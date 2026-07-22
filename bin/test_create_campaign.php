<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

$config = require __DIR__ . '/../config/config.php';

use App\Core\HttpClient\HttpClient;
use App\Services\MetaAds\CampaignCreatorService;

$http = new HttpClient($config['retry'], $config['curl'] ?? []);

// Conta de teste — use account_id como argumento ou cai na do .env
$accountId   = $argv[1] ?? $config['meta_ads']['account_id'];
$accessToken = $config['meta_ads']['access_token'];
$apiVersion  = $config['meta_ads']['api_version'] ?? 'v22.0';

$metaConfig = [
    'access_token' => $accessToken,
    'account_id'   => $accountId,
    'api_version'  => $apiVersion ?? 'v22.0',
];

$creator = new CampaignCreatorService($metaConfig, $http);

echo "=== Teste de criação de campanha ===\n";
echo "Conta  : {$accountId}\n";
echo "Token  : " . substr($accessToken, 0, 30) . "...\n\n";

// ---- 1. Criar campanha -------------------------------------------------------
echo "[1] Criando campanha...\n";
try {
    $campaignId = $creator->createCampaign(
        'TESTE_API_' . date('Ymd_His'),
        'OUTCOME_TRAFFIC'
    );
    echo "    OK — campaign_id: {$campaignId}\n\n";
} catch (\Throwable $e) {
    echo "    FALHOU — " . $e->getMessage() . "\n";
    exit(1);
}

// ---- 2. Criar ad set ---------------------------------------------------------
echo "[2] Criando ad set...\n";
try {
    $adSetId = $creator->createAdSet($campaignId, [
        'campaign_name'      => 'TESTE_API',
        'objective'          => 'OUTCOME_TRAFFIC',
        'countries'          => ['BR'],
        'daily_budget_cents' => 1000, // R$10,00
        'start_time'         => date('Y-m-d'),
    ]);
    echo "    OK — adset_id: {$adSetId}\n\n";
} catch (\Throwable $e) {
    echo "    FALHOU — " . $e->getMessage() . "\n";
    // Limpa campanha criada
    echo "    (campanha {$campaignId} permanece no Meta — delete manualmente se necessário)\n";
    exit(1);
}

echo "=== SUCESSO ===\n";
echo "campaign_id : {$campaignId}\n";
echo "adset_id    : {$adSetId}\n";
echo "\nAmbos foram criados com status PAUSED — nenhum gasto ocorreu.\n";
echo "Delete em: https://www.facebook.com/adsmanager\n";
