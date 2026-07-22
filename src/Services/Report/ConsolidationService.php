<?php

declare(strict_types=1);

namespace App\Services\Report;

class ConsolidationService
{
    /**
     * Cruza insights do Meta Ads com dados da ActiveView por campaign_id.
     *
     * @param array $insights  [campaign_id][date] => metrics
     * @param array $revenue   [campaign_id][] => {date, revenue_usd, impressions, ...}
     * @param array $sessions  [campaign_id][] => {date, sessions}
     * @param string $domain   domínio AV da conta
     * @param string $date     data de referência (YYYY-MM-DD)
     */
    public function consolidate(
        array  $insights,
        array  $revenue,
        array  $sessions,
        string $domain,
        string $date
    ): array {
        $result = [];

        foreach ($insights as $campaignId => $dates) {
            $dayData = $dates[$date] ?? null;

            if ($dayData === null) {
                continue;
            }

            $avRevenue    = $this->sumRevenueForDay($revenue[$campaignId] ?? [], $date);
            $avImpressions = $this->sumImpressionsForDay($revenue[$campaignId] ?? [], $date);
            $avSessions   = $this->sumSessionsForDay($sessions[$campaignId] ?? [], $date);

            $spendUsd = $dayData['spend_usd'] ?? 0.0;
            $roas     = $spendUsd > 0 ? round($avRevenue / $spendUsd, 4) : 0.0;

            $result[] = [
                'campaign_id'    => $campaignId,
                'campaign_name'  => $dayData['campaign_name'],
                'domain'         => $domain,
                'status'         => $dayData['status'] ?? '',
                'impressions'    => $dayData['impressions'],
                'clicks'         => $dayData['clicks'],
                'reach'          => $dayData['reach'],
                'spend_usd'      => $spendUsd,
                'ctr'            => $dayData['ctr'],
                'cpc_usd'        => $dayData['cpc_usd'],
                'cpm_usd'        => $dayData['cpm_usd'],
                'roas'           => $roas,
                'av_revenue_usd' => round($avRevenue, 4),
                'av_impressions' => $avImpressions,
                'av_sessions'    => $avSessions,
            ];
        }

        return $result;
    }

    private function sumRevenueForDay(array $rows, string $date): float
    {
        $total = 0.0;
        foreach ($rows as $row) {
            if (($row['date'] ?? '') === $date) {
                $total += (float) ($row['revenue_usd'] ?? 0);
            }
        }
        return $total;
    }

    private function sumImpressionsForDay(array $rows, string $date): int
    {
        $total = 0;
        foreach ($rows as $row) {
            if (($row['date'] ?? '') === $date) {
                $total += (int) ($row['impressions'] ?? 0);
            }
        }
        return $total;
    }

    private function sumSessionsForDay(array $rows, string $date): int
    {
        $total = 0;
        foreach ($rows as $row) {
            if (($row['date'] ?? '') === $date) {
                $total += (int) ($row['sessions'] ?? 0);
            }
        }
        return $total;
    }
}
