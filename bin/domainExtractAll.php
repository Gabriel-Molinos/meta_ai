<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

$config = require __DIR__ . '/../config/config.php';

use App\Core\Cache\RedisCache;
use App\Core\Database\Connection;
use App\Core\Encryption\Encryptor;
use App\Core\HttpClient\HttpClient;
use App\Models\Account;
use App\Models\CampaignReport;
use App\Models\ExecutionLog;
use App\Services\ActiveView\RevenueService;
use App\Services\ActiveView\SessionService;
use App\Services\Report\ConsolidationService;

// Uso: php bin/domainExtractAll.php [lookback_days] [--retries=N]
//
// Extrai dados ActiveView (receita/sessões) para TODAS as contas ativas que
// tenham av_domain + av_network_code + av_api_key cadastrados, num único
// processo sequencial — substitui a necessidade de um cron por conta/domínio.
// Cada conta usa o próprio av_domain/av_network_code salvo em `accounts`.
//
// lookback_days: sem argumento processa só o dia de ontem (uso normal em cron
//   diário); com argumento processa os últimos N dias (backfill manual).
// --retries=N: sobrescreve o max_attempts padrão (config/config.php) por
//   execução — mesmo parâmetro que bin/domainExtract.php aceitava por domínio.
//
// Contas com mais de um domínio na ActiveView, ou que precisem de um
// domain/network_code diferente do cadastrado, continuam sendo cobertas
// por chamadas manuais/avulsas a bin/domainExtract.php (não removido).
$positional = [];
$flags      = [];
foreach (array_slice($argv, 1) as $arg) {
    if (str_starts_with($arg, '--')) {
        $eq = strpos($arg, '=');
        if ($eq !== false) {
            $flags[substr($arg, 2, $eq - 2)] = substr($arg, $eq + 1);
        }
    } else {
        $positional[] = $arg;
    }
}

$lookbackDays = isset($positional[0]) ? max(1, (int) $positional[0]) : 1;

Connection::configure($config['database']);

$cache = new RedisCache($config['redis']);
$enc   = new Encryptor($config['encryption_key']);

$retryConfig = $config['retry'];
if (isset($flags['retries'])) {
    $retryConfig['max_attempts'] = max(1, (int) $flags['retries']);
}
$http = new HttpClient($retryConfig, $config['curl'] ?? []);

$accountModel  = new Account($enc);
$reportModel   = new CampaignReport();
$execLog       = new ExecutionLog();
$consolidation = new ConsolidationService();

$accounts = $accountModel->allForSync();

if (empty($accounts)) {
    echo "Nenhuma conta ativa. Cadastre via POST /api/accounts.\n";
    exit(0);
}

$endDate   = date('Y-m-d', strtotime('-1 day'));
$startDate = date('Y-m-d', strtotime("-{$lookbackDays} days"));

foreach ($accounts as $account) {
    $accountKey = $account['account_key'];
    $avConfig   = $account['active_view'];

    if ($avConfig['api_key'] === '' || $avConfig['domain'] === '' || $avConfig['network_code'] === '') {
        echo "[{$accountKey}] Pulado: credenciais ActiveView incompletas (api_key/domain/network_code).\n";
        continue;
    }

    echo "[{$accountKey}] Extraindo ActiveView para domínio '{$avConfig['domain']}' ({$startDate} a {$endDate})...\n";

    $revenueService = new RevenueService($avConfig, $http, $cache);
    $sessionService = new SessionService($avConfig, $http, $cache);

    $executionId = $execLog->create($accountKey, $startDate, $endDate);

    try {
        $revenue  = $revenueService->fetch($startDate, $endDate);
        $sessions = $sessionService->fetch($startDate, $endDate);

        $totalUpserted = 0;

        $cursor = new DateTimeImmutable($startDate);
        $last   = new DateTimeImmutable($endDate);

        while ($cursor <= $last) {
            $date   = $cursor->format('Y-m-d');
            $avRows = $consolidation->consolidateActiveViewOnly($revenue, $sessions, $date);

            if (!empty($avRows)) {
                $count = $reportModel->upsertActiveViewMetrics($executionId, $date, $accountKey, $avConfig['domain'], $avRows);
                $totalUpserted += $count;
                echo "[{$accountKey}] {$avConfig['domain']} {$date}: {$count} campanhas atualizadas.\n";
            } else {
                echo "[{$accountKey}] {$avConfig['domain']} {$date}: sem dados AV.\n";
            }

            $cursor = $cursor->modify('+1 day');
        }

        $execLog->complete($executionId, $totalUpserted);
        echo "[{$accountKey}] Extração concluída. {$totalUpserted} registros processados.\n";
    } catch (\Throwable $e) {
        $execLog->fail($executionId, $e->getMessage());
        echo "[{$accountKey}] ERRO: " . $e->getMessage() . "\n";
        // Não interrompe as demais contas — mesma resiliência de bin/sync.php.
    }
}
