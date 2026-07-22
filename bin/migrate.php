<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

$config = require __DIR__ . '/../config/config.php';

use App\Core\Database\Connection;

Connection::configure($config['database']);

$pdo        = Connection::getInstance();
$migrations = glob(__DIR__ . '/../database/migrations/*.sql');
sort($migrations);

foreach ($migrations as $file) {
    $sql  = file_get_contents($file);
    $name = basename($file);

    $statements = array_filter(
        array_map('trim', explode(';', $sql)),
        fn($s) => $s !== ''
    );

    try {
        foreach ($statements as $statement) {
            $pdo->exec($statement);
        }
        echo "[OK] {$name}\n";
    } catch (PDOException $e) {
        echo "[ERRO] {$name}: " . $e->getMessage() . "\n";
        exit(1);
    }
}

echo "Migrations concluidas.\n";
