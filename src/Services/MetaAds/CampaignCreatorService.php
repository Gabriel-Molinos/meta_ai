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
        'OUTCOME_LEADS'         => 'LEAD_GENERATION',
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

        // Placements — audience_network bloqueado no servidor independente do que vier do frontend
        $platforms = array_values(array_filter(
            (array) ($p['publisher_platforms'] ?? ['facebook', 'instagram']),
            fn($pl) => $pl !== 'audience_network'
        ));
        $targeting['publisher_platforms'] = $platforms ?: ['facebook', 'instagram'];
        if (!empty($p['facebook_positions']) && in_array('facebook', $targeting['publisher_platforms'])) {
            $targeting['facebook_positions'] = array_values((array) $p['facebook_positions']);
        }
        if (!empty($p['instagram_positions']) && in_array('instagram', $targeting['publisher_platforms'])) {
            $targeting['instagram_positions'] = array_values((array) $p['instagram_positions']);
        }
        if (!empty($p['messenger_positions']) && in_array('messenger', $targeting['publisher_platforms'])) {
            $targeting['messenger_positions'] = array_values((array) $p['messenger_positions']);
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

    public function uploadVideo(string $tmpPath, string $title): array
    {
        $response = $this->http->postFile(
            "{$this->baseUrl}/act_{$this->accountId}/advideos",
            ['access_token' => $this->accessToken, 'title' => $title],
            ['source' => $tmpPath],
            300
        );
        $videoId = $response['id'] ?? throw new RuntimeException('Video upload failed: no id returned');

        // Busca thumbnail gerada pelo Meta após o upload
        $meta = $this->http->get(
            "{$this->baseUrl}/{$videoId}",
            [],
            ['fields' => 'picture', 'access_token' => $this->accessToken],
            30
        );

        return ['id' => $videoId, 'thumbnail_url' => $meta['picture'] ?? null];
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
