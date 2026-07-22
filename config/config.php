<?php

declare(strict_types=1);

$envFile = __DIR__ . '/../.env';
if (file_exists($envFile)) {
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        if (str_starts_with(trim($line), '#') || !str_contains($line, '=')) {
            continue;
        }
        [$key, $value] = explode('=', $line, 2);
        $_ENV[trim($key)] = trim($value);
    }
}

$env = fn(string $key, string $default = ''): string => (string) ($_ENV[$key] ?? getenv($key) ?: $default);

return [

    'debug'          => filter_var($env('APP_DEBUG', 'false'), FILTER_VALIDATE_BOOLEAN),
    'api_key'        => $env('APP_API_KEY'),
    'encryption_key' => $env('ENCRYPTION_KEY'),

    'curl' => [
        'ssl_verify' => PHP_OS_FAMILY !== 'Windows',
        'cainfo'     => 'C:/php/extras/cacert.pem',
    ],

    'meta_ads' => [
        'app_id'       => $env('META_APP_ID'),
        'app_secret'   => $env('META_APP_SECRET'),
        'access_token' => $env('META_ACCESS_TOKEN'),
        'account_id'   => $env('META_ACCOUNT_ID'),
        'api_version'  => 'v22.0',
        'timeout'      => 60,
    ],

    'active_view' => [
        'base_url'     => $env('AV_BASE_URL', 'https://external-api.activeview.app'),
        'api_key'      => $env('AV_API_KEY'),
        'network_code' => $env('AV_NETWORK_CODE'),
        'domain'       => $env('AV_DOMAIN'),
        'timeout'      => 100,
    ],

    'gemini' => [
        'api_key' => $env('GEMINI_API_KEY'),
        'model'   => $env('GEMINI_MODEL', 'gemini-2.5-flash'),
        'timeout' => 180,
    ],

    'redis' => [
        'host' => $env('REDIS_HOST', '127.0.0.1'),
        'port' => (int) $env('REDIS_PORT', '6379'),
    ],

    'database' => [
        'path' => __DIR__ . '/../database/meta.sqlite',
    ],

    'retry' => [
        'max_attempts'  => 3,
        'base_delay_ms' => 1000,
    ],

    'sync' => [
        'lookback_days' => 30,
    ],

];
