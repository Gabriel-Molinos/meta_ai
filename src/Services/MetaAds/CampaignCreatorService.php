<?php

declare(strict_types=1);

namespace App\Services\MetaAds;

use App\Core\HttpClient\HttpClient;
use RuntimeException;

class CampaignCreatorService
{
    private const EVENT_NAME_MAP = [
        'Purchase'             => 'PURCHASE',
        'Lead'                 => 'LEAD',
        'ViewContent'          => 'CONTENT_VIEW',
        'AddToCart'            => 'ADD_TO_CART',
        'AddToWishlist'        => 'ADD_TO_WISHLIST',
        'InitiateCheckout'     => 'INITIATED_CHECKOUT',
        'AddPaymentInfo'       => 'ADD_PAYMENT_INFO',
        'CompleteRegistration' => 'COMPLETE_REGISTRATION',
        'Search'               => 'SEARCH',
        'Contact'              => 'CONTACT',
        'CustomizeProduct'     => 'CUSTOMIZE_PRODUCT',
        'Donate'               => 'DONATE',
        'FindLocation'         => 'FIND_LOCATION',
        'Schedule'             => 'SCHEDULE',
        'StartTrial'           => 'START_TRIAL',
        'SubmitApplication'    => 'SUBMIT_APPLICATION',
        'Subscribe'            => 'SUBSCRIBE',
    ];

    private const OPTIMIZATION_GOALS = [
        'OUTCOME_SALES'         => 'OFFSITE_CONVERSIONS',
        'OUTCOME_LEADS'         => 'OFFSITE_CONVERSIONS',
        'OUTCOME_TRAFFIC'       => 'LINK_CLICKS',
        'OUTCOME_AWARENESS'     => 'REACH',
        'OUTCOME_ENGAGEMENT'    => 'POST_ENGAGEMENT',
        'OUTCOME_APP_PROMOTION' => 'APP_INSTALLS',
    ];

    private string $baseUrl;
    private string $accessToken;
    private string $accountId;

    public function __construct(array $config, private readonly HttpClient $http)
    {
        $version           = $config['api_version'] ?? 'v22.0';
        $this->baseUrl     = "https://graph.facebook.com/{$version}";
        $this->accessToken = $config['access_token'];
        $this->accountId   = ltrim($config['account_id'], 'act_');
    }

    private function extractId(array $response, string $context): string
    {
        if (isset($response['id'])) {
            return (string) $response['id'];
        }
        $apiMsg = $response['error']['message'] ?? $response['error']['error_user_msg'] ?? null;
        $detail = $apiMsg ? " — {$apiMsg}" : (' — ' . json_encode($response));
        throw new RuntimeException("{$context} failed{$detail}");
    }

    public function createCampaign(string $name, string $objective, string $status = 'PAUSED'): string
    {
        $response = $this->http->postMultipart(
            "{$this->baseUrl}/act_{$this->accountId}/campaigns",
            [],
            [
                'name'                            => $name,
                'objective'                       => $objective,
                'status'                          => $status,
                'special_ad_categories'           => '[]',
                'is_adset_budget_sharing_enabled' => '0',
                'access_token'                    => $this->accessToken,
            ],
            30
        );
        return $this->extractId($response, 'Campaign creation');
    }

