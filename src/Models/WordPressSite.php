<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database\Connection;
use App\Core\Encryption\Encryptor;
use PDO;

class WordPressSite
{
    public function __construct(private readonly Encryptor $enc) {}

    public function all(): array
    {
        $stmt = Connection::getInstance()->query(
            'SELECT ws.*, a.label AS account_label
             FROM wordpress_sites ws
             LEFT JOIN accounts a ON a.id = ws.account_id
             ORDER BY ws.label'
        );
        return array_map(fn($row) => $this->toPublic($row), $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    public function allForUser(int $userId): array
    {
        $stmt = Connection::getInstance()->prepare(
            'SELECT ws.*, a.label AS account_label
             FROM wordpress_sites ws
             JOIN accounts a ON a.id = ws.account_id
             JOIN user_accounts ua ON ua.account_id = ws.account_id
             WHERE ua.user_id = :user_id
             ORDER BY ws.label'
        );
        $stmt->execute([':user_id' => $userId]);
        return array_map(fn($row) => $this->toPublic($row), $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    public function find(int $id): ?array
    {
        $stmt = Connection::getInstance()->prepare(
            'SELECT * FROM wordpress_sites WHERE id = :id LIMIT 1'
        );
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function create(array $data): int
    {
        $stmt = Connection::getInstance()->prepare(
            'INSERT INTO wordpress_sites (label, url, wp_username, wp_app_password, account_id)
             VALUES (:label, :url, :wp_username, :wp_app_password, :account_id)'
        );
        $stmt->execute([
            ':label'           => $data['label'],
            ':url'             => rtrim($data['url'], '/'),
            ':wp_username'     => $data['wp_username'],
            ':wp_app_password' => $this->enc->encrypt($data['wp_app_password']),
            ':account_id'      => isset($data['account_id']) && $data['account_id'] !== '' ? (int) $data['account_id'] : null,
        ]);
        return (int) Connection::getInstance()->lastInsertId();
    }

    public function update(int $id, array $data): void
    {
        $fields = [];
        $params = [':id' => $id];

        if (isset($data['label'])) {
            $fields[] = 'label = :label';
            $params[':label'] = $data['label'];
        }
        if (isset($data['url'])) {
            $fields[] = 'url = :url';
            $params[':url'] = rtrim($data['url'], '/');
        }
        if (isset($data['wp_username'])) {
            $fields[] = 'wp_username = :wp_username';
            $params[':wp_username'] = $data['wp_username'];
        }
        if (!empty($data['wp_app_password'])) {
            $fields[] = 'wp_app_password = :wp_app_password';
            $params[':wp_app_password'] = $this->enc->encrypt($data['wp_app_password']);
        }
        if (array_key_exists('account_id', $data)) {
            $fields[] = 'account_id = :account_id';
            $params[':account_id'] = isset($data['account_id']) && $data['account_id'] !== '' ? (int) $data['account_id'] : null;
        }

        if (empty($fields)) {
            return;
        }

        Connection::getInstance()->prepare(
            'UPDATE wordpress_sites SET ' . implode(', ', $fields) . ' WHERE id = :id'
        )->execute($params);
    }

    public function delete(int $id): void
    {
        Connection::getInstance()->prepare(
            'DELETE FROM wordpress_sites WHERE id = :id'
        )->execute([':id' => $id]);
    }

    public function toPublic(array $row): array
    {
        unset($row['wp_app_password']);
        if (isset($row['account_id'])) {
            $row['account_id'] = $row['account_id'] !== null ? (int) $row['account_id'] : null;
        }
        return $row;
    }

    public function toConfig(array $row): array
    {
        $row['wp_app_password'] = $this->enc->decrypt($row['wp_app_password']);
        return $row;
    }
}
