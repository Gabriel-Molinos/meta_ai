<?php

declare(strict_types=1);

namespace App\Services\AI;

use App\Core\HttpClient\HttpClient;

class CreativeAnalysisService
{
    private const BASE_URL          = 'https://generativelanguage.googleapis.com/v1beta/models';
    private const FILE_UPLOAD_URL   = 'https://generativelanguage.googleapis.com/upload/v1beta/files';
    private const INLINE_SIZE_LIMIT = 18 * 1024 * 1024; // margem de segurança abaixo do limite de ~20MB do Gemini para dados inline

    private string $apiKey;
    private string $model;
    private int    $timeout;

    public function __construct(array $config, private readonly HttpClient $http)
    {
        $this->apiKey  = $config['api_key'];
        $this->model   = $config['model']   ?? 'gemini-2.5-flash';
        $this->timeout = (int) ($config['timeout'] ?? 180);
    }

    /**
     * Analisa uma imagem de exemplo (multimodal) e devolve uma brief de criação.
     *
     * @return array{description:string,suggested_prompt:string,style:string,mood:string,composition:string,color_palette:array,on_screen_text:string,technical:array}
     */
    public function analyzeImage(string $tmpPath, string $mimeType, array $technicalMeta): array
    {
        $base64 = base64_encode((string) file_get_contents($tmpPath));
        $part   = ['inlineData' => ['mimeType' => $mimeType, 'data' => $base64]];

        $brief              = $this->requestBrief($part, $this->buildImageAnalysisPrompt());
        $brief['technical'] = $technicalMeta;

        return $brief;
    }

    /**
     * Analisa um vídeo de exemplo (multimodal — descrição inclui duração/ritmo aproximados)
     * e devolve uma brief de criação. Vídeos grandes usam o File API em vez de envio inline.
     *
     * @return array{description:string,suggested_prompt:string,style:string,mood:string,composition:string,color_palette:array,on_screen_text:string,technical:array}
     */
    public function analyzeVideo(string $tmpPath, string $mimeType, array $technicalMeta): array
    {
        $size = filesize($tmpPath) ?: 0;

        $part = $size > self::INLINE_SIZE_LIMIT
            ? ['fileData' => ['mimeType' => $mimeType, 'fileUri' => $this->uploadLargeFile($tmpPath, $mimeType)]]
            : ['inlineData' => ['mimeType' => $mimeType, 'data' => base64_encode((string) file_get_contents($tmpPath))]];

        $brief              = $this->requestBrief($part, $this->buildVideoAnalysisPrompt());
        $brief['technical'] = $technicalMeta;

        return $brief;
    }

    /**
     * Decide (a partir de um texto ou HTML) se o resultado deve ser imagem ou vídeo,
     * e já devolve a brief de criação correspondente.
     *
     * @return array{recommended_media_type:string,reasoning:string,brief:array}
     */
    public function analyzeTextOrHtml(string $content, string $sourceType): array
    {
        $kindLabel = $sourceType === 'html' ? 'HTML' : 'texto';

        $prompt = <<<PROMPT
        Você é um diretor de criação decidindo o melhor formato de anúncio para o Meta (Facebook/Instagram) a partir de um briefing em {$kindLabel}.

        Briefing:
        {$content}

        Decida se o anúncio deve ser uma IMAGEM estática ou um VÍDEO curto, e produza uma brief de criação pronta para gerar o criativo.

        Devolva APENAS um JSON com esta forma exata:
        {
          "recommended_media_type": "image" ou "video",
          "reasoning": "1-2 frases explicando a escolha",
          "brief": {
            "description": "descrição objetiva do criativo a ser gerado",
            "suggested_prompt": "um prompt pronto para gerar o criativo — não copie literalmente o briefing",
            "style": "estilo visual (ex: fotografia editorial, ilustração flat, 3D render...)",
            "mood": "clima/sensação (ex: energético, minimalista, urgente...)",
            "composition": "enquadramento e composição",
            "color_palette": ["#hex1", "#hex2", "#hex3"],
            "on_screen_text": "texto que deve aparecer no criativo, ou string vazia"
          }
        }

        Prefira VÍDEO quando o briefing descrever movimento, demonstração de uso, storytelling ou transformação ao longo do tempo.
        Prefira IMAGEM quando o briefing descrever um anúncio estático, uma oferta/preço, ou uma composição parada.
        Responda em português, exceto os valores hexadecimais de cor. Retorne SOMENTE o JSON, sem markdown.
        PROMPT;

        $url      = self::BASE_URL . '/' . $this->model . ':generateContent';
        $headers  = ['x-goog-api-key' => $this->apiKey];
        $body     = [
            'contents'         => [['parts' => [['text' => $prompt]]]],
            'generationConfig' => [
                'temperature'      => 0.4,
                'maxOutputTokens'  => 2048,
                'responseMimeType' => 'application/json',
                'thinkingConfig'   => ['thinkingBudget' => 0],
            ],
        ];

        $response = $this->http->post($url, $headers, $body, $this->timeout);
        $text     = $response['candidates'][0]['content']['parts'][0]['text'] ?? '{}';

        return json_decode($text, true) ?? [];
    }