    public function createAdSet(string $campaignId, array $p): string
    {
        $objective        = $p['objective'] ?? 'OUTCOME_SALES';
        $optimizationGoal = self::OPTIMIZATION_GOALS[$objective] ?? 'OFFSITE_CONVERSIONS';

        $targeting = [
            'geo_locations' => ['countries' => $p['countries'] ?? ['BR']],
        ];
        $targeting['age_min'] = (int) ($p['age_min'] ?? 18);
        $targeting['age_max'] = (int) ($p['age_max'] ?? 65);
        if ((int)($p['advantage_audience'] ?? 1) === 1) {
            $targeting['targeting_automation'] = [
                'advantage_audience' => 1,
                'individual_setting' => ['age' => 1, 'gender' => 1],
            ];
        }
        if (!empty($p['locales'])) {
            $targeting['locales'] = array_map('intval', (array) $p['locales']);
        }

        // Placements — audience_network e messenger bloqueados no servidor independente do que vier do frontend
        $platforms = array_values(array_filter(
            (array) ($p['publisher_platforms'] ?? ['facebook', 'instagram']),
            fn($pl) => $pl !== 'audience_network' && $pl !== 'messenger'
        ));
        $targeting['publisher_platforms'] = $platforms ?: ['facebook', 'instagram'];
        if (!empty($p['facebook_positions']) && in_array('facebook', $targeting['publisher_platforms'])) {
            // video_feeds descontinuado na API v22.0 (error_subcode 2490562) — bloqueado no servidor
            $fbPositions = array_values(array_filter(
                (array) $p['facebook_positions'],
                fn($pos) => $pos !== 'video_feeds'
            ));
            if ($fbPositions) {
                $targeting['facebook_positions'] = $fbPositions;
            }
        }
        if (!empty($p['instagram_positions']) && in_array('instagram', $targeting['publisher_platforms'])) {
            $targeting['instagram_positions'] = array_values((array) $p['instagram_positions']);
        }

        $fields = [
            'name'              => ($p['campaign_name'] ?? 'Ad Set') . ' — Conjunto',
            'campaign_id'       => $campaignId,
            'status'            => $p['campaign_status'] ?? 'PAUSED',
            'daily_budget'      => (string) (int) ($p['daily_budget_cents'] ?? 1000),
            'billing_event'     => 'IMPRESSIONS',
            'optimization_goal' => $optimizationGoal,
            'bid_strategy'      => 'LOWEST_COST_WITHOUT_CAP',
            'targeting'         => json_encode($targeting),
            'start_time'        => $p['start_time'] ?? date('Y-m-d'),
            'access_token'      => $this->accessToken,
        ];

        if (!empty($p['is_dynamic_creative'])) {
            $fields['is_dynamic_creative'] = 'true';
        }

        if (!empty($p['custom_conversion_id'])) {
            $promoted = ['custom_conversion_id' => $p['custom_conversion_id']];
            if (!empty($p['pixel_id'])) {
                $promoted['pixel_id'] = $p['pixel_id'];
            }
            $fields['promoted_object'] = json_encode($promoted);
        } elseif (!empty($p['pixel_id']) && !empty($p['pixel_event'])) {
            $pixelEvent = $p['pixel_event'];
            if (isset(self::EVENT_NAME_MAP[$pixelEvent])) {
                $eventType = self::EVENT_NAME_MAP[$pixelEvent];
            } elseif ($pixelEvent === 'OTHER') {
                $eventType = 'OTHER';
            } else {
                // Evento customizado do pixel — Meta exige custom_event_type=OTHER + custom_event_str
                $eventType = 'OTHER';
                if (empty($p['custom_event_str'])) {
                    $p['custom_event_str'] = $pixelEvent;
                }
            }
            $promoted = ['pixel_id' => $p['pixel_id'], 'custom_event_type' => $eventType];
            if ($eventType === 'OTHER' && !empty($p['custom_event_str'])) {
                $promoted['custom_event_str'] = $p['custom_event_str'];
            }
            $fields['promoted_object'] = json_encode($promoted);
        }

        $response = $this->http->postMultipart(
            "{$this->baseUrl}/act_{$this->accountId}/adsets",
            [],
            $fields,
            30
        );
        return $this->extractId($response, 'Ad set creation');
    }

