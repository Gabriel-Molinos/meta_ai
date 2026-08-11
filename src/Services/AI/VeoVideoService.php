<?php

declare(strict_types=1);

namespace App\Services\AI;

use App\Core\HttpClient\HttpClient;

class VeoVideoService
{
    private const BASE_URL       = 'https://generativelanguage.googleapis.com/v1beta';
    private const MODEL_STANDARD = 'veo-3.1-generate-preview';
    private const MODEL_FAST     = 'veo-3.1-fast-generate-preview';

    public function __construct(
        private readonly string     $apiKey,
        private readonly HttpClient $http,
    ) {}

    /**
     * Inicia a geração assíncrona de vídeo (operação de longa duração do Veo).
     * Retorna imediatamente o nome da operação, usado para consultar o status.
     *
     * @param array{data:string,mimeType:string}|null $keyframeImage imagem inicial (image-to-video)
     */
    public function startGeneration(
        string $prompt,
        string $aspectRatio,     // '16:9' ou '9:16' — únicos suportados pelo Veo
        string $durationSeconds, // '4', '6' ou '8'
        string $resolution = '720p',
        ?array $keyframeImage = null,
        bool   $fast = true,
    ): string {
        $model = $fast ? self::MODEL_FAST : self::MODEL_STANDARD;
        $url   = self::BASE_URL . '/models/' . $model . ':predictLongRunning';

        $instance = ['prompt' => $prompt];
        if ($keyframeImage !== null) {
            // A API do Veo (predictLongRunning) usa o formato de imagem no estilo
            // Vertex/Predict (bytesBase64Encoded), não o `inlineData` do generateContent —
            // confirmado empiricamente: `inlineData` é rejeitado com 400 INVALID_ARGUMENT.
            $instance['image'] = [
                'bytesBase64Encoded' => $keyframeImage['data'],
                'mimeType'           => $keyframeImage['mimeType'],
            ];
        }

        $body = [
            'instances'  => [$instance],
            'parameters' => [
                'aspectRatio'     => $aspectRatio,
                'resolution'      => $resolution,
                'durationSeconds' => (int) $durationSeconds, // a API rejeita string com 400 INVALID_ARGUMENT
            ],
        ];

        $response = $this->http->post($url, ['x-goog-api-key' => $this->apiKey], $body, 60);
        $name     = $response['name'] ?? null;

        if (!$name) {
            throw new \RuntimeException('Veo não retornou o nome da operação. Resposta: ' . json_encode($response));
        }

        return $name;
    }

    /**
     * Consulta UMA vez o status da operação — não bloqueia nem repete sozinho.
     * Quem chama (o navegador, via poll no endpoint HTTP) decide se consulta de novo.
     *
     * @return array{done: bool, videoUri: ?string, error: ?string}
     */
    public function pollOperation(string $operationName): array
    {
        // Operações do Google seguem o padrão LRO: GET {apiRoot}/{operation.name},
        // onde "name" já vem como "operations/xxx" na resposta do predictLongRunning.
        $url      = self::BASE_URL . '/' . ltrim($operationName, '/');
        $response = $this->http->get($url, ['x-goog-api-key' => $this->apiKey], [], 30);

        $done = (bool) ($response['done'] ?? false);
        if (!$done) {
            return ['done' => false, 'videoUri' => null, 'error' => null];
        }

        if (isset($response['error'])) {
            return [
                'done'     => true,
                'videoUri' => null,
                'error'    => $response['error']['message'] ?? 'Erro desconhecido na geração do vídeo.',
            ];
        }

        $videoUri = $response['response']['generateVideoResponse']['generatedSamples'][0]['video']['uri'] ?? null;

        return [
            'done'     => true,
            'videoUri' => $videoUri,
            'error'    => $videoUri ? null : 'Vídeo pronto, mas a resposta não trouxe a URI.',
        ];
    }

    /**
     * Baixa os bytes do vídeo pronto — a URI assinada do Veo exige o mesmo header
     * de API key usado nas outras chamadas Gemini.
     *
     * @return array{data: string, mimeType: string}
     */
    public function downloadVideo(string $videoUri): array
    {
        $bytes = $this->http->getRaw($videoUri, ['x-goog-api-key' => $this->apiKey], 120);

        return ['data' => $bytes, 'mimeType' => 'video/mp4'];
    }
}
