<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Database\Connection;
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

    /**
     * Valida admin (API key) ou usuário OAuth (session_token).
     * Retorna ['type'=>'admin','email'=>null] ou ['type'=>'user','user_id'=>int,'email'=>string].
     */
    public function resolveContext(Request $request): array
    {
        $token = $request->getBearerToken();

        if ($token === '') {
            Response::error('Unauthorized', 401);
        }

        // Admin via API key
        if (hash_equals($this->validApiKey, $token)) {
            return ['type' => 'admin', 'email' => null];
        }

        // Usuário OAuth via session_token
        try {
            $stmt = Connection::getInstance()->prepare(
                'SELECT id, email FROM users WHERE session_token = ? AND token_expires_at > NOW() LIMIT 1'
            );
            $stmt->execute([$token]);
            $row = $stmt->fetch(\PDO::FETCH_ASSOC);
            if ($row) {
                return ['type' => 'user', 'user_id' => (int) $row['id'], 'email' => $row['email']];
            }
        } catch (\Throwable) {}

        Response::error('Unauthorized', 401);
        return []; // unreachable
    }

    /**
     * Aceita admin ou usuário OAuth — não retorna contexto, apenas valida.
     */
    public function handleAny(Request $request): void
    {
        $this->resolveContext($request);
    }
}