    public function uploadImage(string $tmpPath): string
    {
        $response = $this->http->postFile(
            "{$this->baseUrl}/act_{$this->accountId}/adimages",
            ['access_token' => $this->accessToken],
            ['source' => $tmpPath],
            60
        );
        if (isset($response['error'])) {
            $msg = $response['error']['message'] ?? json_encode($response['error']);
            throw new RuntimeException("Image upload failed — {$msg}");
        }
        foreach ($response['images'] ?? [] as $img) {
            return $img['hash'] ?? throw new RuntimeException('Image upload returned no hash');
        }
        throw new RuntimeException('Image upload failed: empty response — ' . json_encode($response));
    }

    private function uploadImageFromUrl(string $url): string
    {
        $bytes = $this->http->getRaw($url, [], 30);
        if ($bytes === '') {
            throw new RuntimeException("Falha ao baixar imagem de capa a partir de {$url}");
        }
        $tmpPath = tempnam(sys_get_temp_dir(), 'meta_cover_');
        file_put_contents($tmpPath, $bytes);
        try {
            return $this->uploadImage($tmpPath);
        } finally {
            if (is_file($tmpPath)) {
                unlink($tmpPath);
            }
        }
    }

    public function uploadVideo(string $tmpPath, string $title): array
    {
        $videoId = $this->uploadVideoOnly($tmpPath, $title);
        return $this->waitForVideoReady($videoId);
    }

    /**
     * Sobe vários vídeos e só então espera todos ficarem prontos, em round-robin.
     * O Meta processa cada vídeo em paralelo do lado dele — subir e esperar um de cada vez
     * (como uploadVideo() faz sozinho) soma os tempos de espera; aqui a espera total tende
     * ao tempo do vídeo mais lento, não à soma de todos.
     */
    public function uploadVideosAndWait(array $tmpPaths, string $title): array
    {
        $videoIds = [];
        foreach ($tmpPaths as $tmpPath) {
            $videoIds[] = $this->uploadVideoOnly($tmpPath, $title);
        }
        return $this->waitForVideosReady($videoIds);
    }

    private function uploadVideoOnly(string $tmpPath, string $title): string
    {
        $response = $this->http->postFile(
            "{$this->baseUrl}/act_{$this->accountId}/advideos",
            ['access_token' => $this->accessToken, 'title' => $title],
            ['source' => $tmpPath],
            300
        );
        return $response['id'] ?? throw new RuntimeException('Video upload failed: no id returned');
    }

    private function waitForVideoReady(string $videoId): array
    {
        return $this->waitForVideosReady([$videoId])[0];
    }

    /**
     * @param string[] $videoIds
     * @return array<int, array{id:string, thumbnail_url:?string}> na mesma ordem de $videoIds
     */
    private function waitForVideosReady(array $videoIds): array
    {
        // O upload em si termina rápido, mas o Meta processa o vídeo de forma assíncrona.
        // Enquanto isso, o campo "picture" já retorna um GIF placeholder genérico e não-vazio
        // (ex.: .../AAqMW82PqGg.gif) — não serve como sinal de prontidão. O sinal real é
        // status.video_status === 'ready' (usar o vídeo antes disso causa error_subcode 1885252).
        $thumbnails = array_fill_keys($videoIds, null);
        $pending    = array_flip($videoIds); // set de video_ids ainda não prontos

        for ($attempt = 0; $attempt < 40 && $pending; $attempt++) {
            if ($attempt > 0) {
                sleep(3);
            }
            foreach (array_keys($pending) as $videoId) {
                $meta = $this->http->get(
                    "{$this->baseUrl}/{$videoId}",
                    [],
                    ['fields' => 'picture,status', 'access_token' => $this->accessToken],
                    30
                );
                if (!empty($meta['picture'])) {
                    $thumbnails[$videoId] = $meta['picture'];
                }
                $videoStatus = $meta['status']['video_status'] ?? null;
                if ($videoStatus === 'ready') {
                    unset($pending[$videoId]);
                } elseif ($videoStatus === 'error') {
                    $reason = $meta['status']['processing_phase']['status']['error']['message'] ?? 'motivo não informado pelo Meta';
                    throw new RuntimeException("Falha no processamento do vídeo {$videoId} no Meta — {$reason}");
                }
            }
        }

        if ($pending) {
            $ids = implode(', ', array_keys($pending));
            throw new RuntimeException("Vídeo(s) {$ids} ainda em processamento no Meta após ~2min de espera — tente novamente em alguns instantes");
        }

        return array_map(fn($id) => ['id' => $id, 'thumbnail_url' => $thumbnails[$id]], $videoIds);
    }

