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

echo "=== Teste minimal (mais próximo possível da doc) — act_{$actId} ===\n\n";

// CAMPANHA — doc usa LINK_CLICKS (deprecado), substituído por OUTCOME_TRAFFIC
// special_ad_categories adicionado pois a API exige
echo "[1] Campanha...\n";
$campaign = postMeta("https://graph.facebook.com/v25.0/act_{$actId}/campaigns", [
    'name'                  => 'My Campaign',
    'objective'             => 'OUTCOME_TRAFFIC',
    'status'                => 'PAUSED',
    'special_ad_categories' => '[]',
    'access_token'          => $token,
], $curlCfg);

echo json_encode($campaign, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n";
if (!isset($campaign['id'])) exit(1);

// ADSET — exatamente como a doc: só name, campaign_id, daily_budget, targeting
echo "[2] Adset (minimal: sem billing_event, optimization_goal, bid_strategy)...\n";
$adset = postMeta("https://graph.facebook.com/v25.0/act_{$actId}/adsets", [
    'name'         => 'My Ad Set',
    'campaign_id'  => $campaign['id'],
    'daily_budget' => '1000',
    'targeting'    => json_encode(['geo_locations' => ['countries' => ['US']]]),
    'access_token' => $token,
], $curlCfg);

echo json_encode($adset, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";

if (isset($adset['id'])) {
    echo "\n=== SUCESSO ===\n";
    echo "campaign_id : {$campaign['id']}\n";
    echo "adset_id    : {$adset['id']}\n";
}
