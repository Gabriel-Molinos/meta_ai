<?php
declare(strict_types=1);
require_once __DIR__ . '/../vendor/autoload.php';
$config = require __DIR__ . '/../config/config.php';
use App\Core\HttpClient\HttpClient;

$http    = new HttpClient($config['retry'], $config['curl'] ?? []);
$token   = $config['meta_ads']['access_token'];
$base    = "https://graph.facebook.com/v25.0";
$campId  = $argv[1] ?? '120245079075840230';
$actId   = $argv[2] ?? '834518578730293';

echo "=== Inspecionando campanha {$campId} (act_{$actId}) ===\n\n";

// Campanha
$campaign = $http->get("{$base}/{$campId}", [], [
    'access_token' => $token,
    'fields'       => 'id,name,objective,status,special_ad_categories,daily_budget,lifetime_budget,bid_strategy,is_adset_budget_sharing_enabled,buying_type,budget_rebalance_flag',
], 15);

echo "[CAMPANHA]\n";
foreach ($campaign as $k => $v) {
    echo "  {$k}: " . (is_array($v) ? json_encode($v) : $v) . "\n";
}

// Adsets da campanha
echo "\n[ADSETS]\n";
$adsets = $http->get("{$base}/{$campId}/adsets", [], [
    'access_token' => $token,
    'fields'       => 'id,name,status,daily_budget,lifetime_budget,billing_event,optimization_goal,bid_strategy,bid_amount,targeting,promoted_object,destination_type,start_time',
    'limit'        => 5,
], 15);

foreach ($adsets['data'] ?? [] as $adset) {
    echo "\n  Adset: {$adset['id']} — {$adset['name']}\n";
    foreach ($adset as $k => $v) {
        if ($k === 'targeting') {
            echo "    targeting: " . json_encode($v, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
        } else {
            echo "    {$k}: " . (is_array($v) ? json_encode($v) : $v) . "\n";
        }
    }
}
