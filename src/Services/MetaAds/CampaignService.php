<?php

declare(strict_types=1);

namespace App\Services\MetaAds;

use App\Core\Cache\RedisCache;
use App\Core\HttpClient\HttpClient;

class CampaignService
{
    private const CACHE_TTL = 3600;

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

    public function fetchCampaigns(): array
    {
        $cacheKey = "meta:campaigns:{$this->accountId}:" . date('Y-m-d');
        $cached   = $this->cache->get($cacheKey);

        if ($cached !== null) {
            return $cached;
        }

        $url    = "{$this->baseUrl}/act_{$this->accountId}/campaigns";
        $params = [
            'fields'       => 'id,name,status,objective',
            'limit'        => 500,
            'access_token' => $this->accessToken,
        ];

        $campaigns = [];
        $response  = $this->http->get($url, [], $params, $this->timeout);

        do {
            foreach ($response['data'] ?? [] as $campaign) {
                $campaigns[$campaign['id']] = [
                    'campaign_id'   => $campaign['id'],
                    'campaign_name' => $campaign['name'],
                    'status'        => $campaign['status'] ?? '',
                    'objective'     => $campaign['objective'] ?? '',
                ];
            }

            $nextUrl = $response['paging']['next'] ?? null;
            if ($nextUrl) {
                $response = $this->http->get($nextUrl, [], [], $this->timeout);
            }
        } while ($nextUrl);

        $this->cache->set($cacheKey, $campaigns, self::CACHE_TTL);

        return $campaigns;
    }
}
