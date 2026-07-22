<?php

declare(strict_types=1);

namespace App\Services\AI;

use App\Core\HttpClient\HttpClient;

class CreativeGenerationService
{
    private const IMAGE_MODEL = 'gemini-2.5-flash-image';
    private const BASE_URL    = 'https://generativelanguage.googleapis.com/v1beta/models';

    public function __construct(
        private readonly string     $apiKey,
        private readonly HttpClient $http
    ) {}

    /**
     * Generates a featured blog post image (landscape, editorial/stock-photo style).
     *
     * @return array{data: string, mimeType: string}
     */
    public function generateFeaturedImage(string $topic, string $title = ''): array
    {
        $url     = self::BASE_URL . '/' . self::IMAGE_MODEL . ':generateContent';
        $headers = ['x-goog-api-key' => $this->apiKey];

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
            'contents'         => [['parts' => [['text' => $prompt]]]],
            'generationConfig' => ['responseModalities' => ['IMAGE'], 'temperature' => 1.0],
        ];

        $response = $this->http->post($url, $headers, $body, 90);
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
