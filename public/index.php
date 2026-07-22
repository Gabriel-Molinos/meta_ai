<?php

declare(strict_types=1);

ini_set('upload_max_filesize', '512M');
ini_set('post_max_size', '512M');
ini_set('max_execution_time', '300');

require_once __DIR__ . '/../vendor/autoload.php';

$config = require __DIR__ . '/../config/config.php';

if (($config['api_key'] ?? '') === '' || ($config['api_key'] ?? '') === 'troque-por-um-token-forte-aqui') {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'APP_API_KEY não configurado. Defina um token forte no arquivo .env']);
    exit(1);
}

use App\Controllers\AccountController;
use App\Controllers\AiController;
use App\Controllers\AuthController;
use App\Controllers\CampaignController;
use App\Controllers\CampaignGeneratorController;
use App\Core\Cache\RedisCache;
use App\Core\Database\Connection;
use App\Core\Encryption\Encryptor;
use App\Core\Http\Request;
use App\Core\Http\Response;
use App\Core\Http\Router;
use App\Core\HttpClient\HttpClient;
use App\Middleware\AuthMiddleware;
use App\Models\Account;
use App\Models\CampaignReport;
use App\Services\AI\GeminiService;

// ── Inicialização ─────────────────────────────────────────────────────────────
Connection::configure($config['database']);

$cache  = new RedisCache($config['redis']);
$http   = new HttpClient($config['retry'], $config['curl'] ?? []);
$enc    = new Encryptor($config['encryption_key']);
$auth   = new AuthMiddleware($config['api_key']);

$accountModel = new Account($enc);
$reportModel  = new CampaignReport();
$gemini       = new GeminiService($config['gemini'], $http);

// ── Controladores ─────────────────────────────────────────────────────────────
$accountCtrl   = new AccountController($accountModel, $auth);
$campaignCtrl  = new CampaignController($reportModel, $auth);
$aiCtrl        = new AiController($gemini, $reportModel, $auth);
$authCtrl      = new AuthController($config['api_key']);
$generatorCtrl = new CampaignGeneratorController($accountModel, $http, $auth);

// ── Roteador ──────────────────────────────────────────────────────────────────
$router  = new Router();
$request = Request::fromGlobals();

// API — Contas
$router->addRoute('GET',    '/api/accounts',          [$accountCtrl, 'index']);
$router->addRoute('POST',   '/api/accounts',          [$accountCtrl, 'store']);
$router->addRoute('PUT',    '/api/accounts/{key}',    [$accountCtrl, 'update']);
$router->addRoute('DELETE', '/api/accounts/{key}',    [$accountCtrl, 'destroy']);

// API — Campanhas / Dashboard
$router->addRoute('GET',  '/api/campaigns',  [$campaignCtrl, 'index']);
$router->addRoute('GET',  '/api/dashboard',  [$campaignCtrl, 'dashboard']);

// API — IA
$router->addRoute('POST', '/api/ai/analyze', [$aiCtrl, 'analyze']);
$router->addRoute('POST', '/api/ai/trend',   [$aiCtrl, 'trend']);

// API — Gerador de campanhas
$router->addRoute('GET',  '/api/generator/pixels',            [$generatorCtrl, 'pixels']);
$router->addRoute('GET',  '/api/generator/events',            [$generatorCtrl, 'events']);
$router->addRoute('GET',  '/api/generator/pages',             [$generatorCtrl, 'pages']);
$router->addRoute('GET',  '/api/generator/customconversions', [$generatorCtrl, 'customConversions']);
$router->addRoute('POST', '/api/generator/create',            [$generatorCtrl, 'create']);

// API — Auth
$router->addRoute('POST', '/api/auth/login', [$authCtrl, 'login']);
$router->addRoute('GET',  '/logout',         [$authCtrl, 'logout']);

// ── Web — Verificação de autenticação ─────────────────────────────────────────
$uri     = $request->getUri();
$isApi   = str_starts_with($uri, '/api/') || $uri === '/logout';
$isLogin = $uri === '/login';

if (!$isApi && !$isLogin) {
    requireWebAuth($config['api_key']);
}

// ── Rotas Web (Views) ─────────────────────────────────────────────────────────
$router->addRoute('GET', '/',           fn() => header('Location: /dashboard') ?: exit);
$router->addRoute('GET', '/login',      fn() => require __DIR__ . '/views/login.php');
$router->addRoute('GET', '/dashboard',  fn() => require __DIR__ . '/views/dashboard.php');
$router->addRoute('GET', '/campaigns',  fn() => require __DIR__ . '/views/campaigns.php');
$router->addRoute('GET', '/accounts',   fn() => require __DIR__ . '/views/accounts.php');
$router->addRoute('GET', '/ia',         fn() => require __DIR__ . '/views/ia/analysis.php');
$router->addRoute('GET', '/generator',  fn() => require __DIR__ . '/views/generator.php');

$router->dispatch($request);

// ── Função de autenticação web ────────────────────────────────────────────────
function requireWebAuth(string $apiKey): void
{
    $cookie = isset($_COOKIE['_auth']) ? urldecode($_COOKIE['_auth']) : '';

    if ($cookie !== '' && hash_equals($apiKey, $cookie)) {
        $GLOBALS['_authToken'] = $cookie;
        return;
    }

    header('Location: /login');
    exit;
}
