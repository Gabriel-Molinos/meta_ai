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
                    roas           = VALUES(roas),
                    av_revenue_usd = VALUES(av_revenue_usd),
                    av_impressions = VALUES(av_impressions),
                    av_sessions    = VALUES(av_sessions)';

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

    public function query(array $filters): array
    {
        $where  = [];
        $params = [];

        if (!empty($filters['account_key'])) {
            $where[]              = 'account_key = :account_key';
            $params[':account_key'] = $filters['account_key'];
        }
        if (!empty($filters['campaign_name'])) {
            $safe                     = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $filters['campaign_name']);
            $where[]                  = "campaign_name LIKE :campaign_name ESCAPE '\\'";
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

    public function getOverviewMetrics(string $accountKey, int $days = 30): array
    {
        $cutoff = date('Y-m-d', strtotime("-{$days} days"));

        $stmt = Connection::getInstance()->prepare(
            "SELECT
                COUNT(DISTINCT campaign_id)  AS campaigns,
                SUM(impressions)             AS impressions,
                SUM(clicks)                 AS clicks,
                SUM(spend_usd)              AS spend_usd,
                SUM(av_revenue_usd)         AS av_revenue_usd,
                SUM(av_sessions)            AS av_sessions,
                ROUND(SUM(av_revenue_usd) / NULLIF(SUM(spend_usd), 0), 4) AS roas
             FROM campaign_reports
             WHERE account_key = :account_key
               AND report_date >= :cutoff"
        );
        $stmt->execute([
            ':account_key' => $accountKey,
            ':cutoff'      => $cutoff,
        ]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    }

    public function getTopByRoas(int $days = 30, int $limit = 20): array
    {
        $cutoff = date('Y-m-d', strtotime("-{$days} days"));

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
                ROUND(SUM(av_revenue_usd) / NULLIF(SUM(spend_usd), 0), 4) AS roas
             FROM campaign_reports
             WHERE report_date >= :cutoff
               AND spend_usd > 0
             GROUP BY campaign_id, campaign_name, account_key
             HAVING roas > 0
             ORDER BY roas DESC
             LIMIT :limit"
        );
        $stmt->bindValue(':cutoff', $cutoff);
        $stmt->bindValue(':limit',  $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getMissingDates(string $accountKey, int $lookbackDays): array
    {
        $cutoff = date('Y-m-d', strtotime("-{$lookbackDays} days"));

        $existing = [];
        $stmt = Connection::getInstance()->prepare(
            "SELECT DISTINCT report_date FROM campaign_reports
             WHERE account_key = :account_key
               AND report_date >= :cutoff"
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
