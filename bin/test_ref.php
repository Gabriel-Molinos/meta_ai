<?php
declare(strict_types=1);
require_once __DIR__ . '/../vendor/autoload.php';
$config  = require __DIR__ . '/../config/config.php';
$curlCfg = $config['curl'] ?? [];

$token   = $config['meta_ads']['access_token'];
$account = 'act_' . ($argv[1] ?? '1976520109462890');

$input = [
    'campaign_name' => 'Campanha Teste API ' . date('His'),
    'objective'     => 'OUTCOME_TRAFFIC',
    'daily_budget'  => '1000',
    'country'       => 'US',
    'pixel'         => '2133584954165196',
];

function postMeta(string $url, array $fields, array $curlCfg): array
{
    $ch = curl_init($url);
    $opts = [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $fields,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => $curlCfg['ssl_verify'] ?? true,
        CURLOPT_SSL_VERIFYHOST => ($curlCfg['ssl_verify'] ?? true) ? 2 : 0,
        CURLOPT_FOLLOWLOCATION => true,
    ];
    if (!empty($curlCfg['cainfo']) && file_exists($curlCfg['cainfo'])) {
        $opts[CURLOPT_CAINFO] = $curlCfg['cainfo'];
    }
    curl_setopt_array($ch, $opts);
    $r = curl_exec($ch);
    curl_close($ch);
    return json_decode((string) $r, true) ?? [];
}

echo "Conta  : {$account}\n";
echo "Versão : v23.0\n\n";

/* CAMPANHA */
echo "[1] Criando campanha...\n";
$campaign = postMeta("https://graph.facebook.com/v23.0/{$account}/campaigns", [
    'name'                            => $input['campaign_name'],
    'objective'                       => $input['objective'],
    'status'                          => 'PAUSED',
    'special_ad_categories'           => '[]',
    'is_adset_budget_sharing_enabled' => '1',
    'access_token'                    => $token,
], $curlCfg);

echo json_encode($campaign, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n";

if (!isset($campaign['id'])) exit(1);
$campaignId = $campaign['id'];

/* ADSET */
echo "[2] Criando adset...\n";
$adset = postMeta("https://graph.facebook.com/v23.0/{$account}/adsets", [
    'name'              => 'AdSet API',
    'campaign_id'       => $campaignId,
    'billing_event'     => 'IMPRESSIONS',
    'optimization_goal' => 'LINK_CLICKS',
    'bid_strategy'      => 'LOWEST_COST_WITHOUT_CAP',
    'daily_budget'      => $input['daily_budget'],
    'destination_type'  => 'WEBSITE',
    'targeting'         => json_encode([
        'geo_locations' => ['countries' => [$input['country']]],
        'age_min'       => 18,
        'age_max'       => 65,
    ]),
    'promoted_object'   => json_encode(['pixel_id' => $input['pixel']]),
    'status'            => 'PAUSED',
    'access_token'      => $token,
], $curlCfg);

echo json_encode($adset, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
