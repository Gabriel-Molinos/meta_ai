<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Http\Request;
use App\Core\Http\Response;

class AuthMiddleware
{
    public function __construct(private readonly string $validApiKey) {}

    public function handle(Request $request): void
    {
        $token = $request->getBearerToken();

        if ($token === '' || !hash_equals($this->validApiKey, $token)) {
            Response::error('Unauthorized', 401);
        }
    }
}
