<?php

declare(strict_types=1);

namespace App\Services\MetaAds;

use App\Core\Cache\RedisCache;
use App\Core\HttpClient\HttpClient;

class InsightService
{
    private const CACHE_TTL = 3600;
    private const FIELDS    = 'campaign_id,campaign_name,impressions,clicks,spend,reach,cpc,cpm,ctr,actions,action_values';

    private string $baseUrl;
    private string $accessToken;
    private string $accountId;
    private int    $timeout;

    public function __construct(
        array                       $config,
        private readonly HttpClient $http,
        private readonly RedisCache $cache
    ) {
        $version           = $config['api_version'] ?? 'v22.0';
        $this->baseUrl     = "https://graph.facebook.com/{$version}";
        $this->accessToken = $config['access_token'];
        $this->accountId   = ltrim($config['account_id'], 'act_');
        $this->timeout     = (int) ($config['timeout'] ?? 60);
    }

    /**
     * Retorna insights indexados por [campaign_id][date].
     */
    public function fetchInsights(string $startDate, string $endDate): array
    {
        $cacheKey = "meta:insights:{$this->accountId}:{$startDate}:{$endDate}";
        $cached   = $this->cache->get($cacheKey);

        if ($cached !== null) {
            return $cached;
        }

        $url    = "{$this->baseUrl}/act_{$this->accountId}/insights";
        $params = [
            'fields'       => self::FIELDS,
            'level'        => 'campaign',
            'time_increment' => 1,
            'time_range'   => json_encode(['since' => $startDate, 'until' => $endDate]),
            'limit'        => 500,
            'access_token' => $this->accessToken,
        ];

        $indexed  = [];
        $response = $this->http->get($url, [], $params, $this->timeout);

        do {
            foreach ($response['data'] ?? [] as $row) {
                $cid  = $row['campaign_id'] ?? null;
                $date = $row['date_start']  ?? null;

                if ($cid === null || $date === null) {
                    continue;
                }

                $indexed[$cid][$date] = $this->parseRow($row);
            }

            $nextUrl = $response['paging']['next'] ?? null;
            if ($nextUrl) {
                $response = $this->http->get($nextUrl, [], [], $this->timeout);
            }
        } while ($nextUrl);

        $this->cache->set($cacheKey, $indexed, self::CACHE_TTL);

        return $indexed;
    }

    private function parseRow(array $row): array
    {
        $spend  = (float) ($row['spend']       ?? 0);
        $imp    = (int)   ($row['impressions'] ?? 0);
        $clicks = (int)   ($row['clicks']      ?? 0);

        return [
            'campaign_id'   => $row['campaign_id']   ?? '',
            'campaign_name' => $row['campaign_name'] ?? '',
            'impressions'   => $imp,
            'clicks'        => $clicks,
            'reach'         => (int) ($row['reach'] ?? 0),
            'spend_usd'     => round($spend, 4),
            'ctr'           => $imp > 0 ? round($clicks / $imp * 100, 4) : 0.0,
            'cpc_usd'       => (float) ($row['cpc'] ?? 0),
            'cpm_usd'       => (float) ($row['cpm'] ?? 0),
            'status'        => '',
        ];
    }
}
