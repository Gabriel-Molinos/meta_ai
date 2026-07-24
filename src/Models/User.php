<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database\Connection;
use PDO;

class User
{
    public function findByEmail(string $email): ?array
    {
        $stmt = Connection::getInstance()->prepare(
            'SELECT * FROM users WHERE email = ? LIMIT 1'
        );
        $stmt->execute([$email]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function findBySessionToken(string $token): ?array
    {
        $stmt = Connection::getInstance()->prepare(
            'SELECT * FROM users WHERE session_token = ? AND token_expires_at > NOW() LIMIT 1'
        );
        $stmt->execute([$token]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function upsert(string $email, string $name, string $googleId): int
    {
        $pdo = Connection::getInstance();
        $pdo->prepare(
            'INSERT INTO users (email, name, google_id)
             VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE
               name       = VALUES(name),
               google_id  = VALUES(google_id),
               updated_at = NOW()'
        )->execute([$email, $name, $googleId]);

        return (int) $this->findByEmail($email)['id'];
    }

    public function setSessionToken(int $userId, string $token): void
    {
        $expires = (new \DateTimeImmutable('+24 hours'))->format('Y-m-d H:i:s');
        Connection::getInstance()->prepare(
            'UPDATE users SET session_token = ?, token_expires_at = ?, updated_at = NOW() WHERE id = ?'
        )->execute([$token, $expires, $userId]);
    }

    public function clearSessionToken(string $token): void
    {
        Connection::getInstance()->prepare(
            'UPDATE users SET session_token = NULL, token_expires_at = NULL WHERE session_token = ?'
        )->execute([$token]);
    }

    public function all(): array
    {
        $stmt = Connection::getInstance()->query(
            'SELECT u.id, u.email, u.name, u.role,
                    u.token_expires_at, u.created_at,
                    GROUP_CONCAT(a.label   ORDER BY a.label SEPARATOR \', \') AS account_labels,
                    GROUP_CONCAT(a.id      ORDER BY a.id    SEPARATOR \',\')  AS account_ids
             FROM users u
             LEFT JOIN user_accounts ua ON ua.user_id = u.id
             LEFT JOIN accounts a ON a.id = ua.account_id
             GROUP BY u.id
             ORDER BY u.email'
        );
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function setAccounts(int $userId, array $accountIds): void
    {
        $pdo = Connection::getInstance();
        $pdo->prepare('DELETE FROM user_accounts WHERE user_id = ?')->execute([$userId]);
        if (empty($accountIds)) {
            return;
        }
        $stmt = $pdo->prepare('INSERT IGNORE INTO user_accounts (user_id, account_id) VALUES (?, ?)');
        foreach ($accountIds as $accountId) {
            $stmt->execute([$userId, (int) $accountId]);
        }
    }

    public function getAccounts(int $userId): array
    {
        $stmt = Connection::getInstance()->prepare(
            'SELECT a.id, a.account_key, a.label, a.meta_account_id, a.active
             FROM user_accounts ua
             JOIN accounts a ON a.id = ua.account_id
             WHERE ua.user_id = ?
             ORDER BY a.label'
        );
        $stmt->execute([$userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function delete(int $userId): void
    {
        Connection::getInstance()->prepare('DELETE FROM users WHERE id = ?')->execute([$userId]);
    }
}
