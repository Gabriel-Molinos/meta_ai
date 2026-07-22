<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

$config = require __DIR__ . '/../config/config.php';

use App\Core\Cache\RedisCache;
use App\Core\Database\Connection;
use App\Core\Encryption\Encryptor;
use App\Core\HttpClient\HttpClient;
use App\Models\Account;

Connection::configure($config['database']);

$enc          = new Encryptor($config['encryption_key']);
$accountModel = new Account($enc);
$http         = new HttpClient($config['retry'], $config['curl'] ?? []);

// Aceita account_key como argumento: php bin/test_meta_api.php [account_key]
$filterKey = $argv[1] ?? null;

if ($filterKey !== null) {
    $row = $accountModel->find($filterKey);
    if (!$row) {
        echo "ERRO: Conta '{$filterKey}' não encontrada.\n";
        exit(1);
    }
    $accounts = [$accountModel->toConfig($row)];
} else {
    $accounts = $accountModel->allForSync();
}

if (empty($accounts)) {
    echo "Nenhuma conta ativa cadastrada.\n";
    exit(0);
}

$version = 'v22.0';
$baseUrl  = "https://graph.facebook.com/{$version}";
$today    = date('Y-m-d');
$yesterday = date('Y-m-d', strtotime('-1 day'));

foreach ($accounts as $account) {
    $key         = $account['account_key'];
    $accountId   = ltrim($account['meta_ads']['account_id'], 'act_');
    $accessToken = $account['meta_ads']['access_token'];

    echo "\n=== Conta: {$key} (act_{$accountId}) ===\n";

    // ---- 1. Verifica o token (/me) ----------------------------------------
    echo "\n[1] Verificando token (/me)...\n";
    try {
        $me = $http->get("{$baseUrl}/me", [], ['access_token' => $accessToken, 'fields' => 'id,name'], 10);
        echo "    OK — id={$me['id']}, name={$me['name']}\n";
    } catch (\Throwable $e) {
        echo "    FALHOU — " . $e->getMessage() . "\n";
        continue;
    }

    // ---- 2. Verifica permissões do token ------------------------------------
    echo "\n[2] Verificando permissões do token (/me/permissions)...\n";
    try {
        $perms = $http->get("{$baseUrl}/me/permissions", [], ['access_token' => $accessToken], 10);
        $granted = array_filter($perms['data'] ?? [], fn($p) => $p['status'] === 'granted');
        $needed  = ['ads_read', 'ads_management', 'business_management'];
        foreach ($needed as $perm) {
            $has = !empty(array_filter($granted, fn($p) => $p['permission'] === $perm));
            echo "    " . ($has ? 'OK' : 'AUSENTE') . " — {$perm}\n";
        }
    } catch (\Throwable $e) {
        echo "    FALHOU — " . $e->getMessage() . "\n";
    }

    // ---- 3. Verifica acesso à conta de anúncios ----------------------------
    echo "\n[3] Verificando acesso à conta act_{$accountId}...\n";
    try {
        $acct = $http->get(
            "{$baseUrl}/act_{$accountId}",
            [],
            ['access_token' => $accessToken, 'fields' => 'id,name,account_status,currency,timezone_name'],
            15
        );
        echo "    OK\n";
        echo "    name           : " . ($acct['name']            ?? '-') . "\n";
        echo "    account_status : " . ($acct['account_status']  ?? '-') . " (1=ativa)\n";
        echo "    currency       : " . ($acct['currency']        ?? '-') . "\n";
        echo "    timezone       : " . ($acct['timezone_name']   ?? '-') . "\n";
    } catch (\Throwable $e) {
        echo "    FALHOU — " . $e->getMessage() . "\n";
        continue;
    }

    // ---- 4. Busca insights de ontem ----------------------------------------
    echo "\n[4] Buscando insights de ontem ({$yesterday})...\n";
    try {
        $insightsUrl = "{$baseUrl}/act_{$accountId}/insights";
        $params = [
            'fields'         => 'campaign_id,campaign_name,impressions,clicks,spend',
            'level'          => 'campaign',
            'time_increment' => 1,
            'time_range'     => json_encode(['since' => $yesterday, 'until' => $yesterday]),
            'limit'          => 5,
            'access_token'   => $accessToken,
        ];

        $res  = $http->get($insightsUrl, [], $params, 30);
        $rows = $res['data'] ?? [];

        if (empty($rows)) {
            echo "    OK — sem dados para ontem (pode ser normal em fins de semana ou conta sem spend)\n";
        } else {
            echo "    OK — " . count($rows) . " campanha(s) retornada(s) (limite=5):\n";
            foreach ($rows as $row) {
                printf(
                    "      %-12s | %-35s | impressions=%s clicks=%s spend=%s\n",
                    $row['campaign_id']   ?? '-',
                    substr($row['campaign_name'] ?? '-', 0, 35),
                    $row['impressions']   ?? '0',
                    $row['clicks']        ?? '0',
                    $row['spend']         ?? '0'
                );
            }
        }

        $paging = $res['paging'] ?? [];
        if (!empty($paging['next'])) {
            echo "    (há mais páginas — paginação cursor funcionando)\n";
        }
    } catch (\Throwable $e) {
        echo "    FALHOU — " . $e->getMessage() . "\n";
    }
}

echo "\n=== Teste concluído ===\n";
