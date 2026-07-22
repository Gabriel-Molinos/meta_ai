<?php

declare(strict_types=1);

namespace App\Core\Database;

use PDO;
use PDOStatement;

class Connection
{
    private static ?PDO   $instance = null;
    private static array  $config   = [];

    public static function configure(array $config): void
    {
        self::$config = $config;
    }

    public static function getInstance(): PDO
    {
        if (self::$instance === null) {
            $path = self::$config['path'];

            $dir = dirname($path);
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }

            $dsn = 'sqlite:' . $path;

            self::$instance = new PDO($dsn, null, null, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);

            self::$instance->exec('PRAGMA journal_mode=WAL; PRAGMA foreign_keys=ON;');
        }

        return self::$instance;
    }

    public static function query(string $sql, array $params = []): PDOStatement
    {
        $stmt = self::getInstance()->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    public static function execute(string $sql, array $params = []): string|int
    {
        $pdo  = self::getInstance();
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        return str_starts_with(ltrim(strtoupper($sql)), 'INSERT')
            ? $pdo->lastInsertId()
            : $stmt->rowCount();
    }
}