    public function createImageCreative(array $p): string
    {
        $response = $this->http->postMultipart(
            "{$this->baseUrl}/act_{$this->accountId}/adcreatives",
            [],
            [
                'name'              => ($p['name'] ?? 'Creative') . ' — Creative',
                'object_story_spec' => json_encode([
                    'page_id'   => $p['page_id'],
                    ...(!empty($p['instagram_user_id']) ? ['instagram_user_id' => $p['instagram_user_id']] : []),
                    'link_data' => [
                        'image_hash'     => $p['image_hash'],
                        'link'           => $p['destination_url'],
                        'message'        => $p['primary_text'],
                        'name'           => $p['headline'],
                        'description'    => $p['link_description'] ?? '',
                        'call_to_action' => [
                            'type'  => $p['cta'] ?? 'LEARN_MORE',
                            'value' => ['link' => $p['destination_url']],
                        ],
                    ],
                ]),
                ...(!empty($p['url_tags']) ? ['url_tags' => $p['url_tags']] : []),
                'access_token' => $this->accessToken,
            ],
            30
        );
        return $this->extractId($response, 'Image creative creation');
    }

    public function createVideoCreative(array $p): string
    {
        $response = $this->http->postMultipart(
            "{$this->baseUrl}/act_{$this->accountId}/adcreatives",
            [],
            [
                'name'              => ($p['name'] ?? 'Creative') . ' — Creative',
                'object_story_spec' => json_encode([
                    'page_id'    => $p['page_id'],
                    ...(!empty($p['instagram_user_id']) ? ['instagram_user_id' => $p['instagram_user_id']] : []),
                    'video_data' => array_filter([
                        'video_id'         => $p['video_id'],
                        'image_url'        => $p['thumbnail_url'] ?? null,
                        'message'          => $p['primary_text'],
                        'title'            => $p['headline'],
                        'link_description' => $p['link_description'] ?? '',
                        'call_to_action'   => [
                            'type'  => $p['cta'] ?? 'LEARN_MORE',
                            'value' => ['link' => $p['destination_url']],
                        ],
                    ], fn($v) => $v !== null),
                ]),
                ...(!empty($p['url_tags']) ? ['url_tags' => $p['url_tags']] : []),
                'access_token' => $this->accessToken,
            ],
            30
        );
        return $this->extractId($response, 'Video creative creation');
    }

