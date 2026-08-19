<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Http\Request;
use App\Core\Http\Response;
use App\Core\HttpClient\HttpClient;
use App\Middleware\AuthMiddleware;
use App\Models\Account;
use App\Models\CampaignDraft;
use App\Services\MetaAds\CampaignCreatorService;
use App\Services\MetaAds\PixelService;

class CampaignGeneratorController
{
    public function __construct(
        private readonly Account        $accountModel,
        private readonly HttpClient     $http,
        private readonly AuthMiddleware $auth,
        private readonly CampaignDraft  $draftModel,
    ) {}

    public function pixels(Request $request, array $params): never
    {
        $this->auth->handleAny($request);
        $cfg = $this->resolveAccount($_GET['account_key'] ?? '');
        try {
            $service = new PixelService($cfg['meta_ads'], $this->http);
            Response::json(['data' => $service->fetchPixels()]);
        } catch (\Throwable $e) {
            Response::error('Erro ao buscar pixels: ' . $e->getMessage());
        }
    }

    public function events(Request $request, array $params): never
    {
        $this->auth->handleAny($request);
        $cfg = $this->resolveAccount($_GET['account_key'] ?? '');
        try {
            $service = new PixelService($cfg['meta_ads'], $this->http);
            Response::json(['data' => $service->fetchPixelEvents($_GET['pixel_id'] ?? '')]);
        } catch (\Throwable $e) {
            Response::error('Erro ao buscar eventos: ' . $e->getMessage());
        }
    }

    public function pages(Request $request, array $params): never
    {
        $this->auth->handleAny($request);
        $cfg = $this->resolveAccount($_GET['account_key'] ?? '');
        try {
            $service = new PixelService($cfg['meta_ads'], $this->http);
            Response::json(['data' => $service->fetchPages()]);
        } catch (\Throwable $e) {
            Response::error('Erro ao buscar páginas: ' . $e->getMessage());
        }
    }

    public function customConversions(Request $request, array $params): never
    {
        $this->auth->handleAny($request);
        $cfg     = $this->resolveAccount($_GET['account_key'] ?? '');
        $service = new PixelService($cfg['meta_ads'], $this->http);
        Response::json(['data' => $service->fetchCustomConversions($_GET['pixel_id'] ?? '')]);
    }

