<?php

declare(strict_types=1);

namespace App\Core\Http;

class Request
{
    private array $body;

    public function __construct(
        private readonly string $method,
        private readonly string $uri,
        private readonly array  $headers
    ) {
        $raw        = file_get_contents('php://input');
        $this->body = $raw ? (json_decode($raw, true) ?? []) : [];
    }

    public static function fromGlobals(): self
    {
        $method  = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
        $uri     = strtok($_SERVER['REQUEST_URI'] ?? '/', '?');
        $headers = self::extractHeaders();

        return new self($method, $uri, $headers);
    }

    public function getMethod(): string
    {
        return $this->method;
    }

    public function getUri(): string
    {
        return $this->uri;
    }

    public function getBody(): array
    {
        return $this->body;
    }

    public function input(string $key, mixed $default = null): mixed
    {
        return $this->body[$key] ?? $default;
    }

    public function getHeader(string $name): string
    {
        return $this->headers[strtolower($name)] ?? '';
    }

    public function getBearerToken(): string
    {
        $auth = $this->getHeader('authorization');

        if (str_starts_with($auth, 'Bearer ')) {
            return substr($auth, 7);
        }

        return '';
    }

    private static function extractHeaders(): array
    {
        $headers = [];

        foreach ($_SERVER as $key => $value) {
            if (str_starts_with($key, 'HTTP_')) {
                $name           = strtolower(str_replace('_', '-', substr($key, 5)));
                $headers[$name] = $value;
            }
        }

        return $headers;
    }
}
