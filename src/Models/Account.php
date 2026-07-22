<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database\Connection;
use App\Core\Encryption\Encryptor;
use PDO;

class Account
{
    private const SENSITIVE = ['meta_app_secret', 'meta_access_token', 'av_api_key'];

    public function __construct(private readonly Encryptor $enc) {}

    public function all(): array
    {
        $stmt = Connection::getInstance()->query('SELECT * FROM accounts ORDER BY label');
        return array_map(fn($row) => $this->toPublic($row), $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    public function find(string $key): ?array
    {
        $stmt = Connection::getInstance()->prepare(
            'SELECT * FROM accounts WHERE account_key = :key LIMIT 1'
        );
        $stmt->execute([':key' => $key]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function allForSync(): array
    {
        $stmt = Connection::getInstance()->query(
            'SELECT * FROM accounts WHERE active = 1 ORDER BY label'
        );
        return array_map(fn($row) => $this->toConfig($row), $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    public function create(array $data): void
    {
        foreach (self::SENSITIVE as $field) {
            if (!empty($data[$field])) {
                $data[$field] = $this->enc->encrypt($data[$field]);
            }
        }

        $stmt = Connection::getInstance()->prepare(
            'INSERT INTO accounts
                (account_key, label, meta_app_id, meta_app_secret, meta_access_token,
                 meta_account_id, av_base_url, av_api_key, av_network_code, av_domain,
                 av_timeout, nicho)
             VALUES
                (:account_key, :label, :meta_app_id, :meta_app_secret, :meta_access_token,
                 :meta_account_id, :av_base_url, :av_api_key, :av_network_code, :av_domain,
                 :av_timeout, :nicho)'
        );

        $stmt->execute($this->buildParams($data));
    }

    public function update(string $key, array $data, array $existing): void
    {
        foreach (self::SENSITIVE as $field) {
            $data[$field] = !empty($data[$field])
                ? $this->enc->encrypt($data[$field])
                : $existing[$field];
        }

        $stmt = Connection::getInstance()->prepare(
            'UPDATE accounts SET
                label             = :label,
                meta_app_id       = :meta_app_id,
                meta_app_secret   = :meta_app_secret,
                meta_access_token = :meta_access_token,
                meta_account_id   = :meta_account_id,
                av_base_url       = :av_base_url,
                av_api_key        = :av_api_key,
                av_network_code   = :av_network_code,
                av_domain         = :av_domain,
                av_timeout        = :av_timeout,
                nicho             = :nicho,
                active            = :active,
                updated_at        = NOW()
             WHERE account_key = :account_key'
        );

        $params               = $this->buildParams($data);
        $params[':account_key'] = $key;
        $params[':active']      = (int) ($data['active'] ?? 1);
        $stmt->execute($params);
    }

    public function delete(string $key): void
    {
        $stmt = Connection::getInstance()->prepare(
            'DELETE FROM accounts WHERE account_key = :key'
        );
        $stmt->execute([':key' => $key]);
    }

    public function toConfig(array $row): array
    {
        return [
            'account_key' => $row['account_key'],
            'meta_ads'    => [
                'app_id'       => $row['meta_app_id'],
                'app_secret'   => $this->decryptSafe($row['meta_app_secret']),
                'access_token' => $this->decryptSafe($row['meta_access_token']),
                'account_id'   => $row['meta_account_id'],
                'api_version'  => 'v22.0',
                'timeout'      => 60,
            ],
            'active_view' => [
                'base_url'     => $row['av_base_url'],
                'api_key'      => $this->decryptSafe($row['av_api_key']),
                'network_code' => $row['av_network_code'],
                'domain'       => $row['av_domain'],
                'timeout'      => (int) $row['av_timeout'],
            ],
        ];
    }

    private function decryptSafe(string $value): string
    {
        return $value !== '' ? $this->enc->decrypt($value) : '';
    }

    public function toPublic(array $row): array
    {
        return [
            'id'              => (int) $row['id'],
            'account_key'     => $row['account_key'],
            'label'           => $row['label'],
            'meta_app_id'     => $row['meta_app_id'],
            'meta_account_id' => $row['meta_account_id'],
            'av_base_url'     => $row['av_base_url'],
            'av_network_code' => $row['av_network_code'],
            'av_domain'       => $row['av_domain'],
            'av_timeout'      => (int) $row['av_timeout'],
            'nicho'           => $row['nicho'] ?? null,
            'active'          => (bool) $row['active'],
            'created_at'      => $row['created_at'],
            'updated_at'      => $row['updated_at'],
        ];
    }

    private function buildParams(array $data): array
    {
        return [
            ':account_key'     => $data['account_key'] ?? '',
            ':label'           => $data['label'],
            ':meta_app_id'     => $data['meta_app_id']       ?? '',
            ':meta_app_secret' => $data['meta_app_secret']   ?? '',
            ':meta_access_token' => $data['meta_access_token'] ?? '',
            ':meta_account_id' => $data['meta_account_id']   ?? '',
            ':av_base_url'     => $data['av_base_url']       ?? 'https://external-api.activeview.app',
            ':av_api_key'      => $data['av_api_key']        ?? '',
            ':av_network_code' => $data['av_network_code']   ?? '',
            ':av_domain'       => $data['av_domain']         ?? '',
            ':av_timeout'      => (int) ($data['av_timeout'] ?? 30),
            ':nicho'           => $data['nicho']             ?? null,
        ];
    }
}
