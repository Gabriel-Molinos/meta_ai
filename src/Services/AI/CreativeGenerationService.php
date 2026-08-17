<?php

declare(strict_types=1);

namespace App\Services\AI;

use App\Core\HttpClient\HttpClient;

class CreativeGenerationService
{
    private const IMAGE_MODEL = 'gemini-2.5-flash-image';
    private const BASE_URL    = 'https://generativelanguage.googleapis.com/v1beta/models';

    public function __construct(
        private readonly string             $apiKey,
        private readonly HttpClient         $http,
        private readonly ?VertexAuthService $vertexAuth = null,
        private readonly string             $vertexProjectId = '',
        private readonly string             $vertexLocation = 'global',
    ) {}

    /**
     * Resolve URL e headers de autenticação — Vertex AI (service account) quando
     * configurado, com fallback para a API pública do Gemini (x-goog-api-key).
     *
     * @return array{url: string, headers: array<string,string>}
     */
    private function buildRequestTarget(string $action = 'generateContent'): array
    {
        if ($this->vertexAuth !== null) {
            $url = $this->vertexLocation === 'global'
                ? "https://aiplatform.googleapis.com/v1/projects/{$this->vertexProjectId}/locations/global/publishers/google/models/" . self::IMAGE_MODEL . ":{$action}"
                : "https://{$this->vertexLocation}-aiplatform.googleapis.com/v1/projects/{$this->vertexProjectId}/locations/{$this->vertexLocation}/publishers/google/models/" . self::IMAGE_MODEL . ":{$action}";

            return ['url' => $url, 'headers' => ['Authorization' => 'Bearer ' . $this->vertexAuth->getAccessToken()]];
        }

        return [
            'url'     => self::BASE_URL . '/' . self::IMAGE_MODEL . ':' . $action,
            'headers' => ['x-goog-api-key' => $this->apiKey],
        ];
    }

    /**
     * Generates a featured blog post image (landscape, editorial/stock-photo style).
     *
     * @return array{data: string, mimeType: string}
     */
    public function generateFeaturedImage(string $topic, string $title = ''): array
    {
        $target    = $this->buildRequestTarget();
        $titleLine = $title !== '' ? "\nPost title: \"{$title}\"" : '';

        $prompt = <<<PROMPT
Create a professional, high-quality featured image for a blog post.

Topic: {$topic}{$titleLine}

Requirements:
- Editorial / stock-photo aesthetic, clean modern composition
- Landscape orientation (16:9 ratio)
- Bright, professional lighting relevant to the topic
- No text overlays — the title will be added separately
- Suitable as a blog featured image and Open Graph image
- High contrast, vibrant but professional colors
- Photorealistic or high-quality illustration style, not cartoon or clip-art
PROMPT;

        $body = [
            'contents'         => [['role' => 'user', 'parts' => [['text' => $prompt]]]],
            'generationConfig' => ['responseModalities' => ['IMAGE'], 'temperature' => 1.0],
        ];

        $response = $this->http->post($target['url'], $target['headers'], $body, 90);
        $part     = $response['candidates'][0]['content']['parts'][0] ?? null;

        if (isset($part['inlineData']['data'])) {
            return [
                'data'     => $part['inlineData']['data'],
                'mimeType' => $part['inlineData']['mimeType'] ?? 'image/png',
            ];
        }

        throw new \RuntimeException('Gemini não retornou imagem. Resposta: ' . json_encode($response));
    }

    /**
     * Gera um criativo de anúncio, travado na proporção de uma posição do Meta,
     * opcionalmente condicionado a uma imagem de referência (edição/estilo).
     *
     * @param array{data:string,mimeType:string}|null $referenceImage
     * @return array{data: string, mimeType: string}
     */
    public function generateAdCreativeImage(string $prompt, string $aspectRatio, ?array $referenceImage = null): array
    {
        $target = $this->buildRequestTarget();

        $parts = [];
        if ($referenceImage !== null) {
            $parts[] = ['inlineData' => [
                'mimeType' => $referenceImage['mimeType'],
                'data'     => $referenceImage['data'],
            ]];
        }
        $parts[] = ['text' => $prompt];

        $body = [
            'contents'         => [['role' => 'user', 'parts' => $parts]],
            'generationConfig' => [
                'responseModalities' => ['IMAGE'],
                'temperature'        => 1.0,
                'imageConfig'        => ['aspectRatio' => $aspectRatio],
            ],
        ];

        $response = $this->http->post($target['url'], $target['headers'], $body, 90);
        $part     = $response['candidates'][0]['content']['parts'][0] ?? null;

        if (isset($part['inlineData']['data'])) {
            return [
                'data'     => $part['inlineData']['data'],
                'mimeType' => $part['inlineData']['mimeType'] ?? 'image/png',
            ];
        }

        throw new \RuntimeException('Gemini não retornou imagem. Resposta: ' . json_encode($response));
    }
}
