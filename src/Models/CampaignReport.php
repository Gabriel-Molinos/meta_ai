<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database\Connection;
use PDO;

class CampaignReport
{
    public function saveAll(int $executionId, string $date, string $accountKey, array $campaigns): void
    {
        if (empty($campaigns)) {
            return;
        }

        $pdo = Connection::getInstance();

        $sql = 'INSERT INTO campaign_reports
                    (execution_id, report_date, campaign_id, account_key, domain,
                     campaign_name, status, impressions, clicks, reach,
                     spend_usd, ctr, cpc_usd, cpm_usd, roas,
                     av_revenue_usd, av_impressions, av_sessions)
                VALUES
                    (:execution_id, :report_date, :campaign_id, :account_key, :domain,
                     :campaign_name, :status, :impressions, :clicks, :reach,
                     :spend_usd, :ctr, :cpc_usd, :cpm_usd, :roas,
                     :av_revenue_usd, :av_impressions, :av_sessions)
                ON DUPLICATE KEY UPDATE
                    execution_id   = VALUES(execution_id),
                    domain         = VALUES(domain),
                    campaign_name  = VALUES(campaign_name),
                    status         = VALUES(status),
                    impressions    = VALUES(impressions),
                    clicks         = VALUES(clicks),
                    reach          = VALUES(reach),
                    spend_usd      = VALUES(spend_usd),
                    ctr            = VALUES(ctr),
                    cpc_usd        = VALUES(cpc_usd),
                    cpm_usd        = VALUES(cpm_usd),
                    av_revenue_usd = IF(VALUES(av_revenue_usd) > 0, VALUES(av_revenue_usd), av_revenue_usd),
                    av_impressions = IF(VALUES(av_impressions) > 0, VALUES(av_impressions), av_impressions),
                    av_sessions    = IF(VALUES(av_sessions)    > 0, VALUES(av_sessions),    av_sessions),
                    roas           = IF(VALUES(spend_usd) > 0,
                                         ROUND((IF(VALUES(av_revenue_usd) > 0, VALUES(av_revenue_usd), av_revenue_usd) - VALUES(spend_usd)) / VALUES(spend_usd), 4),
                                         0)';

        $stmt = $pdo->prepare($sql);

        foreach ($campaigns as $c) {
            $stmt->execute([
                ':execution_id'   => $executionId,
                ':report_date'    => $date,
                ':campaign_id'    => $c['campaign_id'],
                ':account_key'    => $accountKey,
                ':domain'         => $c['domain']         ?? '',
                ':campaign_name'  => $c['campaign_name'],
                ':status'         => $c['status']         ?? '',
                ':impressions'    => $c['impressions']    ?? 0,
                ':clicks'         => $c['clicks']         ?? 0,
                ':reach'          => $c['reach']          ?? 0,
                ':spend_usd'      => $c['spend_usd']      ?? 0.0,
                ':ctr'            => $c['ctr']            ?? 0.0,
                ':cpc_usd'        => $c['cpc_usd']        ?? 0.0,
                ':cpm_usd'        => $c['cpm_usd']        ?? 0.0,
                ':roas'           => $c['roas']           ?? 0.0,
                ':av_revenue_usd' => $c['av_revenue_usd'] ?? 0.0,
                ':av_impressions' => $c['av_impressions'] ?? 0,
                ':av_sessions'    => $c['av_sessions']    ?? 0,
            ]);
        }
    }

    /**
     * Upsert apenas do lado ActiveView (domainExtract.php), preservando os campos
     * do lado Meta (campaign_name, status, spend_usd, etc.) se a linha já existir.
     *
     * @param array $campaigns [['campaign_id', 'av_revenue_usd', 'av_impressions', 'av_sessions'], ...]
     */
    public function upsertActiveViewMetrics(
        int    $executionId,
        string $date,
        string $accountKey,
        string $domain,
        array  $campaigns
    ): int {
        if (empty($campaigns)) {
            return 0;
        }

        $pdo = Connection::getInstance();

        $sql = "INSERT INTO campaign_reports
                    (execution_id, report_date, campaign_id, account_key, domain,
                     campaign_name, status, av_revenue_usd, av_impressions, av_sessions, roas)
                VALUES
                    (:execution_id, :report_date, :campaign_id, :account_key, :domain,
                     '', '', :av_revenue_usd, :av_impressions, :av_sessions, 0)
                ON DUPLICATE KEY UPDATE
                    domain         = VALUES(domain),
                    av_revenue_usd = VALUES(av_revenue_usd),
                    av_impressions = VALUES(av_impressions),
                    av_sessions    = VALUES(av_sessions),
                    roas           = IF(spend_usd > 0,
                                         ROUND((VALUES(av_revenue_usd) - spend_usd) / spend_usd, 4),
                                         0)";

        $stmt  = $pdo->prepare($sql);
        $count = 0;

        foreach ($campaigns as $c) {
            $stmt->execute([
                ':execution_id'   => $executionId,
                ':report_date'    => $date,
                ':campaign_id'    => $c['campaign_id'],
                ':account_key'    => $accountKey,
                ':domain'         => $domain,
                ':av_revenue_usd' => $c['av_revenue_usd'] ?? 0.0,
                ':av_impressions' => $c['av_impressions'] ?? 0,
                ':av_sessions'    => $c['av_sessions']    ?? 0,
            ]);
            $count++;
        }

        return $count;
    }

