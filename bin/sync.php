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
use App\Services\Fx\ExchangeRateService;
use App\Services\MetaAds\CampaignService;
use App\Services\MetaAds\InsightService;
use App\Services\Report\ConsolidationService;

Connection::configure($config['database']);

$cache        = new RedisCache($config['redis']);
$http         = new HttpClient($config['retry'], $config['curl'] ?? []);
$enc          = new Encryptor($config['encryption_key']);
$accountModel = new Account($enc);
$reportModel  = new CampaignReport();
$execLog      = new ExecutionLog();
$consolidation = new ConsolidationService();
$fxService     = new ExchangeRateService($http, $cache);
$lookbackDays  = (int) ($config['sync']['lookback_days'] ?? 30);

// Aceita account_key como argumento CLI: php bin/sync.php minha-conta
$filterKey = $argv[1] ?? null;

if ($filterKey !== null) {
    $row = $accountModel->find($filterKey);
    if (!$row || !(bool) $row['active']) {
        echo "Conta '{$filterKey}' não encontrada ou inativa.\n";
        exit(1);
    }
    $accounts = [$accountModel->toConfig($row)];
    echo "Processando conta: {$filterKey}\n";
} else {
    $accounts = $accountModel->allForSync();
}

if (empty($accounts)) {
    echo "Nenhuma conta ativa. Cadastre via POST /api/accounts.\n";
    exit(0);
}

foreach ($accounts as $account) {
    $accountKey  = $account['account_key'];
    $missingDates = $reportModel->getMissingDates($accountKey, $lookbackDays);

    if (empty($missingDates)) {
        echo "[{$accountKey}] Todos os dias já sincronizados.\n";
        continue;
    }

    echo "[{$accountKey}] " . count($missingDates) . " dias pendentes: "
        . reset($missingDates) . " até " . end($missingDates) . "\n";

    $campaignService = new CampaignService($account['meta_ads'], $http, $cache);
    $insightService  = new InsightService($account['meta_ads'], $http, $cache, $fxService);
    $revenueService  = new RevenueService($account['active_view'], $http, $cache);
    $sessionService  = new SessionService($account['active_view'], $http, $cache);
    $domain          = $account['active_view']['domain'];

    $startDate = reset($missingDates);
    $endDate   = end($missingDates);

    $executionId = $execLog->create($accountKey, $startDate, $endDate);

    try {
        $activeCampaigns = $campaignService->fetchCampaigns();

        $insightsRaw = $insightService->fetchInsights($startDate, $endDate);
        $insights    = [];
        foreach ($insightsRaw as $campaignId => $dates) {
            if (($activeCampaigns[$campaignId]['status'] ?? null) !== 'ACTIVE') {
                continue;
            }
            foreach ($dates as $date => $dayData) {
                $dayData['status'] = 'ACTIVE';
                $dates[$date]      = $dayData;
            }
            $insights[$campaignId] = $dates;
        }

        $revenue  = $revenueService->fetch($startDate, $endDate);
        $sessions = $sessionService->fetch($startDate, $endDate);

        $totalSaved = 0;

        foreach ($missingDates as $date) {
            $campaigns = $consolidation->consolidate($insights, $revenue, $sessions, $domain, $date);

            if (!empty($campaigns)) {
                $reportModel->saveAll($executionId, $date, $accountKey, $campaigns);
                $totalSaved += count($campaigns);
                echo "[{$accountKey}] {$date}: " . count($campaigns) . " campanhas salvas.\n";
            } else {
                echo "[{$accountKey}] {$date}: sem dados.\n";
            }
        }

        $execLog->complete($executionId, $totalSaved);
        echo "[{$accountKey}] Sync concluído. {$totalSaved} registros salvos.\n";

    } catch (\Throwable $e) {
        $execLog->fail($executionId, $e->getMessage());
        echo "[{$accountKey}] ERRO: " . $e->getMessage() . "\n";
    }
}
