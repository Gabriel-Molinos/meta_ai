<?php

declare(strict_types=1);

namespace App\Services\WordPress;

use RuntimeException;

class WordPressService
{
    /**
     * Creates a post or page via the WordPress REST API.
     * Uses Application Password auth wrapped in a Gutenberg HTML block so inline styles are preserved.
     *
     * @return array{id: int, link: string, edit_link: string}
     */
    public function createPage(
        string $siteUrl,
        string $username,
        string $appPassword,
        string $title,
        string $htmlContent,
        string $status = 'publish',
        string $postType = 'post',
        int    $featuredMediaId = 0
    ): array {
        $type          = in_array($postType, ['post', 'page'], true) ? $postType : 'post';
        $cleanPassword = str_replace(' ', '', $appPassword);
        $restRoute     = $type === 'page' ? 'pages' : 'posts';
        $endpoint      = rtrim($siteUrl, '/') . '/wp-json/wp/v2/' . $restRoute;
        $basicAuth     = base64_encode($username . ':' . $cleanPassword);

        // Gutenberg HTML block preserves inline styles for all admin-created content
        $wrappedContent = "<!-- wp:html -->\n" . $htmlContent . "\n<!-- /wp:html -->";

        $postFields = [
            'title'   => $title,
            'content' => $wrappedContent,
            'status'  => $status,
        ];
        if ($featuredMediaId > 0) {
            $postFields['featured_media'] = $featuredMediaId;
        }
        $body = json_encode($postFields);

        $ch = curl_init($endpoint);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $body,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'Accept: application/json',
                'Authorization: Basic ' . $basicAuth,
            ],
            CURLOPT_USERAGENT      => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36',
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_SSL_VERIFYPEER => false,
        ]);

        $response  = curl_exec($ch);
        $httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($curlError) {
            throw new RuntimeException('cURL error: ' . $curlError);
        }

        $data = json_decode($response, true);

        if ($httpCode !== 200 && $httpCode !== 201) {
            $msg = $data['message'] ?? substr((string) $response, 0, 300);
            throw new RuntimeException("WordPress API returned HTTP {$httpCode}: {$msg}");
        }

        if (!isset($data['id'])) {
            $detail = is_array($data)
                ? json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
                : substr((string) $response, 0, 500);
            throw new RuntimeException('WordPress API: sem page ID na resposta. HTTP ' . $httpCode . '. Resposta: ' . $detail);
        }

        return [
            'id'               => (int) $data['id'],
            'link'             => $data['link'] ?? '',
            'edit_link'        => rtrim($siteUrl, '/') . '/wp-admin/post.php?post=' . $data['id'] . '&action=edit',
            'content_raw'      => $data['content']['raw']      ?? null,
            'content_len'      => strlen($data['content']['raw']      ?? ''),
            'content_rendered' => $data['content']['rendered'] ?? '',
            'content_rlen'     => strlen($data['content']['rendered'] ?? ''),
        ];
    }

    /**
     * Uploads an image to the WordPress media library.
     *
     * @param  string $imageData  Base64-encoded image
     * @param  string $mimeType   e.g. 'image/png' or 'image/jpeg'
     * @return int    WordPress media attachment ID
     */
    public function uploadMedia(
        string $siteUrl,
        string $username,
        string $appPassword,
        string $imageData,
        string $mimeType
    ): int {
        $cleanPassword = str_replace(' ', '', $appPassword);
        $endpoint      = rtrim($siteUrl, '/') . '/wp-json/wp/v2/media';
        $basicAuth     = base64_encode($username . ':' . $cleanPassword);

        $filename   = 'featured-' . time() . '.jpg';
        $imageBytes = $this->compressForUpload(base64_decode($imageData));

        $ch = curl_init($endpoint);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $imageBytes,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: image/jpeg',
                'Content-Disposition: attachment; filename="' . $filename . '"',
                'Accept: application/json',
                'Authorization: Basic ' . $basicAuth,
            ],
            CURLOPT_USERAGENT      => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36',
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_SSL_VERIFYPEER => false,
        ]);

        $response  = curl_exec($ch);
        $httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($curlError) {
            throw new RuntimeException('cURL error ao enviar mídia: ' . $curlError);
        }

        $data = json_decode($response, true);

        if ($httpCode !== 200 && $httpCode !== 201) {
            $msg = $data['message'] ?? substr((string) $response, 0, 300);
            throw new RuntimeException("WordPress Media API HTTP {$httpCode}: {$msg}");
        }

        if (!isset($data['id'])) {
            throw new RuntimeException('WordPress Media API: sem media ID na resposta.');
        }

        return (int) $data['id'];
    }

    /**
     * Redimensiona e converte para JPEG para evitar 413 no upload ao WordPress.
     * Mantém proporção, limita largura/altura a 1200px, qualidade 85%.
     */
    private function compressForUpload(string $bytes, int $maxDim = 1200, int $quality = 85): string
    {
        if (!\function_exists('imagecreatefromstring')) {
            return $bytes;
        }

        $src = @\imagecreatefromstring($bytes);
        if ($src === false) {
            return $bytes;
        }

        $w = \imagesx($src);
        $h = \imagesy($src);

        if ($w > $maxDim || $h > $maxDim) {
            $ratio = min($maxDim / $w, $maxDim / $h);
            $newW  = (int) round($w * $ratio);
            $newH  = (int) round($h * $ratio);
            $dst   = \imagecreatetruecolor($newW, $newH);
            \imagecopyresampled($dst, $src, 0, 0, 0, 0, $newW, $newH, $w, $h);
            \imagedestroy($src);
            $src = $dst;
        }

        \ob_start();
        \imagejpeg($src, null, $quality);
        $result = \ob_get_clean();
        \imagedestroy($src);

        return $result ?: $bytes;
    }
}
