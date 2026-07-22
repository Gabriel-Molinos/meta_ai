<?php

declare(strict_types=1);

namespace App\Services\ActiveView;

use App\Core\Cache\RedisCache;
use App\Core\HttpClient\HttpClient;

class RevenueService
{
    private const CACHE_TTL        = 3600;
    private const REVENUE_DIVISOR  = 1_000_000;

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

    public function fetch(string $startDate, string $endDate): array
    {
        $cacheKey = "av:revenue:{$this->domain}:{$startDate}:{$endDate}";
        $cached   = $this->cache->get($cacheKey);

        if ($cached !== null) {
            return $cached;
        }

        $url     = "{$this->baseUrl}/report/kvp/{$this->networkCode}/{$this->domain}";
        $headers = ['Authorization' => "Bearer {$this->apiKey}"];
        $params  = [
            'start_date'            => $startDate,
            'end_date'              => $endDate,
            'key'                   => 'utm_campaign',
            'additional_dimensions' => 'date',
        ];

        $response = $this->http->get($url, $headers, $params, $this->timeout);
        $indexed  = $this->indexByUtmCampaign($response);

        $this->cache->set($cacheKey, $indexed, self::CACHE_TTL);

        return $indexed;
    }

    private function indexByUtmCampaign(array $response): array
    {
        $result = [];

        foreach ($response['response'] ?? [] as $row) {
            $key = (string) ($row['value'] ?? '');

            if ($key === '' || $key === 'null') {
                continue;
            }

            $result[$key][] = [
                'date'                 => $row['date'] ?? '',
                'revenue_usd'          => (float) ($row['ad_exchange_line_item_level_revenue']             ?? 0) / self::REVENUE_DIVISOR,
                'impressions'          => (int)   ($row['ad_exchange_line_item_level_impressions']          ?? 0),
                'clicks'               => (int)   ($row['ad_exchange_line_item_level_clicks']               ?? 0),
                'viewable_impressions' => (int)   ($row['ad_exchange_active_view_viewable_impressions']     ?? 0),
                'responses_served'     => (int)   ($row['ad_exchange_responses_served']                     ?? 0),
            ];
        }

        return $result;
    }
}
