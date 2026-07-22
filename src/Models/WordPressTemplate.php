<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database\Connection;
use PDO;

class WordPressTemplate
{
    public function findAll(): array
    {
        $stmt = Connection::getInstance()->query(
            'SELECT id, name, description, source_url, is_system, created_at
             FROM wordpress_templates
             ORDER BY is_system DESC, name ASC'
        );
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function find(int $id): ?array
    {
        $stmt = Connection::getInstance()->prepare(
            'SELECT * FROM wordpress_templates WHERE id = :id LIMIT 1'
        );
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function create(array $data): int
    {
        $stmt = Connection::getInstance()->prepare(
            'INSERT INTO wordpress_templates (name, description, html, source_url)
             VALUES (:name, :description, :html, :source_url)'
        );
        $stmt->execute([
            ':name'        => trim($data['name'] ?? ''),
            ':description' => trim($data['description'] ?? ''),
            ':html'        => isset($data['html']) && trim((string) $data['html']) !== ''
                                  ? trim((string) $data['html']) : null,
            ':source_url'  => isset($data['source_url']) && trim((string) $data['source_url']) !== ''
                                  ? trim((string) $data['source_url']) : null,
        ]);
        return (int) Connection::getInstance()->lastInsertId();
    }

    public function update(int $id, array $data): bool
    {
        $stmt = Connection::getInstance()->prepare(
            'UPDATE wordpress_templates
             SET name = :name, description = :description, html = :html, source_url = :source_url
             WHERE id = :id AND is_system = 0'
        );
        $stmt->execute([
            ':id'          => $id,
            ':name'        => trim($data['name'] ?? ''),
            ':description' => trim($data['description'] ?? ''),
            ':html'        => isset($data['html']) && trim((string) $data['html']) !== ''
                                  ? trim((string) $data['html']) : null,
            ':source_url'  => isset($data['source_url']) && trim((string) $data['source_url']) !== ''
                                  ? trim((string) $data['source_url']) : null,
        ]);
        return $stmt->rowCount() > 0;
    }

    public function delete(int $id): bool
    {
        $stmt = Connection::getInstance()->prepare(
            'DELETE FROM wordpress_templates WHERE id = :id AND is_system = 0'
        );
        $stmt->execute([':id' => $id]);
        return $stmt->rowCount() > 0;
    }
}
