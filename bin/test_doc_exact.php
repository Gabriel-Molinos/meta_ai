<?php
declare(strict_types=1);
require_once __DIR__ . '/../vendor/autoload.php';
$config  = require __DIR__ . '/../config/config.php';
$curlCfg = $config['curl'] ?? [];

$token   = $config['meta_ads']['access_token'];
$actId   = $argv[1] ?? '1670103820636729';

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

echo "=== Teste exato conforme documentação Meta (v25.0) ===\n";
echo "Conta: act_{$actId}\n\n";

// CAMPANHA — exatamente como na doc
echo "[1] Criando campanha (objective=LINK_CLICKS, sem special_ad_categories)...\n";
$campaign = postMeta("https://graph.facebook.com/v25.0/act_{$actId}/campaigns", [
    'name'                   => 'My Campaign ' . date('His'),
    'objective'              => 'OUTCOME_TRAFFIC',
    'status'                 => 'PAUSED',
    'special_ad_categories'  => '[]',
    'access_token'           => $token,
], $curlCfg);

echo json_encode($campaign, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n";

if (!isset($campaign['id'])) exit(1);
$campaignId = $campaign['id'];

// ADSET — exatamente como na doc (sem billing_event, optimization_goal, bid_strategy)
echo "[2] Criando adset (só daily_budget + targeting)...\n";
$adset = postMeta("https://graph.facebook.com/v25.0/act_{$actId}/adsets", [
    'name'         => 'My Ad Set',
    'campaign_id'  => $campaignId,
    'daily_budget' => '1000',
    'targeting'    => json_encode(['geo_locations' => ['countries' => ['US']]]),
    'access_token' => $token,
], $curlCfg);

echo json_encode($adset, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