    public function query(array $filters): array
    {
        $where  = [];
        $params = [];

        if (!empty($filters['account_key'])) {
            $where[]              = 'account_key = :account_key';
            $params[':account_key'] = $filters['account_key'];
        } elseif (!empty($filters['account_keys'])) {
            [$inClause, $inParams] = $this->buildInClause('ak', $filters['account_keys']);
            $where[] = "account_key IN ({$inClause})";
            $params  = array_merge($params, $inParams);
        }
        if (!empty($filters['campaign_name'])) {
            $safe                     = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $filters['campaign_name']);
            $where[]                  = 'campaign_name LIKE :campaign_name';
            $params[':campaign_name'] = '%' . $safe . '%';
        }
        if (!empty($filters['start_date'])) {
            $where[]               = 'report_date >= :start_date';
            $params[':start_date'] = $filters['start_date'];
        }
        if (!empty($filters['end_date'])) {
            $where[]             = 'report_date <= :end_date';
            $params[':end_date'] = $filters['end_date'];
        }

        $whereClause = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

        $sql = "SELECT * FROM campaign_reports {$whereClause}
                ORDER BY report_date DESC, spend_usd DESC
                LIMIT 2000";

        $stmt = Connection::getInstance()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function fetchByPeriod(string $accountKey, string $startDate, string $endDate): array
    {
        $stmt = Connection::getInstance()->prepare(
            'SELECT * FROM campaign_reports
             WHERE account_key = :account_key
               AND report_date BETWEEN :start_date AND :end_date
             ORDER BY campaign_id, report_date ASC'
        );
        $stmt->execute([
            ':account_key' => $accountKey,
            ':start_date'  => $startDate,
            ':end_date'    => $endDate,
        ]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * @param array|null $allowedAccountKeys Quando não-null, restringe o resultado a essas
     *                                        contas (usado para usuários não-admin, que só
     *                                        podem ver as contas vinculadas a eles).
     */
    public function getOverviewMetrics(
        string $accountKey,
        int    $days = 30,
        ?array $allowedAccountKeys = null,
        ?string $startDate = null,
        ?string $endDate = null
    ): array {
        // Período dinâmico dos últimos $days dias, mas sempre encerrando ontem —
        // hoje nunca aparece pois o sync roda de manhã para o dia anterior.
        // $startDate/$endDate (filtro de data personalizado) têm prioridade sobre $days.
        $end    = $endDate   ?? date('Y-m-d', strtotime('-1 day'));
        $cutoff = $startDate ?? date('Y-m-d', strtotime("-{$days} days"));

        $where  = ['report_date BETWEEN :cutoff AND :end'];
        $params = [':cutoff' => $cutoff, ':end' => $end];
        if ($accountKey !== '') {
            $where[]              = 'account_key = :account_key';
            $params[':account_key'] = $accountKey;
        } elseif ($allowedAccountKeys !== null) {
            [$inClause, $inParams] = $this->buildInClause('ov', $allowedAccountKeys);
            $where[] = "account_key IN ({$inClause})";
            $params  = array_merge($params, $inParams);
        }
        $whereClause = implode(' AND ', $where);

        $stmt = Connection::getInstance()->prepare(
            "SELECT
                COUNT(DISTINCT campaign_id)  AS campaigns,
                SUM(impressions)             AS impressions,
                SUM(clicks)                 AS clicks,
                SUM(spend_usd)              AS spend_usd,
                SUM(av_revenue_usd)         AS av_revenue_usd,
                SUM(av_sessions)            AS av_sessions,
                ROUND((SUM(av_revenue_usd) - SUM(spend_usd)) / NULLIF(SUM(spend_usd), 0), 4) AS roas
             FROM campaign_reports
             WHERE {$whereClause}"
        );
        $stmt->execute($params);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * @param array|null $allowedAccountKeys Quando não-null, restringe o resultado a essas
     *                                        contas (usado para usuários não-admin).
     */
    public function getTopByRoas(
        int    $days = 30,
        int    $limit = 20,
        string $accountKey = '',
        ?array $allowedAccountKeys = null,
        ?string $startDate = null,
        ?string $endDate = null
    ): array {
        $end    = $endDate   ?? date('Y-m-d', strtotime('-1 day'));
        $cutoff = $startDate ?? date('Y-m-d', strtotime("-{$days} days"));

        $where  = ['report_date BETWEEN :cutoff AND :end', 'spend_usd > 0'];
        $params = [':cutoff' => $cutoff, ':end' => $end];
        if ($accountKey !== '') {
            $where[]              = 'account_key = :account_key';
            $params[':account_key'] = $accountKey;
        } elseif ($allowedAccountKeys !== null) {
            [$inClause, $inParams] = $this->buildInClause('tr', $allowedAccountKeys);
            $where[] = "account_key IN ({$inClause})";
            $params  = array_merge($params, $inParams);
        }
        $whereClause = implode(' AND ', $where);

        $stmt = Connection::getInstance()->prepare(
            "SELECT
                campaign_id,
                campaign_name,
                account_key,
                SUM(spend_usd)      AS spend_usd,
                SUM(av_revenue_usd) AS av_revenue_usd,
                SUM(impressions)    AS impressions,
                SUM(clicks)        AS clicks,
                SUM(av_sessions)   AS av_sessions,
                ROUND((SUM(av_revenue_usd) - SUM(spend_usd)) / NULLIF(SUM(spend_usd), 0), 4) AS roas
             FROM campaign_reports
             WHERE {$whereClause}
             GROUP BY campaign_id, campaign_name, account_key
             ORDER BY roas DESC
             LIMIT :limit"
        );
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Série diária de gasto x receita (pro gráfico do dashboard).
     *
     * @param array|null $allowedAccountKeys Quando não-null, restringe o resultado a essas
     *                                        contas (usado para usuários não-admin).
     */
    public function getDailySeries(
        string  $accountKey,
        int     $days = 30,
        ?array  $allowedAccountKeys = null,
        ?string $startDate = null,
        ?string $endDate = null
    ): array {
        $end    = $endDate   ?? date('Y-m-d', strtotime('-1 day'));
        $cutoff = $startDate ?? date('Y-m-d', strtotime("-{$days} days"));

        $where  = ['report_date BETWEEN :cutoff AND :end'];
        $params = [':cutoff' => $cutoff, ':end' => $end];
        if ($accountKey !== '') {
            $where[]              = 'account_key = :account_key';
            $params[':account_key'] = $accountKey;
        } elseif ($allowedAccountKeys !== null) {
            [$inClause, $inParams] = $this->buildInClause('ds', $allowedAccountKeys);
            $where[] = "account_key IN ({$inClause})";
            $params  = array_merge($params, $inParams);
        }
        $whereClause = implode(' AND ', $where);

        $stmt = Connection::getInstance()->prepare(
            "SELECT
                report_date,
                SUM(spend_usd)      AS spend_usd,
                SUM(av_revenue_usd) AS av_revenue_usd,
                ROUND((SUM(av_revenue_usd) - SUM(spend_usd)) / NULLIF(SUM(spend_usd), 0), 4) AS roas
             FROM campaign_reports
             WHERE {$whereClause}
             GROUP BY report_date
             ORDER BY report_date ASC"
        );
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function buildInClause(string $prefix, array $values): array
    {
        $placeholders = [];
        $params       = [];
        foreach (array_values($values) as $i => $value) {
            $key                = ":{$prefix}{$i}";
            $placeholders[]     = $key;
            $params[$key]       = $value;
        }
        return [implode(',', $placeholders), $params];
    }

    public function getMissingDates(string $accountKey, int $lookbackDays): array
    {
        $cutoff = date('Y-m-d', strtotime("-{$lookbackDays} days"));

        // campaign_name só é vazio em linhas criadas por domainExtract.php (lado
        // ActiveView) antes do lado Meta rodar — não conta como "já sincronizado",
        // senão o sync.php nunca mais preenche spend_usd/campaign_name para o dia.
        $existing = [];
        $stmt = Connection::getInstance()->prepare(
            "SELECT DISTINCT report_date FROM campaign_reports
             WHERE account_key = :account_key
               AND report_date >= :cutoff
               AND campaign_name != ''"
        );
        $stmt->execute([
            ':account_key' => $accountKey,
            ':cutoff'      => $cutoff,
        ]);
        foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $d) {
            $existing[$d] = true;
        }

        $missing = [];
        for ($i = $lookbackDays; $i >= 1; $i--) {
            $date = date('Y-m-d', strtotime("-{$i} days"));
            if (!isset($existing[$date])) {
                $missing[] = $date;
            }
        }

        return $missing;
    }
}
