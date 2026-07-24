<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Http\Request;
use App\Core\Http\Response;
use App\Models\User;

class AuthController
{
    private const GOOGLE_AUTH_URL     = 'https://accounts.google.com/o/oauth2/v2/auth';
    private const GOOGLE_TOKEN_URL    = 'https://oauth2.googleapis.com/token';
    private const GOOGLE_USERINFO_URL = 'https://www.googleapis.com/oauth2/v3/userinfo';

    public function __construct(
        private readonly string $validApiKey,
        private readonly string $clientId,
        private readonly string $clientSecret,
        private readonly string $redirectUri,
    ) {}

    public function showLogin(): void
    {
        $error = htmlspecialchars($_GET['error'] ?? '');
        require __DIR__ . '/../../public/views/login.php';
        exit;
    }

    public function redirectToGoogle(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $state = bin2hex(random_bytes(16));
        $_SESSION['oauth_state'] = $state;

        $url = self::GOOGLE_AUTH_URL . '?' . http_build_query([
            'client_id'     => $this->clientId,
            'redirect_uri'  => $this->redirectUri,
            'response_type' => 'code',
            'scope'         => 'openid email profile',
            'state'         => $state,
            'access_type'   => 'online',
            'prompt'        => 'select_account',
        ]);
        header('Location: ' . $url);
        exit;
    }

    public function handleCallback(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if (!empty($_GET['error'])) {
            header('Location: /login?error=' . urlencode('Acesso negado pelo Google.'));
            exit;
        }

        $code        = $_GET['code']  ?? '';
        $stateGiven  = $_GET['state'] ?? '';
        $stateStored = $_SESSION['oauth_state'] ?? '';

        if (!$code || !$stateGiven || !hash_equals($stateStored, $stateGiven)) {
            header('Location: /login?error=' . urlencode('Parâmetros inválidos. Tente novamente.'));
            exit;
        }
        unset($_SESSION['oauth_state']);

        $tokenData = $this->exchangeCode($code);
        if (empty($tokenData['access_token'])) {
            header('Location: /login?error=' . urlencode('Falha ao obter token do Google.'));
            exit;
        }

        $userInfo = $this->getUserInfo($tokenData['access_token']);
        if (empty($userInfo['email'])) {
            header('Location: /login?error=' . urlencode('Não foi possível obter o e-mail da conta Google.'));
            exit;
        }

        $model  = new User();
        $userId = $model->upsert($userInfo['email'], $userInfo['name'] ?? '', $userInfo['sub'] ?? '');
        $token  = bin2hex(random_bytes(32));
        $model->setSessionToken($userId, $token);

        setcookie('_auth', urlencode($token), [
            'expires'  => time() + 86400,
            'path'     => '/',
            'httponly' => true,
            'samesite' => 'Lax',
        ]);

        header('Location: /generator');
        exit;
    }

    public function adminLogin(Request $request, array $params): never
    {
        $password = $request->input('password', '');
        if ($password === '' || !hash_equals($this->validApiKey, $password)) {
            Response::error('Credenciais inválidas', 401);
        }
        setcookie('_auth', urlencode($this->validApiKey), [
            'expires'  => time() + 86400 * 7,
            'path'     => '/',
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        Response::json(['status' => 'ok']);
    }

    public function logout(Request $request, array $params): never
    {
        $cookie = isset($_COOKIE['_auth']) ? urldecode($_COOKIE['_auth']) : '';
        if ($cookie !== '') {
            try {
                (new User())->clearSessionToken($cookie);
            } catch (\Throwable) {}
        }
        setcookie('_auth', '', ['expires' => time() - 3600, 'path' => '/']);
        header('Location: /login');
        exit;
    }

    private function exchangeCode(string $code): array
    {
        $ch = curl_init(self::GOOGLE_TOKEN_URL);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => PHP_OS_FAMILY !== 'Windows',
            CURLOPT_POSTFIELDS     => http_build_query([
                'code'          => $code,
                'client_id'     => $this->clientId,
                'client_secret' => $this->clientSecret,
                'redirect_uri'  => $this->redirectUri,
                'grant_type'    => 'authorization_code',
            ]),
        ]);
        $body = (string) curl_exec($ch);
        curl_close($ch);
        return (array) (json_decode($body, true) ?? []);
    }

    private function getUserInfo(string $accessToken): array
    {
        $ch = curl_init(self::GOOGLE_USERINFO_URL);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => PHP_OS_FAMILY !== 'Windows',
            CURLOPT_HTTPHEADER     => ['Authorization: Bearer ' . $accessToken],
        ]);
        $body = (string) curl_exec($ch);
        curl_close($ch);
        return (array) (json_decode($body, true) ?? []);
    }
}