    public function createCarouselCreative(array $p): string
    {
        // Cada card de vídeo do carrossel exige a própria imagem de capa (image_hash),
        // além da capa no nível do link_data — só o video_id não basta (error_subcode 1443052).
        $hashCache = [];
        $childAttachments = [];
        foreach ($p['videos'] as $i => $v) {
            if (empty($v['thumbnail_url'])) {
                throw new RuntimeException(
                    'Thumbnail do vídeo ' . ($i + 1) . ' do carrossel ainda não está pronta no Meta — tente novamente em alguns instantes'
                );
            }
            $url = $v['thumbnail_url'];
            $hashCache[$url] ??= $this->uploadImageFromUrl($url);

            $childAttachments[] = array_filter([
                'video_id'       => $v['id'],
                'image_hash'     => $hashCache[$url],
                'link'           => $p['destination_url'],
                'name'           => $p['headline'] ?: null,
                'description'    => $p['link_description'] ?: null,
                'call_to_action' => [
                    'type'  => $p['cta'] ?? 'LEARN_MORE',
                    'value' => ['link' => $p['destination_url']],
                ],
            ], fn($val) => $val !== null);
        }

        // Sem image_hash no nível do link_data: cada card já tem o seu, e a capa do topo
        // era contada pelo Meta como card extra (error_subcode 2446581 — divergência de contagem).
        $objectStorySpec = json_encode(array_filter([
            'page_id'           => $p['page_id'],
            'instagram_user_id' => $p['instagram_user_id'] ?: null,
            'link_data'         => array_filter([
                'link'                 => $p['destination_url'],
                'message'              => $p['primary_text'],
                'multi_share_end_card' => false,
                'child_attachments'    => $childAttachments,
            ], fn($v) => $v !== null),
        ], fn($v) => $v !== null));

        try {
            $response = $this->http->postMultipart(
                "{$this->baseUrl}/act_{$this->accountId}/adcreatives",
                [],
                [
                    'name'              => ($p['name'] ?? 'Creative') . ' — Creative',
                    'object_story_spec' => $objectStorySpec,
                    ...(!empty($p['url_tags']) ? ['url_tags' => $p['url_tags']] : []),
                    'access_token' => $this->accessToken,
                ],
                30
            );
        } catch (\Throwable $e) {
            // Expõe o payload enviado — sem ele o erro do Meta não diz qual campo faltou onde
            throw new RuntimeException(
                $e->getMessage() . ' — object_story_spec enviado: ' . $objectStorySpec,
                0,
                $e
            );
        }
        return $this->extractId($response, 'Carousel creative creation');
    }

    public function createDynamicCreative(array $p): string
    {
        $videoAssets = array_map(fn($v) => array_filter([
            'video_id'      => $v['id'],
            'thumbnail_url' => $v['thumbnail_url'] ?? null,
        ], fn($x) => $x !== null), $p['videos']);

        $assetFeedSpec = array_filter([
            'videos'              => $videoAssets,
            'bodies'              => [['text' => $p['primary_text']]],
            'titles'              => [['text' => $p['headline']]],
            'descriptions'        => !empty($p['link_description']) ? [['text' => $p['link_description']]] : null,
            'link_urls'           => [['website_url' => $p['destination_url']]],
            'call_to_action_types'=> [$p['cta'] ?? 'LEARN_MORE'],
            'ad_formats'          => ['SINGLE_VIDEO'],
        ], fn($v) => $v !== null);

        $objectStorySpec = json_encode(array_filter([
            'page_id'           => $p['page_id'],
            'instagram_user_id' => $p['instagram_user_id'] ?: null,
        ], fn($v) => $v !== null));
        $assetFeedSpecJson = json_encode($assetFeedSpec);

        try {
            $response = $this->http->postMultipart(
                "{$this->baseUrl}/act_{$this->accountId}/adcreatives",
                [],
                [
                    'name'              => ($p['name'] ?? 'Creative') . ' — Creative',
                    'object_story_spec' => $objectStorySpec,
                    'asset_feed_spec'   => $assetFeedSpecJson,
                    ...(!empty($p['url_tags']) ? ['url_tags' => $p['url_tags']] : []),
                    'access_token' => $this->accessToken,
                ],
                30
            );
        } catch (\Throwable $e) {
            // Expõe o payload enviado — sem ele o erro do Meta não diz qual campo faltou onde
            throw new RuntimeException(
                $e->getMessage() . ' — asset_feed_spec enviado: ' . $assetFeedSpecJson,
                0,
                $e
            );
        }
        return $this->extractId($response, 'Dynamic creative creation');
    }

    public function createAd(string $adSetId, string $creativeId, string $name, string $status = 'PAUSED'): string
    {
        $response = $this->http->postMultipart(
            "{$this->baseUrl}/act_{$this->accountId}/ads",
            [],
            [
                'name'         => $name,
                'adset_id'     => $adSetId,
                'creative'     => json_encode(['creative_id' => $creativeId]),
                'status'       => $status,
                'access_token' => $this->accessToken,
            ],
            30
        );
        return $this->extractId($response, 'Ad creation');
    }
}
