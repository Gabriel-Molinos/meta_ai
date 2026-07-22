<?php
declare(strict_types=1);
require_once __DIR__ . '/../vendor/autoload.php';
$config = require __DIR__ . '/../config/config.php';

$token   = $config['meta_ads']['access_token'];
$actId   = $argv[1] ?? '1670103820636729';
$base    = "https://graph.facebook.com/v22.0";
$curlCfg = $config['curl'] ?? [];

function tryCreate(string $base, string $actId, string $token, array $extra, array $curlCfg): string
{
    $fields = array_merge([
        'name'                   => 'TESTE_API_' . date('Ymd_His'),
        'objective'              => 'OUTCOME_TRAFFIC',
        'status'                 => 'PAUSED',
        'special_ad_categories'  => '[]',
        'access_token'           => $token,
    ], $extra);

    $ch = curl_init("{$base}/act_{$actId}/campaigns");
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $fields,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 20,
        CURLOPT_SSL_VERIFYPEER => $curlCfg['ssl_verify'] ?? true,
        CURLOPT_SSL_VERIFYHOST => ($curlCfg['ssl_verify'] ?? true) ? 2 : 0,
        CURLOPT_FOLLOWLOCATION => true,
    ]);
    if (!empty($curlCfg['cainfo']) && file_exists($curlCfg['cainfo'])) {
        curl_setopt($ch, CURLOPT_CAINFO, $curlCfg['cainfo']);
    }
    $response = curl_exec($ch);
    $status   = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $decoded = json_decode((string) $response, true) ?? [];
    if (isset($decoded['id'])) return "OK — id={$decoded['id']}";
    $err = $decoded['error'] ?? [];
    return "HTTP {$status} | code={$err['code']} subcode={$err['error_subcode']} | {$err['error_user_title']}";
}

echo "=== Teste de bid_strategies na conta act_{$actId} ===\n\n";

$combos = [
    'sem is_adset_budget_sharing_enabled'                      => [],
    'sharing=0'                                                 => ['is_adset_budget_sharing_enabled' => '0'],
    'sharing=1 sem bid_strategy'                               => ['is_adset_budget_sharing_enabled' => '1'],
    'sharing=1 + LOWEST_COST_WITHOUT_CAP'                      => ['is_adset_budget_sharing_enabled' => '1', 'bid_strategy' => 'LOWEST_COST_WITHOUT_CAP'],
    'sharing=1 + LOWEST_COST_WITH_BID_CAP'                     => ['is_adset_budget_sharing_enabled' => '1', 'bid_strategy' => 'LOWEST_COST_WITH_BID_CAP'],
    'sharing=1 + COST_CAP'                                     => ['is_adset_budget_sharing_enabled' => '1', 'bid_strategy' => 'COST_CAP'],
    'CBO: daily_budget=1000 sem sharing'                       => ['daily_budget' => '1000'],
    'CBO: daily_budget=1000 + LOWEST_COST_WITHOUT_CAP'         => ['daily_budget' => '1000', 'bid_strategy' => 'LOWEST_COST_WITHOUT_CAP'],
];

foreach ($combos as $label => $extra) {
    $result = tryCreate($base, $actId, $token, $extra, $curlCfg);
    printf("%-52s → %s\n", $label, $result);
    if (str_starts_with($result, 'OK')) break; // Parou no primeiro sucesso
    usleep(300000);
}