    public function create(Request $request, array $params): never
    {
        $this->auth->handle($request);

        $cfg     = $this->resolveAccount($_POST['account_key'] ?? '');
        $creator = new CampaignCreatorService($cfg['meta_ads'], $this->http);

        $objective      = $_POST['objective']        ?? 'OUTCOME_SALES';
        $campaignName   = $_POST['campaign_name']   ?? 'Nova Campanha';
        $campaignStatus = in_array($_POST['campaign_status'] ?? '', ['ACTIVE', 'PAUSED'], true)
            ? $_POST['campaign_status']
            : 'PAUSED';

        try {
            $ads = $_POST['ads'] ?? [];

            // Valida todos os arquivos antes de criar qualquer recurso no Meta
            $this->validateAdFiles($ads);

            $campaignId = $creator->createCampaign($campaignName, $objective, $campaignStatus);

            $isDynamic = in_array('dynamic', array_column($ads, 'media_type'), true);

            $adSetId = $creator->createAdSet($campaignId, [
                'campaign_status'      => $campaignStatus,
                'campaign_name'        => $campaignName,
                'objective'            => $objective,
                'countries'            => $_POST['countries'] ?? ['BR'],
                'locales'              => $_POST['locales']   ?? [],
                'daily_budget_cents'   => (int) (((float) ($_POST['daily_budget'] ?? 10)) * 100),
                'start_time'           => $_POST['start_time'] ?? date('Y-m-d'),
                'pixel_id'             => $_POST['pixel_id']   ?? '',
                'pixel_event'          => $_POST['pixel_event'] ?? '',
                'age_min'              => (int) ($_POST['age_min'] ?? 18),
                'age_max'              => (int) ($_POST['age_max'] ?? 65),
                'advantage_audience'   => (int) ($_POST['advantage_audience'] ?? 1),
                'custom_event_str'     => $_POST['custom_event_str'] ?? '',
                'custom_conversion_id' => $_POST['custom_conversion_id'] ?? '',
                'publisher_platforms'  => $_POST['publisher_platforms'] ?? ['facebook', 'instagram'],
                'facebook_positions'   => $_POST['facebook_positions']  ?? ['feed', 'facebook_reels', 'story'],
                'instagram_positions'  => $_POST['instagram_positions'] ?? ['stream', 'reels', 'story'],
                'messenger_positions'  => $_POST['messenger_positions'] ?? [],
                'is_dynamic_creative'  => $isDynamic,
            ]);

            $results = [];

            foreach ($ads as $i => $ad) {
                $mediaType = $ad['media_type'] ?? 'image';
                $pageId    = $_POST['page_id'] ?? '';

                $creative = [
                    'name'              => $ad['name']            ?? "Anúncio " . ($i + 1),
                    'page_id'           => $pageId,
                    'primary_text'      => $ad['primary_text']    ?? '',
                    'headline'          => $ad['headline']        ?? '',
                    'cta'               => $ad['cta']             ?? 'LEARN_MORE',
                    'destination_url'   => $ad['destination_url'] ?? '',
                    'instagram_user_id' => $_POST['instagram_user_id'] ?? '',
                    'link_description'  => $ad['link_description'] ?? '',
                    'url_tags'          => $ad['url_tags']         ?? '',
                ];

                if ($mediaType === 'carousel') {
                    $tmpPaths = (array) ($_FILES['ads_files']['tmp_name'][$i] ?? []);
                    $creative['videos'] = $creator->uploadVideosAndWait($tmpPaths, $creative['headline']);
                    $creativeId = $creator->createCarouselCreative($creative);
                } elseif ($mediaType === 'dynamic') {
                    $tmpPaths = (array) ($_FILES['ads_files']['tmp_name'][$i] ?? []);
                    $creative['videos'] = $creator->uploadVideosAndWait($tmpPaths, $creative['headline']);
                    $creativeId = $creator->createDynamicCreative($creative);
                } elseif ($mediaType === 'video') {
                    $tmpPath = $_FILES['ads_files']['tmp_name'][$i] ?? null;
                    $video = $creator->uploadVideo($tmpPath, $creative['headline']);
                    $creative['video_id']      = $video['id'];
                    $creative['thumbnail_url'] = $video['thumbnail_url'];
                    $creativeId = $creator->createVideoCreative($creative);
                } else {
                    $tmpPath = $_FILES['ads_files']['tmp_name'][$i] ?? null;
                    $creative['image_hash'] = $creator->uploadImage($tmpPath);
                    $creativeId = $creator->createImageCreative($creative);
                }

                $adId = $creator->createAd($adSetId, $creativeId, $creative['name'], $campaignStatus);
                $results[] = [
                    'ad_name'     => $creative['name'],
                    'ad_id'       => $adId,
                    'creative_id' => $creativeId,
                ];
            }

            Response::json([
                'success'     => true,
                'campaign_id' => $campaignId,
                'adset_id'    => $adSetId,
                'ads'         => $results,
            ]);
        } catch (\Throwable $e) {
            Response::json(['success' => false, 'error' => $e->getMessage()], 422);
        }
    }

