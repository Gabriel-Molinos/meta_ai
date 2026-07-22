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
use App\Models\WordPressSite;
use App\Models\WordPressTemplate;
use App\Services\AI\CreativeGenerationService;
use App\Services\AI\GeminiService;
use App\Services\WordPress\WordPressService;

// ── Inicialização ─────────────────────────────────────────────────────────────
Connection::configure($config['database']);

$cache  = new RedisCache($config['redis']);
$http   = new HttpClient($config['retry'], $config['curl'] ?? []);
$enc    = new Encryptor($config['encryption_key']);
$auth   = new AuthMiddleware($config['api_key']);

$accountModel   = new Account($enc);
$reportModel    = new CampaignReport();
$gemini         = new GeminiService($config['gemini'], $http);
$wpSiteModel    = new WordPressSite($enc);
$wpTplModel     = new WordPressTemplate();
$wpService      = new WordPressService();
$creativeGen    = new CreativeGenerationService($config['gemini']['api_key'], $http);

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

// API — Gerador de campanhas
$router->addRoute('GET',  '/api/generator/pixels',            [$generatorCtrl, 'pixels']);
$router->addRoute('GET',  '/api/generator/events',            [$generatorCtrl, 'events']);
$router->addRoute('GET',  '/api/generator/pages',             [$generatorCtrl, 'pages']);
$router->addRoute('GET',  '/api/generator/customconversions', [$generatorCtrl, 'customConversions']);
$router->addRoute('POST', '/api/generator/create',            [$generatorCtrl, 'create']);

// API — Auth
$router->addRoute('POST', '/api/auth/login', [$authCtrl, 'login']);
$router->addRoute('GET',  '/logout',         [$authCtrl, 'logout']);

// API — WordPress Sites
$router->addRoute('GET',    '/api/wordpress/sites',     function() use ($auth, $wpSiteModel) {
    $auth->handle(Request::fromGlobals());
    Response::json(['status' => 'success', 'data' => $wpSiteModel->all()]);
});
$router->addRoute('POST',   '/api/wordpress/sites',     function() use ($auth, $wpSiteModel) {
    $auth->handle(Request::fromGlobals());
    $data = json_decode(file_get_contents('php://input'), true) ?? [];
    if (empty($data['label']) || empty($data['url']) || empty($data['wp_username']) || empty($data['wp_app_password'])) {
        Response::error('label, url, wp_username e wp_app_password são obrigatórios', 422);
        return;
    }
    $id = $wpSiteModel->create($data);
    Response::json(['status' => 'success', 'id' => $id], 201);
});
$router->addRoute('PUT',    '/api/wordpress/sites/{id}', function($req, array $params) use ($auth, $wpSiteModel) {
    $auth->handle(Request::fromGlobals());
    $data = json_decode(file_get_contents('php://input'), true) ?? [];
    $wpSiteModel->update((int) $params['id'], $data);
    Response::json(['status' => 'success']);
});
$router->addRoute('DELETE', '/api/wordpress/sites/{id}', function($req, array $params) use ($auth, $wpSiteModel) {
    $auth->handle(Request::fromGlobals());
    $wpSiteModel->delete((int) $params['id']);
    Response::json(['status' => 'success']);
});

// API — WordPress Templates
$router->addRoute('GET',  '/api/wordpress/templates',     function() use ($auth, $wpTplModel) {
    $auth->handle(Request::fromGlobals());
    Response::json($wpTplModel->findAll());
});
$router->addRoute('GET',  '/api/wordpress/templates/{id}', function($req, array $params) use ($auth, $wpTplModel) {
    $auth->handle(Request::fromGlobals());
    $tpl = $wpTplModel->find((int) $params['id']);
    if (!$tpl) { Response::error('Template não encontrado', 404); return; }
    Response::json($tpl);
});
$router->addRoute('POST', '/api/wordpress/templates',     function() use ($auth, $wpTplModel) {
    $auth->handle(Request::fromGlobals());
    $data = json_decode(file_get_contents('php://input'), true) ?? [];
    if (empty($data['name'])) { Response::error('name é obrigatório', 422); return; }
    $id = $wpTplModel->create($data);
    Response::json(['status' => 'success', 'id' => $id], 201);
});
$router->addRoute('PUT',  '/api/wordpress/templates/{id}', function($req, array $params) use ($auth, $wpTplModel) {
    $auth->handle(Request::fromGlobals());
    $data = json_decode(file_get_contents('php://input'), true) ?? [];
    $updated = $wpTplModel->update((int) $params['id'], $data);
    if (!$updated) { Response::error('Template não encontrado ou é de sistema', 404); return; }
    Response::json(['status' => 'success']);
});
$router->addRoute('DELETE', '/api/wordpress/templates/{id}', function($req, array $params) use ($auth, $wpTplModel) {
    $auth->handle(Request::fromGlobals());
    $deleted = $wpTplModel->delete((int) $params['id']);
    if (!$deleted) { Response::error('Template não encontrado ou é de sistema', 404); return; }
    Response::json(['status' => 'success']);
});
$router->addRoute('POST', '/api/wordpress/templates/generate-from-url', function() use ($auth, $gemini) {
    $auth->handle(Request::fromGlobals());
    $data = json_decode(file_get_contents('php://input'), true) ?? [];
    if (empty($data['url'])) { Response::error('url é obrigatório', 422); return; }
    $html = $gemini->extractTemplateFromUrl($data['url']);
    Response::json(['status' => 'success', 'html' => $html]);
});

