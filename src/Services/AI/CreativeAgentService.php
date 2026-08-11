<?php

declare(strict_types=1);

namespace App\Services\AI;

class CreativeAgentService
{
    // Meta pede 1.91:1 para "Feed retângulo", mas o Gemini image não tem esse ratio exato —
    // 16:9 (1.78) é o mais próximo disponível.
    private const PLACEMENT_IMAGE_RATIOS = [
        'feed_square'    => '1:1',
        'feed_rectangle' => '16:9',
        'feed_vertical'  => '4:5',
        'stories_reels'  => '9:16',
    ];

    // O Veo só aceita 16:9 e 9:16 — "feed_square" (1:1) é ambíguo entre os dois,
    // por isso o valor aqui é só o padrão; a UI deve deixar o usuário sobrepor.
    private const PLACEMENT_VIDEO_RATIOS = [
        'feed_landscape' => '16:9',
        'feed_square'    => '16:9',
        'feed_vertical'  => '9:16',
        'stories_reels'  => '9:16',
    ];

    public function __construct(
        private readonly CreativeAnalysisService   $analysis,
        private readonly CreativeGenerationService $imageGen,
        private readonly VeoVideoService           $videoGen,
    ) {}

    public function analyzeImageExample(string $tmpPath, string $mimeType): array
    {
        $size      = @getimagesize($tmpPath);
        $technical = [
            'width'      => $size[0] ?? null,
            'height'     => $size[1] ?? null,
            'mime_type'  => $mimeType,
            'size_bytes' => filesize($tmpPath) ?: 0,
        ];

        return $this->analysis->analyzeImage($tmpPath, $mimeType, $technical);
    }

    public function analyzeVideoExample(string $tmpPath, string $mimeType): array
    {
        $technical = [
            'mime_type'  => $mimeType,
            'size_bytes' => filesize($tmpPath) ?: 0,
        ];

        return $this->analysis->analyzeVideo($tmpPath, $mimeType, $technical);
    }

    public function analyzeTextExample(string $content, string $sourceType): array
    {
        return $this->analysis->analyzeTextOrHtml($content, $sourceType);
    }

    /**
     * Gera uma imagem final de anúncio (mediaContext='image') ou o frame inicial
     * de um vídeo (mediaContext='video', mesma proporção do vídeo alvo).
     *
     * @param array{data:string,mimeType:string}|null $referenceImage
     */
    public function generateImage(
        string  $prompt,
        string  $placementKey,
        ?array  $referenceImage = null,
        string  $mediaContext = 'image',
    ): array {
        $ratio  = $this->mapAspectRatio($placementKey, $mediaContext);
        $result = $this->imageGen->generateAdCreativeImage($prompt, $ratio, $referenceImage);

        $result['aspectRatio'] = $ratio;

        return $result;
    }

    /**
     * @param array{data:string,mimeType:string} $keyframeImage
     */
    public function startVideo(
        string $prompt,
        string $placementKey,
        string $duration,
        string $resolution,
        array  $keyframeImage,
        bool   $fast,
    ): array {
        $ratio         = $this->mapAspectRatio($placementKey, 'video');
        $operationName = $this->videoGen->startGeneration($prompt, $ratio, $duration, $resolution, $keyframeImage, $fast);

        return ['operation_name' => $operationName];
    }

    public function pollVideo(string $operationName): array
    {
        return $this->videoGen->pollOperation($operationName);
    }

    public function downloadVideo(string $videoUri): array
    {
        return $this->videoGen->downloadVideo($videoUri);
    }

    public function mapAspectRatio(string $placementKey, string $mediaType): string
    {
        $table = $mediaType === 'video' ? self::PLACEMENT_VIDEO_RATIOS : self::PLACEMENT_IMAGE_RATIOS;

        return $table[$placementKey] ?? ($mediaType === 'video' ? '16:9' : '1:1');
    }
}
