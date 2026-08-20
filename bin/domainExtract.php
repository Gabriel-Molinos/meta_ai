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

// Uso: php bin/domainExtract.php {domain} {start_date} {end_date}
//          --customer-id={account_key} --network-code={code} [--retries=N]
$positional = [];
$flags      = [];

foreach (array_slice($argv, 1) as $arg) {
    if (str_starts_with($arg, '--')) {
        $eq = strpos($arg, '=');
        if ($eq === false) {
            $flags[substr($arg, 2)] = true;
        } else {
            $flags[substr($arg, 2, $eq - 2)] = substr($arg, $eq + 1);
        }
    } else {
        $positional[] = $arg;
    }
}

[$domain, $startDate, $endDate] = array_pad($positional, 3, null);

$usage = "Uso: php bin/domainExtract.php {domain} {start_date} {end_date} --customer-id=X --network-code=Y [--retries=N]\n";

if ($domain === null || $startDate === null || $endDate === null
    || !isset($flags['customer-id']) || !isset($flags['network-code'])) {
    echo $usage;
    exit(1);
}

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $startDate) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $endDate)) {
    echo "Datas devem estar no formato YYYY-MM-DD.\n";
    exit(1);
}

if ($startDate > $endDate) {
    echo "start_date não pode ser maior que end_date.\n";
    exit(1);
}

$customerId  = (string) $flags['customer-id'];
$networkCode = (string) $flags['network-code'];
$retries     = isset($flags['retries']) ? max(1, (int) $flags['retries']) : (int) ($config['retry']['max_attempts'] ?? 3);

Connection::configure($config['database']);

$cache = new RedisCache($config['redis']);
$enc   = new Encryptor($config['encryption_key']);

$retryConfig = [
    'max_attempts'  => $retries,
    'base_delay_ms' => (int) ($config['retry']['base_delay_ms'] ?? 1000),
    'max_delay_ms'  => (int) ($config['retry']['max_delay_ms']  ?? 30000),
];
$http = new HttpClient($retryConfig, $config['curl'] ?? []);

$accountModel  = new Account($enc);
$reportModel   = new CampaignReport();
$execLog       = new ExecutionLog();
$consolidation = new ConsolidationService();

$row = $accountModel->find($customerId);
if (!$row || !(bool) $row['active']) {
    echo "Conta '{$customerId}' não encontrada ou inativa.\n";
    exit(1);
}

$account  = $accountModel->toConfig($row);
$avConfig = $account['active_view'];
$avConfig['domain']       = $domain;
$avConfig['network_code'] = $networkCode;

echo "[{$customerId}] Extraindo ActiveView para domínio '{$domain}' ({$startDate} a {$endDate})...\n";

$revenueService = new RevenueService($avConfig, $http, $cache);
$sessionService = new SessionService($avConfig, $http, $cache);

$executionId = $execLog->create($customerId, $startDate, $endDate);

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
            $count = $reportModel->upsertActiveViewMetrics($executionId, $date, $customerId, $domain, $avRows);
            $totalUpserted += $count;
            echo "[{$customerId}] {$domain} {$date}: {$count} campanhas atualizadas.\n";
        } else {
            echo "[{$customerId}] {$domain} {$date}: sem dados AV.\n";
        }

        $cursor = $cursor->modify('+1 day');
    }

    $execLog->complete($executionId, $totalUpserted);
    echo "[{$customerId}] Extração concluída. {$totalUpserted} registros processados.\n";
} catch (\Throwable $e) {
    $execLog->fail($executionId, $e->getMessage());
    echo "[{$customerId}] ERRO: " . $e->getMessage() . "\n";
    exit(1);
}
