<?php
declare(strict_types=1);
require_once __DIR__ . '/../vendor/autoload.php';
$config = require __DIR__ . '/../config/config.php';
use App\Core\Database\Connection;
use App\Core\Encryption\Encryptor;
use App\Models\Account;
Connection::configure($config['database']);
$enc   = new Encryptor($config['encryption_key']);
$model = new Account($enc);
$rows  = $model->allForSync();
foreach ($rows as $r) {
    echo $r['account_key'] . ' => ' . $r['meta_ads']['account_id'] . "\n";
}
