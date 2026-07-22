<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database\Connection;

class ExecutionLog
{
    public function create(string $accountKey, string $startDate, string $endDate): int
    {
        return (int) Connection::execute(
            'INSERT INTO executions (account_key, start_date, end_date, status) VALUES (?, ?, ?, ?)',
            [$accountKey, $startDate, $endDate, 'running']
        );
    }

    public function complete(int $id, int $campaignsCount): void
    {
        Connection::execute(
            "UPDATE executions SET status = 'completed', campaigns_count = ?, completed_at = datetime('now') WHERE id = ?",
            [$campaignsCount, $id]
        );
    }

    public function fail(int $id, string $errorMessage): void
    {
        Connection::execute(
            "UPDATE executions SET status = 'failed', error_message = ?, completed_at = datetime('now') WHERE id = ?",
            [$errorMessage, $id]
        );
    }

    public function recent(int $limit = 20): array
    {
        $stmt = Connection::query(
            'SELECT * FROM executions ORDER BY id DESC LIMIT ?',
            [$limit]
        );
        return $stmt->fetchAll();
    }
}