    public function submit(Request $request, array $params): never
    {
        $ctx = $this->auth->resolveContext($request);
        $userId = $ctx['type'] === 'user' ? $ctx['user_id'] : 0;

        $accountKey     = $_POST['account_key'] ?? '';
        $objective      = $_POST['objective']    ?? 'OUTCOME_SALES';
        $campaignName   = $_POST['campaign_name'] ?? 'Nova Campanha';
        $campaignStatus = in_array($_POST['campaign_status'] ?? '', ['ACTIVE', 'PAUSED'], true)
            ? $_POST['campaign_status']
            : 'PAUSED';

        if ($accountKey === '') {
            Response::error('account_key é obrigatório', 422);
        }

        $ads = $_POST['ads'] ?? [];

        // Converte cada arquivo para base64
        $creatives = [];
        foreach ($ads as $i => $ad) {
            $mediaType = $ad['media_type'] ?? 'image';

            if ($mediaType === 'carousel') {
                $errors = (array) ($_FILES['ads_files']['error'][$i] ?? []);
                $paths  = (array) ($_FILES['ads_files']['tmp_name'][$i] ?? []);
                $mimes  = (array) ($_FILES['ads_files']['type'][$i] ?? []);
                if (count($paths) < 2 || count($paths) > 5) {
                    Response::error('Carrossel do anúncio ' . ($i + 1) . ' exige de 2 a 5 vídeos', 422);
                }
                $items = [];
                foreach ($paths as $j => $tmpPath) {
                    $errCode = $errors[$j] ?? UPLOAD_ERR_NO_FILE;
                    if ($errCode !== UPLOAD_ERR_OK || $tmpPath === '' || !is_file($tmpPath)) {
                        Response::error('Vídeo ' . ($j + 1) . ' do anúncio ' . ($i + 1) . ' inválido ou ausente', 422);
                    }
                    $items[] = [
                        'mime_type'   => $mimes[$j] ?? 'video/mp4',
                        'data_base64' => base64_encode(file_get_contents($tmpPath)),
                    ];
                }
                $creatives[$i] = [
                    'index'      => $i,
                    'media_type' => 'carousel',
                    'items'      => $items,
                ];
                continue;
            }

            $errCode = $_FILES['ads_files']['error'][$i] ?? UPLOAD_ERR_NO_FILE;
            $tmpPath = $_FILES['ads_files']['tmp_name'][$i] ?? '';
            $mime    = $_FILES['ads_files']['type'][$i]     ?? 'application/octet-stream';
            $name    = $_FILES['ads_files']['name'][$i]     ?? ('file_' . $i);

            if ($errCode !== UPLOAD_ERR_OK || $tmpPath === '' || !is_file($tmpPath)) {
                Response::error('Arquivo do anúncio ' . ($i + 1) . ' inválido ou ausente', 422);
            }

            $creatives[$i] = [
                'index'       => $i,
                'name'        => $name,
                'mime_type'   => $mime,
                'media_type'  => $mediaType,
                'data_base64' => base64_encode(file_get_contents($tmpPath)),
            ];
        }

        $payload = [
            'account_key'          => $accountKey,
            'campaign_name'        => $campaignName,
            'objective'            => $objective,
            'campaign_status'      => $campaignStatus,
            'daily_budget'         => $_POST['daily_budget']         ?? '10',
            'start_time'           => $_POST['start_time']           ?? date('Y-m-d'),
            'countries'            => $_POST['countries']            ?? ['BR'],
            'locales'              => $_POST['locales']              ?? [],
            'age_min'              => (int) ($_POST['age_min']       ?? 18),
            'age_max'              => (int) ($_POST['age_max']       ?? 65),
            'advantage_audience'   => (int) ($_POST['advantage_audience'] ?? 1),
            'publisher_platforms'  => $_POST['publisher_platforms']  ?? ['facebook', 'instagram'],
            'facebook_positions'   => $_POST['facebook_positions']   ?? ['feed'],
            'instagram_positions'  => $_POST['instagram_positions']  ?? ['stream'],
            'messenger_positions'  => $_POST['messenger_positions']  ?? [],
            'pixel_id'             => $_POST['pixel_id']             ?? '',
            'pixel_event'          => $_POST['pixel_event']          ?? '',
            'custom_conversion_id' => $_POST['custom_conversion_id'] ?? '',
            'custom_event_str'     => $_POST['custom_event_str']     ?? '',
            'page_id'              => $_POST['page_id']              ?? '',
            'destination_url'      => $_POST['destination_url']      ?? '',
            'instagram_user_id'    => $_POST['instagram_user_id']    ?? '',
            'ads'                  => array_map(fn($ad) => [
                'name'             => $ad['name']             ?? '',
                'primary_text'     => $ad['primary_text']     ?? '',
                'headline'         => $ad['headline']         ?? '',
                'cta'              => $ad['cta']              ?? 'LEARN_MORE',
                'media_type'       => $ad['media_type']       ?? 'image',
                'link_description' => $ad['link_description'] ?? '',
                'url_tags'         => $ad['url_tags']         ?? '',
                'destination_url'  => $_POST['destination_url'] ?? '',
            ], $ads),
        ];

        $draftId = $this->draftModel->create($userId, $accountKey, $payload, array_values($creatives));
        Response::json(['status' => 'success', 'draft_id' => $draftId]);
    }

