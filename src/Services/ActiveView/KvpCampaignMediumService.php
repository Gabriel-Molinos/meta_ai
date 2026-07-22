<?php

declare(strict_types=1);

namespace App\Services\ActiveView;

use App\Core\Cache\RedisCache;
use App\Core\HttpClient\HttpClient;

class KvpCampaignMediumService
{
    private const CACHE_TTL       = 3600;
    private const REVENUE_DIVISOR = 1_000_000;

    private string $baseUrl;
    private string $apiKey;
    private string $networkCode;
    private string $domain;
    private int    $timeout;

    public function __construct(
        array                       $config,
        private readonly HttpClient $http,
        private readonly RedisCache $cache
    ) {
        $this->baseUrl     = rtrim($config['base_url'], '/');
        $this->apiKey      = $config['api_key'];
        $this->networkCode = $config['network_code'];
        $this->domain      = $config['domain'];
        $this->timeout     = (int) $config['timeout'];
    }

    public function fetchDay(string $date): array
    {
        $cacheKey = "av:kvp-medium:{$this->domain}:{$date}";
        $cached   = $this->cache->get($cacheKey);

        if ($cached !== null) {
            return $cached;
        }

        $url     = "{$this->baseUrl}/report/kvp/{$this->networkCode}/{$this->domain}";
        $headers = ['Authorization' => "Bearer {$this->apiKey}"];
        $params  = [
            'start_date' => $date,
            'end_date'   => $date,
            'key'        => 'utm_campaign_medium',
        ];

        $response = $this->http->get($url, $headers, $params, $this->timeout);
        $records  = $this->parseRecords($response);

        $this->cache->set($cacheKey, $records, self::CACHE_TTL);

        return $records;
    }

    private function parseRecords(array $response): array
    {
        $records = [];

        foreach ($response['response'] ?? [] as $row) {
            $medium = (string) ($row['value'] ?? '');

            if ($medium === '' || $medium === 'null') {
                continue;
            }

            $records[] = [
                'utm_campaign_medium'    => $medium,
                'revenue_usd'            => round((float) ($row['ad_exchange_line_item_level_revenue']           ?? 0) / self::REVENUE_DIVISOR, 6),
                'impressions'            => (int) ($row['ad_exchange_line_item_level_impressions']               ?? 0),
                'clicks'                 => (int) ($row['ad_exchange_line_item_level_clicks']                    ?? 0),
                'ctr'                    => (float) ($row['ad_exchange_line_item_level_ctr']                     ?? 0),
                'viewable_impressions'   => (int) ($row['ad_exchange_active_view_viewable_impressions']          ?? 0),
                'measurable_impressions' => (int) ($row['ad_exchange_active_view_measurable_impressions']        ?? 0),
                'responses_served'       => (int) ($row['ad_exchange_responses_served']                         ?? 0),
            ];
        }

        return $records;
    }
}
