<?php
declare(strict_types=1);
require_once __DIR__ . '/../vendor/autoload.php';
$config  = require __DIR__ . '/../config/config.php';
$curlCfg = $config['curl'] ?? [];
$token   = $config['meta_ads']['access_token'];
$actId   = $argv[1] ?? '834518578730293';

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

echo "=== Teste CBO (budget na campanha) — act_{$actId} ===\n\n";

// Campanha com CBO — exatamente como a campanha existente
echo "[1] Criando campanha CBO...\n";
$campaign = postMeta("https://graph.facebook.com/v25.0/act_{$actId}/campaigns", [
    'name'                   => 'TESTE_API_' . date('His'),
    'objective'              => 'OUTCOME_SALES',
    'status'                 => 'PAUSED',
    'special_ad_categories'  => '[]',
    'daily_budget'           => '5000',
    'bid_strategy'           => 'LOWEST_COST_WITHOUT_CAP',
    'access_token'           => $token,
], $curlCfg);

echo json_encode($campaign, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n";

if (!isset($campaign['id'])) exit(1);
$campaignId = $campaign['id'];

// Adset sem budget (budget está na campanha)
echo "[2] Criando adset...\n";
$adset = postMeta("https://graph.facebook.com/v25.0/act_{$actId}/adsets", [
    'name'              => 'TESTE_API — Conjunto',
    'campaign_id'       => $campaignId,
    'status'            => 'PAUSED',
    'billing_event'     => 'IMPRESSIONS',
    'optimization_goal' => 'OFFSITE_CONVERSIONS',
    'targeting'         => json_encode([
        'geo_locations' => ['countries' => ['US']],
        'age_min'       => 18,
        'age_max'       => 65,
    ]),
    'promoted_object'   => json_encode([
        'pixel_id'          => '820776303461311',
        'custom_event_type' => 'OTHER',
    ]),
    'start_time'        => date('Y-m-d'),
    'access_token'      => $token,
], $curlCfg);

echo json_encode($adset, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";

if (isset($adset['id'])) {
    echo "\n=== SUCESSO TOTAL ===\n";
    echo "campaign_id : {$campaignId}\n";
    echo "adset_id    : {$adset['id']}\n";
    echo "Ambos PAUSED — sem gasto. Delete em: facebook.com/adsmanager\n";
}
