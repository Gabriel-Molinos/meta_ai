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

class ApprovalController
{
    public function __construct(
        private readonly AuthMiddleware $auth,
        private readonly Account        $accountModel,
        private readonly CampaignDraft  $draftModel,
        private readonly HttpClient     $http,
    ) {}

    public function index(Request $request, array $params): never
    {
        $ctx = $this->auth->resolveContext($request);
        if ($ctx['type'] !== 'admin') {
            Response::error('Acesso restrito ao administrador', 403);
        }
        Response::json(['status' => 'success', 'data' => $this->draftModel->allForAdmin()]);
    }

    public function show(Request $request, array $params): never
    {
        $ctx = $this->auth->resolveContext($request);
        if ($ctx['type'] !== 'admin') {
            Response::error('Acesso restrito ao administrador', 403);
        }
        $draft = $this->draftModel->findById((int) ($params['id'] ?? 0));
        if (!$draft) {
            Response::error('Draft não encontrado', 404);
        }
        Response::json(['status' => 'success', 'data' => $draft]);
    }

    public function approve(Request $request, array $params): never
    {
        $ctx = $this->auth->resolveContext($request);
        if ($ctx['type'] !== 'admin') {
            Response::error('Acesso restrito ao administrador', 403);
        }

        $id    = (int) ($params['id'] ?? 0);
        $draft = $this->draftModel->findById($id);
        if (!$draft) {
            Response::error('Draft não encontrado', 404);
        }
        if ($draft['status'] !== 'pending') {
            Response::error('Campanha não está pendente de aprovação', 422);
        }

        $accountRow = $this->accountModel->find($draft['account_key']);
        if (!$accountRow) {
            Response::error('Conta Meta não encontrada', 404);
        }
        $cfg     = $this->accountModel->toConfig($accountRow);
        $creator = new CampaignCreatorService($cfg['meta_ads'], $this->http);

        $payload   = $draft['payload'];
        $creatives = $draft['creatives'] ?? [];

        // Reconstrói temp files a partir do base64 salvo
        $tmpPaths = [];
        foreach ($creatives as $i => $creative) {
            $data    = base64_decode($creative['data_base64'] ?? '');
            $tmpPath = tempnam(sys_get_temp_dir(), 'meta_creative_');
            file_put_contents($tmpPath, $data);
            $tmpPaths[$i] = $tmpPath;
        }

        try {
            $campaignId = $creator->createCampaign(
                $payload['campaign_name'],
                $payload['objective'],
                $payload['campaign_status'] ?? 'PAUSED'
            );

            $adSetId = $creator->createAdSet($campaignId, [
                'campaign_name'        => $payload['campaign_name'],
                'objective'            => $payload['objective'],
                'campaign_status'      => $payload['campaign_status'] ?? 'PAUSED',
                'countries'            => $payload['countries'] ?? ['BR'],
                'locales'              => $payload['locales'] ?? [],
                'daily_budget_cents'   => (int) round((float) ($payload['daily_budget'] ?? 10) * 100),
                'start_time'           => $payload['start_time'] ?? date('Y-m-d'),
                'pixel_id'             => $payload['pixel_id'] ?? '',
                'pixel_event'          => $payload['pixel_event'] ?? '',
                'age_min'              => (int) ($payload['age_min'] ?? 18),
                'age_max'              => (int) ($payload['age_max'] ?? 65),
                'advantage_audience'   => (int) ($payload['advantage_audience'] ?? 1),
                'custom_event_str'     => $payload['custom_event_str'] ?? '',
                'custom_conversion_id' => $payload['custom_conversion_id'] ?? '',
                'publisher_platforms'  => $payload['publisher_platforms'] ?? ['facebook', 'instagram'],
                'facebook_positions'   => $payload['facebook_positions'] ?? ['feed'],
                'instagram_positions'  => $payload['instagram_positions'] ?? ['stream'],
                'messenger_positions'  => $payload['messenger_positions'] ?? [],
            ]);

            $createdAds = [];
            $ads        = $payload['ads'] ?? [];

            foreach ($ads as $i => $ad) {
                $tmpPath   = $tmpPaths[$i] ?? null;
                $mediaType = $creatives[$i]['media_type'] ?? 'image';

                $creative = [
                    'name'              => $ad['name'] ?? ('Anúncio ' . ($i + 1)),
                    'page_id'           => $payload['page_id'] ?? '',
                    'primary_text'      => $ad['primary_text'] ?? '',
                    'headline'          => $ad['headline'] ?? '',
                    'cta'               => $ad['cta'] ?? 'LEARN_MORE',
                    'destination_url'   => $ad['destination_url'] ?? $payload['destination_url'] ?? '',
                    'instagram_user_id' => $payload['instagram_user_id'] ?? '',
                    'link_description'  => $ad['link_description'] ?? '',
                    'url_tags'          => $ad['url_tags'] ?? '',
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

                $adId = $creator->createAd($adSetId, $creativeId, $creative['name'], $payload['campaign_status'] ?? 'PAUSED');
                $createdAds[] = [
                    'ad_name'     => $creative['name'],
                    'ad_id'       => $adId,
                    'creative_id' => $creativeId,
                ];
            }

            $this->draftModel->markApproved($id, $ctx['email'] ?? 'admin', $campaignId, $adSetId, $createdAds);
            Response::json(['status' => 'success', 'campaign_id' => $campaignId, 'adset_id' => $adSetId, 'ads' => $createdAds]);

        } catch (\Throwable $e) {
            Response::error('Erro ao criar campanha no Meta: ' . $e->getMessage(), 500);
        } finally {
            foreach ($tmpPaths as $tmp) {
                if (is_file($tmp)) {
                    unlink($tmp);
                }
            }
        }
    }

    public function reject(Request $request, array $params): never
    {
        $ctx = $this->auth->resolveContext($request);
        if ($ctx['type'] !== 'admin') {
            Response::error('Acesso restrito ao administrador', 403);
        }

        $id   = (int) ($params['id'] ?? 0);
        $data = json_decode(file_get_contents('php://input'), true) ?? [];

        $reason         = trim($data['reason'] ?? '');
        $rejectedFields = (array) ($data['rejected_fields'] ?? []);

        if ($reason === '') {
            Response::error('Motivo da rejeição é obrigatório', 422);
        }

        $draft = $this->draftModel->findById($id);
        if (!$draft) {
            Response::error('Draft não encontrado', 404);
        }

        $this->draftModel->markRejected($id, $ctx['email'] ?? 'admin', $reason, $rejectedFields);
        Response::json(['status' => 'success']);
    }

    public function pendingCount(Request $request, array $params): never
    {
        $ctx = $this->auth->resolveContext($request);
        if ($ctx['type'] !== 'admin') {
            Response::error('Acesso restrito ao administrador', 403);
        }
        Response::json(['count' => $this->draftModel->countPending()]);
    }
}
