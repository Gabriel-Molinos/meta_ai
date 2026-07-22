<?php

declare(strict_types=1);

namespace App\Services\ActiveView;

use App\Core\HttpClient\HttpClient;

class GamCustomReportService
{
    private const REVENUE_DIVISOR = 1_000_000;

    private string $baseUrl;
    private string $apiKey;
    private string $networkCode;
    private string $domain;
    private int    $timeout;

    public function __construct(array $config, private readonly HttpClient $http)
    {
        $this->baseUrl     = rtrim($config['base_url'], '/');
        $this->apiKey      = $config['api_key'];
        $this->networkCode = $config['network_code'];
        $this->domain      = $config['domain'];
        $this->timeout     = (int) $config['timeout'];
    }

    public function fetchAll(string $startDate, string $endDate): array
    {
        $url     = "{$this->baseUrl}/report/gam/custom/{$this->networkCode}/{$this->domain}/from-gam";
        $headers = ['Authorization' => "Bearer {$this->apiKey}"];
        $params  = [
            'start_date' => $startDate,
            'end_date'   => $endDate,
            'key'        => 'utm_campaign',
            'dimensions' => 'DOMAIN,CUSTOM_CRITERIA,AD_UNIT_NAME',
            'metrics'    => implode(',', [
                'AD_EXCHANGE_LINE_ITEM_LEVEL_REVENUE',
                'AD_EXCHANGE_LINE_ITEM_LEVEL_IMPRESSIONS',
                'AD_EXCHANGE_LINE_ITEM_LEVEL_CLICKS',
                'AD_EXCHANGE_RESPONSES_SERVED',
                'AD_EXCHANGE_ACTIVE_VIEW_VIEWABLE_IMPRESSIONS',
                'PROGRAMMATIC_MATCH_RATE',
            ]),
        ];

        $response = $this->http->get($url, $headers, $params, $this->timeout);
        $rows     = $response['response'] ?? [];

        $byCampaign = [];
        $byAdUnit   = [];

        foreach ($rows as $row) {
            $criteria = $row['CUSTOM_CRITERIA'] ?? '';
            if (!str_starts_with($criteria, 'utm_campaign=')) continue;
            $cid = substr($criteria, strlen('utm_campaign='));
            if ($cid === '' || $cid === 'null') continue;

            $unit    = $row['AD_UNIT_NAME'] ?? 'unknown';
            $revenue = ($row['AD_EXCHANGE_LINE_ITEM_LEVEL_REVENUE'] ?? 0) / self::REVENUE_DIVISOR;
            $imp     = (int) ($row['AD_EXCHANGE_LINE_ITEM_LEVEL_IMPRESSIONS'] ?? 0);
            $clicks  = (int) ($row['AD_EXCHANGE_LINE_ITEM_LEVEL_CLICKS'] ?? 0);
            $req     = (int) ($row['AD_EXCHANGE_RESPONSES_SERVED'] ?? 0);
            $view    = (int) ($row['AD_EXCHANGE_ACTIVE_VIEW_VIEWABLE_IMPRESSIONS'] ?? 0);
            $matchRaw = (float) ($row['PROGRAMMATIC_MATCH_RATE'] ?? 0) * 100;

            if (!isset($byCampaign[$cid])) {
                $byCampaign[$cid] = ['revenue_usd' => 0.0, 'impressions' => 0, 'clicks' => 0, 'ad_requests' => 0, 'viewable_imp' => 0, '_match_rate_w' => 0.0];
            }
            $byCampaign[$cid]['revenue_usd']   += $revenue;
            $byCampaign[$cid]['impressions']   += $imp;
            $byCampaign[$cid]['clicks']        += $clicks;
            $byCampaign[$cid]['ad_requests']   += $req;
            $byCampaign[$cid]['viewable_imp']  += $view;
            $byCampaign[$cid]['_match_rate_w'] += $matchRaw * $req;

            if (!isset($byAdUnit[$cid][$unit])) {
                $byAdUnit[$cid][$unit] = ['revenue_usd' => 0.0, 'impressions' => 0, 'clicks' => 0, 'ad_requests' => 0, 'viewable_imp' => 0, '_match_rate_w' => 0.0];
            }
            $byAdUnit[$cid][$unit]['revenue_usd']   += $revenue;
            $byAdUnit[$cid][$unit]['impressions']   += $imp;
            $byAdUnit[$cid][$unit]['clicks']        += $clicks;
            $byAdUnit[$cid][$unit]['ad_requests']   += $req;
            $byAdUnit[$cid][$unit]['viewable_imp']  += $view;
            $byAdUnit[$cid][$unit]['_match_rate_w'] += $matchRaw * $req;
        }

        foreach ($byCampaign as &$s) {
            $s['ctr']            = $s['impressions'] > 0 ? $s['clicks'] / $s['impressions'] : 0.0;
            $s['match_rate_pct'] = $s['ad_requests'] > 0 ? round($s['_match_rate_w'] / $s['ad_requests'], 2) : null;
            unset($s['_match_rate_w']);
        }
        unset($s);

        foreach ($byAdUnit as &$units) {
            foreach ($units as &$u) {
                $u['ecpm']           = $u['impressions'] > 0 ? round($u['revenue_usd'] / $u['impressions'] * 1000, 4) : 0.0;
                $u['fill_rate_pct']  = $u['ad_requests'] > 0 ? round($u['impressions'] / $u['ad_requests'] * 100, 1) : 0.0;
                $u['drop_off_pct']   = round(100 - $u['fill_rate_pct'], 1);
                $u['viewable_pct']   = $u['impressions'] > 0 ? round($u['viewable_imp'] / $u['impressions'] * 100, 1) : 0.0;
                $u['ctr']            = $u['impressions'] > 0 ? round($u['clicks'] / $u['impressions'] * 100, 3) : 0.0;
                $u['match_rate_pct'] = $u['ad_requests'] > 0 ? round($u['_match_rate_w'] / $u['ad_requests'], 2) : null;
                unset($u['_match_rate_w']);
            }
            unset($u);
            uasort($units, fn($a, $b) => $b['revenue_usd'] <=> $a['revenue_usd']);
        }
        unset($units);

        return ['by_campaign' => $byCampaign, 'by_ad_unit' => $byAdUnit];
    }

    public function fetchByCampaign(string $startDate, string $endDate): array
    {
        return $this->fetchAll($startDate, $endDate)['by_campaign'];
    }
}
