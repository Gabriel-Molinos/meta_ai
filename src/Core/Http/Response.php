<?php

declare(strict_types=1);

namespace App\Core\Http;

class Response
{
    public static function json(array $data, int $statusCode = 200): never
    {
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    public static function error(string $message, int $statusCode = 500): never
    {
        self::json(['status' => 'error', 'message' => $message], $statusCode);
    }
}
