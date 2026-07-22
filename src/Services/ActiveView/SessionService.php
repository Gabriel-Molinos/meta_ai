<?php

declare(strict_types=1);

namespace App\Services\ActiveView;

use App\Core\Cache\RedisCache;
use App\Core\HttpClient\HttpClient;

class SessionService
{
    private const CACHE_TTL = 3600;

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
        $cacheKey = "av:sessions:{$startDate}:{$endDate}";
        $cached   = $this->cache->get($cacheKey);

        if ($cached !== null) {
            return $cached;
        }

        $url     = "{$this->baseUrl}/report/session/kvp/{$this->networkCode}/{$this->domain}";
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
        $aggregated = [];

        foreach ($response['response'] ?? [] as $row) {
            $key  = $row['VALUE'] ?? null;
            $date = $row['RECORDED_DATE'] ?? '';

            if ($key === null || $key === '') {
                continue;
            }

            $aggregated[$key][$date] = ($aggregated[$key][$date] ?? 0) + (int) ($row['TOTAL'] ?? 0);
        }

        $result = [];
        foreach ($aggregated as $campaignId => $dates) {
            foreach ($dates as $date => $total) {
                $result[(string) $campaignId][] = [
                    'date'     => $date,
                    'sessions' => $total,
                ];
            }
        }

        return $result;
    }
}