    public function resubmit(Request $request, array $params): never
    {
        $ctx = $this->auth->resolveContext($request);
        $id  = (int) ($params['id'] ?? 0);

        if ($ctx['type'] === 'user' && !$this->draftModel->belongsToUser($id, $ctx['user_id'])) {
            Response::error('Não autorizado', 403);
        }

        $draft = $this->draftModel->findById($id);
        if (!$draft) {
            Response::error('Draft não encontrado', 404);
        }

        $ads             = $_POST['ads'] ?? [];
        $existingCreatives = $draft['creatives'] ?? [];

        // Para cada ad: usar novo arquivo se fornecido, senão manter existente
        $creatives = [];
        foreach ($ads as $i => $ad) {
            $mediaType = $ad['media_type'] ?? 'image';
            $rawPaths  = $_FILES['ads_files']['tmp_name'][$i] ?? null;

            if ($mediaType === 'carousel' && is_array($rawPaths)) {
                $errors = (array) ($_FILES['ads_files']['error'][$i] ?? []);
                $mimes  = (array) ($_FILES['ads_files']['type'][$i] ?? []);
                $items  = [];
                foreach ($rawPaths as $j => $tmpPath) {
                    $errCode = $errors[$j] ?? UPLOAD_ERR_NO_FILE;
                    if ($errCode === UPLOAD_ERR_OK && $tmpPath !== '' && is_file($tmpPath)) {
                        $items[] = [
                            'mime_type'   => $mimes[$j] ?? 'video/mp4',
                            'data_base64' => base64_encode(file_get_contents($tmpPath)),
                        ];
                    }
                }
                $creatives[$i] = count($items) >= 2
                    ? ['index' => $i, 'media_type' => 'carousel', 'items' => $items]
                    : ($existingCreatives[$i] ?? ['index' => $i, 'media_type' => 'carousel', 'items' => []]);
                continue;
            }

            $errCode = is_array($rawPaths) ? UPLOAD_ERR_NO_FILE : ($_FILES['ads_files']['error'][$i] ?? UPLOAD_ERR_NO_FILE);
            $tmpPath = is_array($rawPaths) ? '' : ($rawPaths ?? '');

            if ($errCode === UPLOAD_ERR_OK && $tmpPath !== '' && is_file($tmpPath)) {
                $mime = $_FILES['ads_files']['type'][$i] ?? 'application/octet-stream';
                $name = $_FILES['ads_files']['name'][$i] ?? ('file_' . $i);
                $creatives[$i] = [
                    'index'       => $i,
                    'name'        => $name,
                    'mime_type'   => $mime,
                    'media_type'  => $mediaType,
                    'data_base64' => base64_encode(file_get_contents($tmpPath)),
                ];
            } else {
                // Mantém criativo existente se não foi re-enviado
                $creatives[$i] = $existingCreatives[$i] ?? [
                    'index'       => $i,
                    'name'        => 'file_' . $i,
                    'mime_type'   => 'image/jpeg',
                    'media_type'  => $mediaType,
                    'data_base64' => '',
                ];
            }
        }

        $accountKey = $_POST['account_key'] ?? $draft['account_key'];
        $payload    = [
            'account_key'          => $accountKey,
            'campaign_name'        => $_POST['campaign_name']        ?? $draft['payload']['campaign_name'],
            'objective'            => $_POST['objective']            ?? $draft['payload']['objective'],
            'campaign_status'      => $_POST['campaign_status']      ?? $draft['payload']['campaign_status'],
            'daily_budget'         => $_POST['daily_budget']         ?? $draft['payload']['daily_budget'],
            'start_time'           => $_POST['start_time']           ?? $draft['payload']['start_time'],
            'countries'            => $_POST['countries']            ?? $draft['payload']['countries'],
            'locales'              => $_POST['locales']              ?? $draft['payload']['locales'],
            'age_min'              => (int) ($_POST['age_min']       ?? $draft['payload']['age_min']),
            'age_max'              => (int) ($_POST['age_max']       ?? $draft['payload']['age_max']),
            'advantage_audience'   => (int) ($_POST['advantage_audience'] ?? $draft['payload']['advantage_audience']),
            'publisher_platforms'  => $_POST['publisher_platforms']  ?? $draft['payload']['publisher_platforms'],
            'facebook_positions'   => $_POST['facebook_positions']   ?? $draft['payload']['facebook_positions'],
            'instagram_positions'  => $_POST['instagram_positions']  ?? $draft['payload']['instagram_positions'],
            'messenger_positions'  => $_POST['messenger_positions']  ?? $draft['payload']['messenger_positions'],
            'pixel_id'             => $_POST['pixel_id']             ?? $draft['payload']['pixel_id'],
            'pixel_event'          => $_POST['pixel_event']          ?? $draft['payload']['pixel_event'],
            'custom_conversion_id' => $_POST['custom_conversion_id'] ?? $draft['payload']['custom_conversion_id'],
            'custom_event_str'     => $_POST['custom_event_str']     ?? $draft['payload']['custom_event_str'],
            'page_id'              => $_POST['page_id']              ?? $draft['payload']['page_id'],
            'destination_url'      => $_POST['destination_url']      ?? $draft['payload']['destination_url'],
            'instagram_user_id'    => $_POST['instagram_user_id']    ?? $draft['payload']['instagram_user_id'],
            'ads'                  => array_map(fn($ad) => [
                'name'             => $ad['name']             ?? '',
                'primary_text'     => $ad['primary_text']     ?? '',
                'headline'         => $ad['headline']         ?? '',
                'cta'              => $ad['cta']              ?? 'LEARN_MORE',
                'media_type'       => $ad['media_type']       ?? 'image',
                'link_description' => $ad['link_description'] ?? '',
                'url_tags'         => $ad['url_tags']         ?? '',
                'destination_url'  => $_POST['destination_url'] ?? $draft['payload']['destination_url'] ?? '',
            ], $ads ?: $draft['payload']['ads']),
        ];

        $this->draftModel->resubmit($id, $payload, array_values($creatives));
        Response::json(['status' => 'success', 'draft_id' => $id]);
    }

