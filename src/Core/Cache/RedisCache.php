<?php

declare(strict_types=1);

namespace App\Core\Cache;

use Predis\Client;
use Throwable;

class RedisCache
{
    private ?Client $client    = null;
    private bool    $available = true;

    public function __construct(private readonly array $config) {}

    public function get(string $key): mixed
    {
        try {
            $value = $this->connection()->get($key);
            return $value !== null ? json_decode($value, true) : null;
        } catch (Throwable) {
            $this->available = false;
            return null;
        }
    }

    public function set(string $key, mixed $value, int $ttlSeconds): void
    {
        if (!$this->available) {
            return;
        }

        try {
            $this->connection()->setex($key, $ttlSeconds, json_encode($value));
        } catch (Throwable) {
            $this->available = false;
        }
    }

    public function delete(string $key): void
    {
        if (!$this->available) {
            return;
        }

        try {
            $this->connection()->del([$key]);
        } catch (Throwable) {
            $this->available = false;
        }
    }

    private function connection(): Client
    {
        if ($this->client === null) {
            $this->client = new Client([
                'scheme'             => 'tcp',
                'host'               => $this->config['host'],
                'port'               => (int) $this->config['port'],
                'timeout'            => 1.5,
                'read_write_timeout' => 1.5,
            ]);
        }

        return $this->client;
    }
}
