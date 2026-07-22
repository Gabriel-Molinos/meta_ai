<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Http\Request;
use App\Core\Http\Response;
use App\Core\HttpClient\HttpClient;
use App\Middleware\AuthMiddleware;
use App\Models\Account;
use App\Services\MetaAds\CampaignCreatorService;
use App\Services\MetaAds\PixelService;

class CampaignGeneratorController
{
    public function __construct(
        private readonly Account         $accountModel,
        private readonly HttpClient      $http,
        private readonly AuthMiddleware  $auth
    ) {}

    public function pixels(Request $request, array $params): never
    {
        $this->auth->handle($request);
        $cfg     = $this->resolveAccount($_GET['account_key'] ?? '');
        $service = new PixelService($cfg['meta_ads'], $this->http);
        Response::json(['data' => $service->fetchPixels()]);
    }

    public function events(Request $request, array $params): never
    {
        $this->auth->handle($request);
        $cfg     = $this->resolveAccount($_GET['account_key'] ?? '');
        $service = new PixelService($cfg['meta_ads'], $this->http);
        Response::json(['data' => $service->fetchPixelEvents($_GET['pixel_id'] ?? '')]);
    }

    public function pages(Request $request, array $params): never
    {
        $this->auth->handle($request);
        $cfg     = $this->resolveAccount($_GET['account_key'] ?? '');
        $service = new PixelService($cfg['meta_ads'], $this->http);
        Response::json(['data' => $service->fetchPages()]);
    }

    public function customConversions(Request $request, array $params): never
    {
        $this->auth->handle($request);
        $cfg     = $this->resolveAccount($_GET['account_key'] ?? '');
        $service = new PixelService($cfg['meta_ads'], $this->http);
        Response::json(['data' => $service->fetchCustomConversions()]);
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
            ]);

            $results = [];

            foreach ($ads as $i => $ad) {
                $tmpPath   = $_FILES['ads_files']['tmp_name'][$i] ?? null;
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

                if ($mediaType === 'video') {
                    $video = $creator->uploadVideo($tmpPath, $creative['headline']);
                    $creative['video_id']      = $video['id'];
                    $creative['thumbnail_url'] = $video['thumbnail_url'];
                    $creativeId = $creator->createVideoCreative($creative);
                } else {
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
            $label = 'Anúncio ' . ($i + 1) . (isset($ad['name']) && $ad['name'] !== '' ? ' ("' . $ad['name'] . '")' : '');
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
