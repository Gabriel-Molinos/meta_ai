<?php
declare(strict_types=1);
require_once __DIR__ . '/../vendor/autoload.php';
$config = require __DIR__ . '/../config/config.php';

$token   = $config['meta_ads']['access_token'];
$actId   = $argv[1] ?? '1976520109462890';
$version = 'v22.0';
$base    = "https://graph.facebook.com/{$version}";

echo "=== Teste raw cURL (form-data, {$version}) ===\n";
echo "Conta : act_{$actId}\n\n";

function curlPost(string $url, array $fields, array $curlConfig): array
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $fields,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_SSL_VERIFYPEER => $curlConfig['ssl_verify'] ?? true,
        CURLOPT_SSL_VERIFYHOST => ($curlConfig['ssl_verify'] ?? true) ? 2 : 0,
        CURLOPT_FOLLOWLOCATION => true,
    ]);
    if (!($curlConfig['ssl_verify'] ?? true) || (isset($curlConfig['cainfo']) && file_exists($curlConfig['cainfo']))) {
        if (!empty($curlConfig['cainfo']) && file_exists($curlConfig['cainfo'])) {
            curl_setopt($ch, CURLOPT_CAINFO, $curlConfig['cainfo']);
        }
    }
    $response = curl_exec($ch);
    $status   = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err      = curl_error($ch);
    curl_close($ch);

    if ($err) throw new \RuntimeException("cURL error: {$err}");

    $decoded = json_decode((string) $response, true) ?? [];
    if ($status < 200 || $status >= 300) {
        throw new \RuntimeException("HTTP {$status} — " . json_encode($decoded['error'] ?? $response));
    }
    return $decoded;
}

$curlCfg = $config['curl'] ?? [];

// ---- 1. Criar campanha (form-data) ------------------------------------------
echo "[1] Criando campanha...\n";
try {
    $r = curlPost("{$base}/act_{$actId}/campaigns", [
        'name'                   => 'TESTE_API_' . date('Ymd_His'),
        'objective'              => 'OUTCOME_TRAFFIC',
        'status'                 => 'PAUSED',
        'special_ad_categories'           => '[]',
        'is_adset_budget_sharing_enabled' => '1',
        'access_token'                    => $token,
    ], $curlCfg);

    $campaignId = $r['id'] ?? null;
    echo "    OK — campaign_id: {$campaignId}\n\n";
} catch (\Throwable $e) {
    echo "    FALHOU — " . $e->getMessage() . "\n";
    exit(1);
}

// ---- 2. Criar ad set (form-data) --------------------------------------------
echo "[2] Criando ad set...\n";
try {
    $r = curlPost("{$base}/act_{$actId}/adsets", [
        'name'              => 'TESTE_API — Conjunto',
        'campaign_id'       => $campaignId,
        'billing_event'     => 'IMPRESSIONS',
        'optimization_goal' => 'LINK_CLICKS',
        'bid_strategy'      => 'LOWEST_COST_WITHOUT_CAP',
        'daily_budget'      => '1000',
        'destination_type'  => 'WEBSITE',
        'targeting'         => json_encode([
            'geo_locations' => ['countries' => ['BR']],
            'age_min'       => 18,
            'age_max'       => 65,
        ]),
        'start_time'        => date('Y-m-d\TH:i:sO'),
        'status'            => 'PAUSED',
        'access_token'      => $token,
    ], $curlCfg);

    $adSetId = $r['id'] ?? null;
    echo "    OK — adset_id: {$adSetId}\n\n";
} catch (\Throwable $e) {
    echo "    FALHOU — " . $e->getMessage() . "\n";
    echo "    (campanha {$campaignId} criada — delete em adsmanager se necessário)\n";
    exit(1);
}

echo "=== SUCESSO ===\n";
echo "campaign_id : {$campaignId}\n";
echo "adset_id    : {$adSetId}\n";
echo "\nAmbos PAUSED — sem gasto. Delete em: https://www.facebook.com/adsmanager\n";
