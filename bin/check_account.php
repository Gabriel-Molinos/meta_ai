<?php
declare(strict_types=1);
require_once __DIR__ . '/../vendor/autoload.php';
$config = require __DIR__ . '/../config/config.php';
use App\Core\HttpClient\HttpClient;

$http    = new HttpClient($config['retry'], $config['curl'] ?? []);
$token   = $config['meta_ads']['access_token'];
$version = $config['meta_ads']['api_version'] ?? 'v22.0';
$base    = "https://graph.facebook.com/{$version}";
$actId   = $argv[1] ?? '1976520109462890';

$r = $http->get("{$base}/act_{$actId}", [], [
    'access_token' => $token,
    'fields'       => 'id,name,account_status,disable_reason,currency,balance,amount_spent,business,owner',
], 15);

echo "id             : " . ($r['id']             ?? '-') . "\n";
echo "name           : " . ($r['name']           ?? '-') . "\n";
echo "account_status : " . ($r['account_status'] ?? '-') . " (1=ativa, 2=desabilitada, 3=inadimplente, 7=pendente)\n";
echo "disable_reason : " . ($r['disable_reason'] ?? '-') . " (0=sem motivo)\n";
echo "currency       : " . ($r['currency']       ?? '-') . "\n";
echo "balance        : " . ($r['balance']        ?? '-') . "\n";
echo "amount_spent   : " . ($r['amount_spent']   ?? '-') . "\n";
echo "business       : " . json_encode($r['business'] ?? null) . "\n";
echo "owner          : " . json_encode($r['owner']    ?? null) . "\n";
echo "restrictions   : " . json_encode($r['restrictions'] ?? null) . "\n";