// API — WordPress Generate + Publish
$router->addRoute('POST', '/api/wordpress/generate', function() use ($auth, $gemini, $wpTplModel) {
    $auth->handle(Request::fromGlobals());
    $data      = json_decode(file_get_contents('php://input'), true) ?? [];
    $topic     = trim($data['topic'] ?? '');
    $language  = trim($data['language'] ?? 'English');
    $wordCount = max(200, min(5000, (int) ($data['word_count'] ?? 1000)));
    $buttons   = (array) ($data['buttons'] ?? []);
    $inclHeader = (bool) ($data['include_header_buttons'] ?? true);
    $inclText   = (bool) ($data['include_text_before_buttons'] ?? true);
    $components = (array) ($data['components'] ?? []);
    $templateId = (int) ($data['template_id'] ?? 0);

    if ($topic === '') { Response::error('topic é obrigatório', 422); return; }

    $htmlTemplate = null;
    if ($templateId > 0) {
        $tpl = $wpTplModel->find($templateId);
        if ($tpl && !empty($tpl['html'])) {
            $htmlTemplate = $tpl['html'];
        }
    }

    $html = $gemini->generateBlogContent($topic, $language, $wordCount, $buttons, $inclHeader, $inclText, $components, $htmlTemplate);
    Response::json(['status' => 'success', 'html' => $html]);
});

$router->addRoute('POST', '/api/wordpress/generate-featured-image', function() use ($auth, $creativeGen) {
    $auth->handle(Request::fromGlobals());
    $data  = json_decode(file_get_contents('php://input'), true) ?? [];
    $topic = trim($data['topic'] ?? '');
    $title = trim($data['title'] ?? '');
    if ($topic === '' && $title === '') { Response::error('topic ou title é obrigatório', 422); return; }
    $result = $creativeGen->generateFeaturedImage($topic ?: $title, $title);
    Response::json(['status' => 'success', 'data' => $result['data'], 'mime_type' => $result['mimeType']]);
});

$router->addRoute('POST', '/api/wordpress/pages', function() use ($auth, $wpSiteModel, $wpService) {
    $auth->handle(Request::fromGlobals());
    $data    = json_decode(file_get_contents('php://input'), true) ?? [];
    $siteId  = (int) ($data['site_id'] ?? 0);
    $title   = trim($data['title'] ?? '');
    $html    = trim($data['html_content'] ?? '');
    $status  = in_array($data['status'] ?? '', ['publish', 'draft', 'private'], true) ? $data['status'] : 'publish';
    $type    = in_array($data['post_type'] ?? '', ['post', 'page'], true) ? $data['post_type'] : 'post';

    if (!$siteId || $title === '' || $html === '') {
        Response::error('site_id, title e html_content são obrigatórios', 422);
        return;
    }

    $siteRow = $wpSiteModel->find($siteId);
    if (!$siteRow) { Response::error('Site não encontrado', 404); return; }
    $site = $wpSiteModel->toConfig($siteRow);

    $featuredMediaId = 0;
    if (!empty($data['featured_image_b64'])) {
        try {
            $featuredMediaId = $wpService->uploadMedia(
                $site['url'], $site['wp_username'], $site['wp_app_password'],
                $data['featured_image_b64'], $data['featured_image_mime'] ?? 'image/png'
            );
        } catch (\Throwable) {}
    }

    $result = $wpService->createPage(
        $site['url'], $site['wp_username'], $site['wp_app_password'],
        $title, $html, $status, $type, $featuredMediaId
    );
    Response::json(['status' => 'success', 'data' => $result]);
});

// ── Web — Verificação de autenticação ─────────────────────────────────────────
$uri     = $request->getUri();
$isApi   = str_starts_with($uri, '/api/') || $uri === '/logout';
$isLogin = $uri === '/login';

if (!$isApi && !$isLogin) {
    requireWebAuth($config['api_key']);
}

// ── Rotas Web (Views) ─────────────────────────────────────────────────────────
$router->addRoute('GET', '/',                  fn() => header('Location: /generator') ?: exit);
$router->addRoute('GET', '/login',             fn() => require __DIR__ . '/views/login.php');
$router->addRoute('GET', '/accounts',          fn() => require __DIR__ . '/views/accounts.php');
$router->addRoute('GET', '/generator',         fn() => require __DIR__ . '/views/generator.php');
$router->addRoute('GET', '/wordpress/pages',   fn() => require __DIR__ . '/views/wordpress/pages.php');

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