    private function validateAdFiles(array $ads): void
    {
        $errorMessages = [
            UPLOAD_ERR_INI_SIZE   => 'tamanho excede o limite do servidor (upload_max_filesize)',
            UPLOAD_ERR_FORM_SIZE  => 'tamanho excede o limite do formulário',
            UPLOAD_ERR_PARTIAL    => 'arquivo recebido parcialmente — tente novamente',
            UPLOAD_ERR_NO_FILE    => 'nenhum arquivo selecionado',
            UPLOAD_ERR_NO_TMP_DIR => 'diretório temporário do servidor não encontrado',
            UPLOAD_ERR_CANT_WRITE => 'falha ao gravar arquivo temporário no servidor',
            UPLOAD_ERR_EXTENSION  => 'upload bloqueado por extensão PHP',
        ];

        foreach ($ads as $i => $ad) {
            $label     = 'Anúncio ' . ($i + 1) . (isset($ad['name']) && $ad['name'] !== '' ? ' ("' . $ad['name'] . '")' : '');
            $mediaType = $ad['media_type'] ?? 'image';

            if ($mediaType === 'carousel' || $mediaType === 'dynamic') {
                $errors  = (array) ($_FILES['ads_files']['error'][$i] ?? []);
                $paths   = (array) ($_FILES['ads_files']['tmp_name'][$i] ?? []);
                $maxVids = $mediaType === 'carousel' ? 5 : 10;
                if (count($paths) < 2 || count($paths) > $maxVids) {
                    $formatLabel = $mediaType === 'carousel' ? 'carrossel exige de 2 a 5 vídeos' : 'anúncio de vários vídeos exige de 2 a 10 vídeos';
                    throw new \RuntimeException("{$label}: {$formatLabel}");
                }
                foreach ($paths as $j => $tmpPath) {
                    $errCode = $errors[$j] ?? UPLOAD_ERR_NO_FILE;
                    if ($errCode !== UPLOAD_ERR_OK) {
                        $msg = $errorMessages[$errCode] ?? "erro de upload (código {$errCode})";
                        throw new \RuntimeException("{$label}, vídeo " . ($j + 1) . ": {$msg}");
                    }
                    if ($tmpPath === '' || !is_file($tmpPath)) {
                        throw new \RuntimeException("{$label}, vídeo " . ($j + 1) . ": arquivo temporário não encontrado no servidor");
                    }
                }
                continue;
            }

            $errCode = $_FILES['ads_files']['error'][$i] ?? UPLOAD_ERR_NO_FILE;
            if ($errCode !== UPLOAD_ERR_OK) {
                $msg = $errorMessages[$errCode] ?? "erro de upload (código {$errCode})";
                throw new \RuntimeException("{$label}: {$msg}");
            }
            $tmpPath = $_FILES['ads_files']['tmp_name'][$i] ?? '';
            if ($tmpPath === '' || !is_file($tmpPath)) {
                throw new \RuntimeException("{$label}: arquivo temporário não encontrado no servidor");
            }
        }
    }

    private function resolveAccount(string $key): array
    {
        if ($key === '') {
            Response::error('account_key é obrigatório', 422);
        }
        $row = $this->accountModel->find($key);
        if ($row === null) {
            Response::error("Conta '{$key}' não encontrada", 404);
        }
        return $this->accountModel->toConfig($row);
    }
}
