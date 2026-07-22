<?php

declare(strict_types=1);

namespace App\Core\Database;

use PDO;
use PDOStatement;

class Connection
{
    private static ?PDO  $instance = null;
    private static array $config   = [];

    public static function configure(array $config): void
    {
        self::$config = $config;
    }

    public static function getInstance(): PDO
    {
        if (self::$instance === null) {
            $c = self::$config;

            $dsn = sprintf(
                'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
                $c['host'], $c['port'], $c['dbname']
            );

            $options = [
                PDO::ATTR_ERRMODE              => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE   => PDO::FETCH_ASSOC,
                PDO::MYSQL_ATTR_INIT_COMMAND   => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci",
                PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT => false,
            ];

            if (!empty($c['ssl_ca'])) {
                $options[PDO::MYSQL_ATTR_SSL_CA] = $c['ssl_ca'];
            }

            self::$instance = new PDO($dsn, $c['username'], $c['password'], $options);
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
