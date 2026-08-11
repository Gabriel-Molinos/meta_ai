<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Http\Request;
use App\Core\Http\Response;
use App\Middleware\AuthMiddleware;
use App\Services\AI\CreativeAgentService;

class CreativeAgentController
{
    public function __construct(
        private readonly CreativeAgentService $agent,
        private readonly AuthMiddleware       $auth,
    ) {}

    public function analyzeImage(Request $request, array $params): never
    {
        $this->auth->handleAny($request);

        $tmpPath = $_FILES['example_file']['tmp_name'] ?? '';
        $mime    = $_FILES['example_file']['type']      ?? 'image/jpeg';

        if ($tmpPath === '' || !is_file($tmpPath)) {
            Response::error('example_file é obrigatório', 422);
        }

        try {
            $brief = $this->agent->analyzeImageExample($tmpPath, $mime);
            Response::json(['status' => 'success', 'brief' => $brief]);
        } catch (\Throwable $e) {
            Response::error('Erro ao analisar imagem: ' . $e->getMessage());
        }
    }

    public function analyzeVideo(Request $request, array $params): never
    {
        $this->auth->handleAny($request);

        $tmpPath = $_FILES['example_file']['tmp_name'] ?? '';
        $mime    = $_FILES['example_file']['type']      ?? 'video/mp4';

        if ($tmpPath === '' || !is_file($tmpPath)) {
            Response::error('example_file é obrigatório', 422);
        }

        try {
            $brief = $this->agent->analyzeVideoExample($tmpPath, $mime);
            Response::json(['status' => 'success', 'brief' => $brief]);
        } catch (\Throwable $e) {
            Response::error('Erro ao analisar vídeo: ' . $e->getMessage());
        }
    }

    public function analyzeText(Request $request, array $params): never
    {
        $this->auth->handleAny($request);

        $content    = trim((string) $request->input('content', ''));
        $sourceType = $request->input('source_type', 'text') === 'html' ? 'html' : 'text';

        if ($content === '') {
            Response::error('content é obrigatório', 422);
        }

        try {
            $result = $this->agent->analyzeTextExample($content, $sourceType);
            Response::json(['status' => 'success', ...$result]);
        } catch (\Throwable $e) {
            Response::error('Erro ao analisar texto/HTML: ' . $e->getMessage());
        }
    }

    public function generateImage(Request $request, array $params): never
    {
        $this->auth->handleAny($request);

        $prompt        = trim((string) $request->input('prompt', ''));
        $placement     = (string) $request->input('placement', 'feed_square');
        $mediaContext  = $request->input('media_context', 'image') === 'video' ? 'video' : 'image';
        $referenceB64  = $request->input('reference_image_base64');
        $referenceMime = $request->input('reference_image_mime', 'image/png');

        if ($prompt === '') {
            Response::error('prompt é obrigatório', 422);
        }

        $reference = $referenceB64 ? ['data' => $referenceB64, 'mimeType' => $referenceMime] : null;

        try {
            $result = $this->agent->generateImage($prompt, $placement, $reference, $mediaContext);
            Response::json([
                'status'       => 'success',
                'data'         => $result['data'],
                'mime_type'    => $result['mimeType'],
                'aspect_ratio' => $result['aspectRatio'],
            ]);
        } catch (\Throwable $e) {
            Response::error('Erro ao gerar imagem: ' . $e->getMessage());
        }
    }

    public function startVideo(Request $request, array $params): never
    {
        $this->auth->handleAny($request);

        $prompt       = trim((string) $request->input('prompt', ''));
        $placement    = (string) $request->input('placement', 'feed_landscape');
        $duration     = (string) $request->input('duration', '8');
        $resolution   = (string) $request->input('resolution', '720p');
        $quality      = (string) $request->input('quality', 'fast');
        $keyframeB64  = $request->input('keyframe_image_base64');
        $keyframeMime = $request->input('keyframe_image_mime', 'image/png');

        if ($prompt === '' || !$keyframeB64) {
            Response::error('prompt e keyframe_image_base64 são obrigatórios', 422);
        }

        try {
            $result = $this->agent->startVideo(
                $prompt,
                $placement,
                $duration,
                $resolution,
                ['data' => $keyframeB64, 'mimeType' => $keyframeMime],
                $quality !== 'standard',
            );
            Response::json(['status' => 'success', 'operation_name' => $result['operation_name']]);
        } catch (\Throwable $e) {
            Response::error('Erro ao iniciar geração de vídeo: ' . $e->getMessage());
        }
    }

    public function videoStatus(Request $request, array $params): never
    {
        $this->auth->handleAny($request);

        $operationName = $_GET['operation_name'] ?? '';
        if ($operationName === '') {
            Response::error('operation_name é obrigatório', 422);
        }

        try {
            $result = $this->agent->pollVideo($operationName);
            Response::json(['status' => 'success', ...$result]);
        } catch (\Throwable $e) {
            Response::error('Erro ao consultar status do vídeo: ' . $e->getMessage());
        }
    }

    public function downloadVideo(Request $request, array $params): never
    {
        $this->auth->handleAny($request);

        $videoUri = trim((string) $request->input('video_uri', ''));
        if ($videoUri === '') {
            Response::error('video_uri é obrigatório', 422);
        }

        try {
            $result = $this->agent->downloadVideo($videoUri);
            header('Content-Type: ' . $result['mimeType']);
            header('Content-Length: ' . strlen($result['data']));
            echo $result['data'];
            exit;
        } catch (\Throwable $e) {
            Response::error('Erro ao baixar vídeo: ' . $e->getMessage());
        }
    }
}