    private function requestBrief(array $mediaPart, string $prompt): array
    {
        $url     = self::BASE_URL . '/' . $this->model . ':generateContent';
        $headers = ['x-goog-api-key' => $this->apiKey];
        $body    = [
            'contents' => [[
                'parts' => [$mediaPart, ['text' => $prompt]],
            ]],
            'generationConfig' => [
                'temperature'      => 0.4,
                'maxOutputTokens'  => 2048,
                'responseMimeType' => 'application/json',
                'thinkingConfig'   => ['thinkingBudget' => 0],
            ],
        ];

        $response = $this->http->post($url, $headers, $body, $this->timeout);
        $text     = $response['candidates'][0]['content']['parts'][0]['text'] ?? '{}';

        return json_decode($text, true) ?? [];
    }

    private function buildImageAnalysisPrompt(): string
    {
        return <<<PROMPT
        Você é um diretor de arte analisando um criativo de anúncio de exemplo (imagem) para servir de referência a uma nova geração de imagem por IA.

        Descreva o que você vê e devolva APENAS um JSON com esta forma exata:
        {
          "description": "descrição objetiva do que aparece na imagem",
          "suggested_prompt": "um prompt pronto para gerar uma NOVA imagem no mesmo estilo/tema, com composição própria — não copie o texto da imagem original",
          "style": "estilo visual (ex: fotografia editorial, ilustração flat, 3D render...)",
          "mood": "clima/sensação (ex: energético, minimalista, urgente...)",
          "composition": "enquadramento e composição",
          "color_palette": ["#hex1", "#hex2", "#hex3"],
          "on_screen_text": "qualquer texto visível na imagem, ou string vazia se não houver"
        }

        Responda em português, exceto os valores hexadecimais de cor. Retorne SOMENTE o JSON, sem markdown.
        PROMPT;
    }

    private function buildVideoAnalysisPrompt(): string
    {
        return <<<PROMPT
        Você é um diretor de arte analisando um vídeo de exemplo (anúncio) para servir de referência a uma nova geração de vídeo por IA.

        Assista ao vídeo e devolva APENAS um JSON com esta forma exata:
        {
          "description": "descrição objetiva do que acontece no vídeo: cenas, ações, ritmo e duração aproximada",
          "suggested_prompt": "um prompt pronto para gerar um NOVO vídeo curto (4-8s) no mesmo estilo/tema, com cenas próprias — não copie literalmente o vídeo original",
          "style": "estilo visual e de filmagem (ex: cinemático, UGC/celular, motion graphics...)",
          "mood": "clima/sensação (ex: energético, minimalista, urgente...)",
          "composition": "enquadramento, movimento de câmera e ritmo de corte",
          "color_palette": ["#hex1", "#hex2", "#hex3"],
          "on_screen_text": "qualquer texto/legenda visível no vídeo, ou string vazia se não houver"
        }

        Responda em português, exceto os valores hexadecimais de cor. Retorne SOMENTE o JSON, sem markdown.
        PROMPT;
    }

    /**
     * Envia um arquivo grande pelo File API do Gemini (upload resumível em 2 passos)
     * e devolve o fileUri utilizável em partes `fileData`. Usa curl direto (mesmo
     * padrão já usado em AuthController para chamadas pontuais fora do HttpClient
     * compartilhado), porque precisa ler o header X-Goog-Upload-URL da resposta.
     */
    private function uploadLargeFile(string $path, string $mimeType): string
    {
        $size = filesize($path) ?: 0;

        $ch = curl_init(self::FILE_UPLOAD_URL);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_HTTPHEADER     => [
                'X-Goog-Api-Key: ' . $this->apiKey,
                'X-Goog-Upload-Protocol: resumable',
                'X-Goog-Upload-Command: start',
                'X-Goog-Upload-Header-Content-Length: ' . $size,
                'X-Goog-Upload-Header-Content-Type: ' . $mimeType,
                'Content-Type: application/json',
            ],
            CURLOPT_POSTFIELDS     => json_encode(['file' => ['display_name' => basename($path)]]),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER         => true,
            CURLOPT_SSL_VERIFYPEER => PHP_OS_FAMILY !== 'Windows',
            CURLOPT_TIMEOUT        => 30,
        ]);
        $startResponse = (string) curl_exec($ch);
        $headerSize    = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        curl_close($ch);

        if (!preg_match('/x-goog-upload-url:\s*(\S+)/i', substr($startResponse, 0, $headerSize), $m)) {
            throw new \RuntimeException('Gemini File API não retornou a upload URL.');
        }
        $uploadUrl = trim($m[1]);

        $ch = curl_init($uploadUrl);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => file_get_contents($path),
            CURLOPT_HTTPHEADER     => [
                'Content-Length: ' . $size,
                'X-Goog-Upload-Offset: 0',
                'X-Goog-Upload-Command: upload, finalize',
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => PHP_OS_FAMILY !== 'Windows',
            CURLOPT_TIMEOUT        => 120,
        ]);
        $finalizeResponse = json_decode((string) curl_exec($ch), true) ?? [];
        curl_close($ch);

        $uri = $finalizeResponse['file']['uri'] ?? null;
        if (!$uri) {
            throw new \RuntimeException('Gemini File API não retornou o URI do arquivo enviado.');
        }

        return $uri;
    }
}
