<?php

declare(strict_types=1);

namespace App\Core\HttpClient;

use App\Core\Exceptions\ApiException;

class HttpClient
{
    private const RETRYABLE_CODES = [429, 502, 503, 504];

    private int    $maxAttempts;
    private int    $baseDelayMs;
    private int    $maxDelayMs;
    private bool   $sslVerify;
    private string $cainfo;

    public function __construct(array $retryConfig, array $curlConfig = [])
    {
        $this->maxAttempts = (int) ($retryConfig['max_attempts']  ?? 3);
        $this->baseDelayMs = (int) ($retryConfig['base_delay_ms'] ?? 1000);
        $this->maxDelayMs  = (int) ($retryConfig['max_delay_ms']  ?? 30000);
        $this->sslVerify   = (bool) ($curlConfig['ssl_verify']    ?? true);
        $this->cainfo      = (string) ($curlConfig['cainfo']      ?? '');
    }

    public function get(string $url, array $headers = [], array $params = [], int $timeout = 30): array
    {
        if ($params) {
            $url .= '?' . http_build_query($params);
        }

        return $this->request('GET', $url, $headers, null, $timeout);
    }

    public function post(string $url, array $headers = [], array $body = [], int $timeout = 30): array
    {
        $headers['Content-Type'] = 'application/json';
        return $this->request('POST', $url, $headers, json_encode($body), $timeout);
    }

    public function postMultipart(string $url, array $headers = [], array $fields = [], int $timeout = 30): array
    {
        return $this->request('POST', $url, $headers, $fields, $timeout);
    }

    public function postForm(string $url, array $headers = [], array $fields = [], int $timeout = 30): array
    {
        $headers['Content-Type'] = 'application/x-www-form-urlencoded';
        return $this->request('POST', $url, $headers, http_build_query($fields), $timeout);
    }

    public function getRaw(string $url, array $headers = [], int $timeout = 30): string
    {
        [, $body] = $this->execute('GET', $url, $headers, null, $timeout);
        return $body;
    }

    public function postFile(string $url, array $fields = [], array $files = [], int $timeout = 60): array
    {
        $mimeToExt = [
            'image/jpeg' => 'jpg',
            'image/png'  => 'png',
            'image/gif'  => 'gif',
            'image/bmp'  => 'bmp',
            'image/tiff' => 'tiff',
            'video/mp4'  => 'mp4',
            'video/quicktime' => 'mov',
            'video/x-msvideo' => 'avi',
        ];

        $postFields = $fields;
        foreach ($files as $name => $path) {
            if ($path !== '' && file_exists($path)) {
                $mime     = mime_content_type($path) ?: 'application/octet-stream';
                $ext      = $mimeToExt[$mime] ?? (pathinfo($path, PATHINFO_EXTENSION) ?: 'bin');
                $filename = $name . '.' . $ext;
                $postFields[$name] = new \CURLFile($path, $mime, $filename);
            }
        }

        $ch = curl_init();
        $options = [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => $timeout,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $postFields,
            CURLOPT_SSL_VERIFYPEER => $this->sslVerify,
            CURLOPT_SSL_VERIFYHOST => $this->sslVerify ? 2 : 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS      => 5,
        ];

        if ($this->sslVerify && $this->cainfo !== '' && file_exists($this->cainfo)) {
            $options[CURLOPT_CAINFO] = $this->cainfo;
        }

        curl_setopt_array($ch, $options);
        $response   = curl_exec($ch);
        $statusCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if (curl_errno($ch)) {
            $error = curl_error($ch);
            curl_close($ch);
            throw new ApiException("cURL error: {$error}");
        }
        curl_close($ch);

        if ($statusCode < 200 || $statusCode >= 300) {
            throw new ApiException("HTTP {$statusCode} — " . substr((string) $response, 0, 2000), $statusCode);
        }

        return json_decode((string) $response, true) ?? [];
    }

    private function request(
        string            $method,
        string            $url,
        array             $headers,
        null|string|array $body,
        int               $timeout
    ): array {
        $attempt = 0;

        while (true) {
            $attempt++;
            [$statusCode, $responseBody] = $this->execute($method, $url, $headers, $body, $timeout);

            if ($statusCode >= 200 && $statusCode < 300) {
                return json_decode($responseBody, true) ?? [];
            }

            $shouldRetry = in_array($statusCode, self::RETRYABLE_CODES, true)
                && $attempt < $this->maxAttempts;

            if (!$shouldRetry) {
                $truncated = substr($responseBody, 0, 2000);
                throw new ApiException("HTTP {$statusCode} from {$url} — {$truncated}", $statusCode);
            }

            $delayMs = min($this->baseDelayMs * (2 ** ($attempt - 1)), $this->maxDelayMs);
            usleep($delayMs * 1000);
        }
    }

    private function execute(string $method, string $url, array $headers, null|string|array $body, int $timeout): array
    {
        $ch = curl_init();

        // For array bodies (multipart/form-data), let cURL set Content-Type with boundary
        $formattedHeaders = array_map(
            fn($k, $v) => "{$k}: {$v}",
            array_keys(is_array($body) ? array_filter($headers, fn($k) => $k !== 'Content-Type', ARRAY_FILTER_USE_KEY) : $headers),
            is_array($body) ? array_values(array_filter($headers, fn($k) => $k !== 'Content-Type', ARRAY_FILTER_USE_KEY)) : array_values($headers)
        );

        $options = [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => $timeout,
            CURLOPT_HTTPHEADER     => $formattedHeaders,
            CURLOPT_CUSTOMREQUEST  => $method,
            CURLOPT_SSL_VERIFYPEER => $this->sslVerify,
            CURLOPT_SSL_VERIFYHOST => $this->sslVerify ? 2 : 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS      => 5,
        ];

        if ($this->sslVerify && $this->cainfo !== '' && file_exists($this->cainfo)) {
            $options[CURLOPT_CAINFO] = $this->cainfo;
        }

        if ($body !== null) {
            $options[CURLOPT_POSTFIELDS] = $body;
        }

        curl_setopt_array($ch, $options);

        $response   = curl_exec($ch);
        $statusCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if (curl_errno($ch)) {
            $error = curl_error($ch);
            curl_close($ch);
            throw new ApiException("cURL error: {$error}");
        }

        curl_close($ch);

        return [$statusCode, (string) $response];
    }
}
