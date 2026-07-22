<?php
declare(strict_types=1);
require_once __DIR__ . '/../vendor/autoload.php';
$config = require __DIR__ . '/../config/config.php';
use App\Core\HttpClient\HttpClient;

$http        = new HttpClient($config['retry'], $config['curl'] ?? []);
$token       = $config['meta_ads']['access_token'];
$appId       = $config['meta_ads']['app_id'];
$version     = $config['meta_ads']['api_version'] ?? 'v22.0';
$base        = "https://graph.facebook.com/{$version}";

echo "=== Diagnóstico de permissões do app ===\n\n";

// 1. Token info (/me)
echo "[1] Token /me\n";
try {
    $me = $http->get("{$base}/me", [], ['access_token' => $token, 'fields' => 'id,name'], 10);
    echo "    id={$me['id']}  name={$me['name']}\n\n";
} catch (\Throwable $e) {
    echo "    FALHOU: " . $e->getMessage() . "\n\n";
}

// 2. Permissões concedidas ao token
echo "[2] Permissões concedidas ao token (/me/permissions)\n";
try {
    $r = $http->get("{$base}/me/permissions", [], ['access_token' => $token], 10);
    foreach ($r['data'] ?? [] as $p) {
        $status = $p['status'] === 'granted' ? 'OK  ' : 'DENY';
        echo "    [{$status}] {$p['permission']}\n";
    }
} catch (\Throwable $e) {
    echo "    FALHOU: " . $e->getMessage() . "\n";
}
echo "\n";

// 3. Debug do token (verifica app e scopes)
echo "[3] Token debug (/debug_token)\n";
try {
    $r = $http->get("{$base}/debug_token", [], [
        'input_token'  => $token,
        'access_token' => "{$appId}|" . $config['meta_ads']['app_secret'] ?? '',
    ], 10);
    $data = $r['data'] ?? [];
    echo "    app_id    : " . ($data['app_id']    ?? '-') . "\n";
    echo "    user_id   : " . ($data['user_id']   ?? '-') . "\n";
    echo "    type      : " . ($data['type']      ?? '-') . "\n";
    echo "    is_valid  : " . ($data['is_valid']  ? 'true' : 'false') . "\n";
    echo "    expires   : " . ($data['expires_at'] ? date('Y-m-d H:i', $data['expires_at']) : 'never') . "\n";
    echo "    scopes    : " . implode(', ', $data['scopes'] ?? []) . "\n";
    if (!empty($data['error'])) {
        echo "    error     : " . json_encode($data['error']) . "\n";
    }
} catch (\Throwable $e) {
    echo "    FALHOU (precisa de app_secret): " . $e->getMessage() . "\n";
}
echo "\n";

// 4. Contas de anúncio acessíveis pelo token
echo "[4] Contas de anúncio acessíveis (/me/adaccounts)\n";
try {
    $r = $http->get("{$base}/me/adaccounts", [], [
        'access_token' => $token,
        'fields'       => 'id,name,account_status,currency,amount_spent,balance',
        'limit'        => 20,
    ], 15);
    $accounts = $r['data'] ?? [];
    if (empty($accounts)) {
        echo "    Nenhuma conta retornada\n";
    } else {
        foreach ($accounts as $a) {
            printf(
                "    %-28s | status=%s | balance=%s %s\n",
                $a['id'] . ' ' . substr($a['name'] ?? '', 0, 20),
                $a['account_status'] ?? '?',
                $a['balance']   ?? '0',
                $a['currency']  ?? ''
            );
        }
    }
} catch (\Throwable $e) {
    echo "    FALHOU: " . $e->getMessage() . "\n";
}
echo "\n";

echo "=== Fim do diagnóstico ===\n";
