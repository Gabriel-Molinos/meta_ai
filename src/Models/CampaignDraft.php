<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database\Connection;
use PDO;

class CampaignDraft
{
    public function create(int $userId, string $accountKey, array $payload, array $creatives): int
    {
        $pdo = Connection::getInstance();
        $pdo->prepare(
            'INSERT INTO campaign_drafts (user_id, account_key, payload, creatives)
             VALUES (?, ?, ?, ?)'
        )->execute([
            $userId,
            $accountKey,
            json_encode($payload, JSON_UNESCAPED_UNICODE),
            json_encode($creatives, JSON_UNESCAPED_UNICODE),
        ]);
        return (int) $pdo->lastInsertId();
    }

    public function findById(int $id): ?array
    {
        $stmt = Connection::getInstance()->prepare(
            'SELECT d.*, u.email AS user_email, u.name AS user_name
             FROM campaign_drafts d
             JOIN users u ON u.id = d.user_id
             WHERE d.id = ? LIMIT 1'
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return null;
        }
        $row['payload']         = json_decode($row['payload'], true) ?? [];
        $row['creatives']       = json_decode($row['creatives'] ?? '[]', true) ?? [];
        $row['rejected_fields'] = json_decode($row['rejected_fields'] ?? '[]', true) ?? [];
        $row['meta_ads']        = json_decode($row['meta_ads'] ?? '[]', true) ?? [];
        return $row;
    }

    public function allForAdmin(): array
    {
        $stmt = Connection::getInstance()->query(
            'SELECT d.id, d.account_key, d.status, d.created_at, d.reviewed_at,
                    d.rejection_reason, d.rejected_fields,
                    d.meta_campaign_id,
                    u.email AS user_email, u.name AS user_name,
                    JSON_UNQUOTE(JSON_EXTRACT(d.payload, \'$.campaign_name\')) AS campaign_name
             FROM campaign_drafts d
             JOIN users u ON u.id = d.user_id
             ORDER BY FIELD(d.status,\'pending\',\'rejected\',\'approved\'), d.created_at DESC'
        );
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as &$r) {
            $r['rejected_fields'] = json_decode($r['rejected_fields'] ?? '[]', true) ?? [];
        }
        return $rows;
    }

    public function byUser(int $userId): array
    {
        $stmt = Connection::getInstance()->prepare(
            'SELECT id, account_key, status, created_at, reviewed_at,
                    rejection_reason, rejected_fields, meta_campaign_id,
                    JSON_UNQUOTE(JSON_EXTRACT(payload, \'$.campaign_name\')) AS campaign_name
             FROM campaign_drafts
             WHERE user_id = ?
             ORDER BY created_at DESC'
        );
        $stmt->execute([$userId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as &$r) {
            $r['rejected_fields'] = json_decode($r['rejected_fields'] ?? '[]', true) ?? [];
        }
        return $rows;
    }

    public function markApproved(int $id, string $adminEmail, string $campaignId, string $adsetId, array $ads): void
    {
        Connection::getInstance()->prepare(
            'UPDATE campaign_drafts
             SET status = "approved", reviewed_by = ?, reviewed_at = NOW(),
                 meta_campaign_id = ?, meta_adset_id = ?, meta_ads = ?, updated_at = NOW()
             WHERE id = ?'
        )->execute([$adminEmail, $campaignId, $adsetId, json_encode($ads), $id]);
    }

    public function markRejected(int $id, string $adminEmail, string $reason, array $rejectedFields): void
    {
        Connection::getInstance()->prepare(
            'UPDATE campaign_drafts
             SET status = "rejected", reviewed_by = ?, reviewed_at = NOW(),
                 rejection_reason = ?, rejected_fields = ?, updated_at = NOW()
             WHERE id = ?'
        )->execute([$adminEmail, $reason, json_encode($rejectedFields), $id]);
    }

    public function resubmit(int $id, array $newPayload, array $newCreatives): void
    {
        Connection::getInstance()->prepare(
            'UPDATE campaign_drafts
             SET status = "pending", payload = ?, creatives = ?,
                 rejection_reason = NULL, rejected_fields = NULL,
                 reviewed_by = NULL, reviewed_at = NULL,
                 updated_at = NOW()
             WHERE id = ?'
        )->execute([
            json_encode($newPayload, JSON_UNESCAPED_UNICODE),
            json_encode($newCreatives, JSON_UNESCAPED_UNICODE),
            $id,
        ]);
    }

    public function countPending(): int
    {
        return (int) Connection::getInstance()
            ->query('SELECT COUNT(*) FROM campaign_drafts WHERE status = "pending"')
            ->fetchColumn();
    }

    public function belongsToUser(int $id, int $userId): bool
    {
        $stmt = Connection::getInstance()->prepare(
            'SELECT id FROM campaign_drafts WHERE id = ? AND user_id = ? LIMIT 1'
        );
        $stmt->execute([$id, $userId]);
        return (bool) $stmt->fetch();
    }
}
