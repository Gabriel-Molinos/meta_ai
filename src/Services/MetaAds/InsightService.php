<?php

declare(strict_types=1);

namespace App\Services\MetaAds;

use App\Core\Cache\RedisCache;
use App\Core\HttpClient\HttpClient;
use App\Services\Fx\ExchangeRateService;

class InsightService
{
    private const CACHE_TTL          = 3600;
    private const CURRENCY_CACHE_TTL = 86400;
    private const FIELDS             = 'campaign_id,campaign_name,impressions,clicks,spend,reach,cpc,cpm,ctr,actions,action_values';

    private string $baseUrl;
    private string $accessToken;
    private string $accountId;
    private int    $timeout;

    public function __construct(
        array                                $config,
        private readonly HttpClient          $http,
        private readonly RedisCache          $cache,
        private readonly ExchangeRateService $fx
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

        $currency = $this->resolveCurrency();

        $indexed  = [];
        $response = $this->http->get($url, [], $params, $this->timeout);

        do {
            foreach ($response['data'] ?? [] as $row) {
                $cid  = $row['campaign_id'] ?? null;
                $date = $row['date_start']  ?? null;

                if ($cid === null || $date === null) {
                    continue;
                }

                $fxRate = $currency === 'USD' ? 1.0 : $this->fx->getRate($currency, 'USD', $date);
                $indexed[$cid][$date] = $this->parseRow($row, $fxRate);
            }

            $nextUrl = $response['paging']['next'] ?? null;
            if ($nextUrl) {
                $response = $this->http->get($nextUrl, [], [], $this->timeout);
            }
        } while ($nextUrl);

        $this->cache->set($cacheKey, $indexed, self::CACHE_TTL);

        return $indexed;
    }

    /**
     * Moeda em que a conta de anúncio reporta gasto (ex: USD, EUR) — a API do
     * Meta sempre devolve `spend`/`cpc`/`cpm` na moeda nativa da conta, nunca em USD.
     */
    private function resolveCurrency(): string
    {
        $cacheKey = "meta:currency:{$this->accountId}";
        $cached   = $this->cache->get($cacheKey);

        if ($cached !== null) {
            return $cached;
        }

        $response = $this->http->get("{$this->baseUrl}/act_{$this->accountId}", [], [
            'access_token' => $this->accessToken,
            'fields'       => 'currency',
        ], $this->timeout);

        $currency = $response['currency'] ?? 'USD';
        $this->cache->set($cacheKey, $currency, self::CURRENCY_CACHE_TTL);

        return $currency;
    }

    private function parseRow(array $row, float $fxRate): array
    {
        $spend  = (float) ($row['spend']       ?? 0) * $fxRate;
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
            'cpc_usd'       => round((float) ($row['cpc'] ?? 0) * $fxRate, 4),
            'cpm_usd'       => round((float) ($row['cpm'] ?? 0) * $fxRate, 4),
            'status'        => '',
        ];
    }
}
