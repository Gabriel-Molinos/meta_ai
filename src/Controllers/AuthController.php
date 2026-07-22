<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Http\Request;
use App\Core\Http\Response;

class AuthController
{
    public function __construct(private readonly string $validApiKey) {}

    public function login(Request $request, array $params): never
    {
        $password = $request->input('password', '');

        if ($password === '' || !hash_equals($this->validApiKey, $password)) {
            Response::error('Credenciais inválidas', 401);
        }

        setcookie('_auth', $this->validApiKey, [
            'expires'  => time() + 86400 * 7,
            'path'     => '/',
            'httponly' => true,
            'samesite' => 'Lax',
        ]);

        Response::json(['status' => 'ok']);
    }

    public function logout(Request $request, array $params): never
    {
        setcookie('_auth', '', ['expires' => time() - 3600, 'path' => '/']);
        header('Location: /login');
        exit;
    }
}
