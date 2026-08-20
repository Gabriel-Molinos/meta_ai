<?php

declare(strict_types=1);

namespace App\Services\Fx;

use App\Core\Cache\RedisCache;
use App\Core\Exceptions\ApiException;
use App\Core\HttpClient\HttpClient;

class ExchangeRateService
{
    // Taxas de datas passadas nunca mudam — pode cachear por muito tempo.
    private const CACHE_TTL = 30 * 86400;

    public function __construct(
        private readonly HttpClient  $http,
        private readonly ?RedisCache $cache = null
    ) {}

    /**
     * Retorna a taxa de conversão de $from para $to na data $date (YYYY-MM-DD).
     * Usa o câmbio histórico do dia (ex: EUR->USD do dia do gasto), não o câmbio atual.
     */
    public function getRate(string $from, string $to, string $date): float
    {
        if ($from === $to) {
            return 1.0;
        }

        $cacheKey = "fx:{$from}:{$to}:{$date}";
        $cached   = $this->cache?->get($cacheKey);
        if ($cached !== null) {
            return (float) $cached;
        }

        $url      = "https://api.frankfurter.dev/v1/{$date}";
        $response = $this->http->get($url, [], ['from' => $from, 'to' => $to], 15);
        $rate     = (float) ($response['rates'][$to] ?? 0);

        if ($rate <= 0) {
            throw new ApiException("Taxa de câmbio {$from}->{$to} indisponível para {$date}");
        }

        $this->cache?->set($cacheKey, $rate, self::CACHE_TTL);

        return $rate;
    }
}
