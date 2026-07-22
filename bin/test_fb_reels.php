<?php
declare(strict_types=1);
require_once __DIR__ . '/../vendor/autoload.php';
$config = require __DIR__ . '/../config/config.php';

use App\Core\HttpClient\HttpClient;
use App\Services\MetaAds\CampaignCreatorService;

$http = new HttpClient($config['retry'], $config['curl'] ?? []);
$token = $config['meta_ads']['access_token'];
$apiVersion = 'v22.0';
$accountId = 'act_834518578730293';

// Usa a campanha já existente do teste anterior
$campaignId = '120253464899250230';

$baseUrl = "https://graph.facebook.com/{$apiVersion}";

// Testa com US onde Facebook Reels é mais amplamente disponível
$targeting = [
    'geo_locations' => ['countries' => ['US']],
    'age_min' => 18,
    'age_max' => 65,
    'publisher_platforms' => ['facebook'],
    'facebook_positions' => ['facebook_reels'],
];

$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => "{$baseUrl}/{$accountId}/adsets",
    CURLOPT_POST => true,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_POSTFIELDS => [
        'name'              => 'TEST_FB_REELS_US',
        'campaign_id'       => $campaignId,
        'status'            => 'PAUSED',
        'daily_budget'      => '500',
        'billing_event'     => 'IMPRESSIONS',
        'optimization_goal' => 'OFFSITE_CONVERSIONS',
        'bid_strategy'      => 'LOWEST_COST_WITHOUT_CAP',
        'targeting'         => json_encode($targeting),
        'start_time'        => date('Y-m-d'),
        'promoted_object'   => json_encode(['pixel_id' => '820776303461311', 'custom_event_type' => 'PURCHASE']),
        'access_token'      => $token,
    ],
]);
$resp = curl_exec($ch);
$data = json_decode($resp, true);
echo "=== FB Reels US ===\n";
if (isset($data['id'])) {
    echo "SUCESSO — adset_id: {$data['id']}\n";
} else {
    $err = $data['error'] ?? $data;
    echo "ERRO subcode={$err['error_subcode']} — {$err['error_user_msg']}\n";
    echo "Msg: {$err['message']}\n";
}
echo "\n--- Tentando agora com BR + facebook_positions=['facebook_reels'] ---\n";
$targeting['geo_locations'] = ['countries' => ['BR']];
curl_setopt($ch, CURLOPT_POSTFIELDS, [
    'name'              => 'TEST_FB_REELS_BR',
    'campaign_id'       => $campaignId,
    'status'            => 'PAUSED',
    'daily_budget'      => '500',
    'billing_event'     => 'IMPRESSIONS',
    'optimization_goal' => 'OFFSITE_CONVERSIONS',
    'bid_strategy'      => 'LOWEST_COST_WITHOUT_CAP',
    'targeting'         => json_encode($targeting),
    'start_time'        => date('Y-m-d'),
    'promoted_object'   => json_encode(['pixel_id' => '820776303461311', 'custom_event_type' => 'PURCHASE']),
    'access_token'      => $token,
]);
$resp2 = curl_exec($ch);
$data2 = json_decode($resp2, true);
if (isset($data2['id'])) {
    echo "SUCESSO — adset_id: {$data2['id']}\n";
} else {
    $err2 = $data2['error'] ?? $data2;
    echo "ERRO subcode={$err2['error_subcode']} — {$err2['error_user_msg']}\n";
}
curl_close($ch);
