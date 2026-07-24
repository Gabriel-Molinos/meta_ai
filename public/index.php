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
use App\Controllers\ApprovalController;
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
use App\Models\CampaignDraft;
use App\Models\CampaignReport;
use App\Models\User;
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

$GLOBALS['_httpConfig'] = $config['retry'];

$accountModel   = new Account($enc);
$reportModel    = new CampaignReport();
$draftModel     = new CampaignDraft();
$gemini         = new GeminiService($config['gemini'], $http);
$wpSiteModel    = new WordPressSite($enc);
$wpTplModel     = new WordPressTemplate();
$wpService      = new WordPressService();
$creativeGen    = new CreativeGenerationService($config['gemini']['api_key'], $http);

// ── Controladores ─────────────────────────────────────────────────────────────
$accountCtrl   = new AccountController($accountModel, $auth);
$campaignCtrl  = new CampaignController($reportModel, $auth);
$aiCtrl        = new AiController($gemini, $reportModel, $auth);
$authCtrl      = new AuthController(
    $config['api_key'],
    $config['google_oauth']['client_id'],
    $config['google_oauth']['client_secret'],
    $config['google_oauth']['redirect_uri'],
);
$generatorCtrl = new CampaignGeneratorController($accountModel, $http, $auth, $draftModel);
$approvalCtrl  = new ApprovalController($auth, $accountModel, $draftModel, $http);

// ── Roteador ──────────────────────────────────────────────────────────────────
$router  = new Router();
$request = Request::fromGlobals();

// API — Auth
$router->addRoute('GET',  '/oauth/google',         [$authCtrl, 'redirectToGoogle']);
$router->addRoute('GET',  '/oauth/callback',        [$authCtrl, 'handleCallback']);
$router->addRoute('POST', '/api/auth/admin-login',  [$authCtrl, 'adminLogin']);
$router->addRoute('GET',  '/logout',                [$authCtrl, 'logout']);

// API — Contas
$router->addRoute('GET',    '/api/accounts',          [$accountCtrl, 'index']);
$router->addRoute('POST',   '/api/accounts',          [$accountCtrl, 'store']);
$router->addRoute('PUT',    '/api/accounts/{key}',    [$accountCtrl, 'update']);
$router->addRoute('DELETE', '/api/accounts/{key}',    [$accountCtrl, 'destroy']);

// API — Lista de contas acessível ao usuário logado (admin=todas, user=vinculadas)
$router->addRoute('GET', '/api/accounts/list', function (Request $r) use ($auth, $accountModel) {
    $ctx = $auth->resolveContext($r);
    if ($ctx['type'] === 'admin') {
        Response::json(['status' => 'success', 'data' => $accountModel->all()]);
    } else {
        Response::json(['status' => 'success', 'data' => (new User())->getAccounts($ctx['user_id'])]);
    }
});

// API — Gerador de campanhas
$router->addRoute('GET',  '/api/generator/pixels',            [$generatorCtrl, 'pixels']);
$router->addRoute('GET',  '/api/generator/events',            [$generatorCtrl, 'events']);
$router->addRoute('GET',  '/api/generator/pages',             [$generatorCtrl, 'pages']);
$router->addRoute('GET',  '/api/generator/customconversions', [$generatorCtrl, 'customConversions']);
$router->addRoute('POST', '/api/generator/create',            [$generatorCtrl, 'create']);
$router->addRoute('POST', '/api/generator/submit',            [$generatorCtrl, 'submit']);

// API — Aprovações (admin only)
$router->addRoute('GET',  '/api/approvals/pending-count',   [$approvalCtrl, 'pendingCount']);
$router->addRoute('GET',  '/api/approvals',                  [$approvalCtrl, 'index']);
$router->addRoute('GET',  '/api/approvals/{id}',             [$approvalCtrl, 'show']);
$router->addRoute('POST', '/api/approvals/{id}/approve',     [$approvalCtrl, 'approve']);
$router->addRoute('POST', '/api/approvals/{id}/reject',      [$approvalCtrl, 'reject']);

// API — Minhas campanhas (usuário)
$router->addRoute('GET', '/api/my-campaigns', function (Request $r) use ($auth, $draftModel) {
    $ctx = $auth->resolveContext($r);
    if ($ctx['type'] !== 'user') {
        Response::error('Apenas usuários OAuth podem acessar suas campanhas', 403);
    }
    Response::json(['status' => 'success', 'data' => $draftModel->byUser($ctx['user_id'])]);
});
$router->addRoute('GET', '/api/my-campaigns/{id}', function (Request $r, array $p) use ($auth, $draftModel) {
    $ctx   = $auth->resolveContext($r);
    $id    = (int) $p['id'];
    $draft = $draftModel->findById($id);
    if (!$draft) {
        Response::error('Não encontrado', 404);
    }
    if ($ctx['type'] === 'user' && !$draftModel->belongsToUser($id, $ctx['user_id'])) {
        Response::error('Não autorizado', 403);
    }
    Response::json(['status' => 'success', 'data' => $draft]);
});
$router->addRoute('POST', '/api/my-campaigns/{id}/resubmit', function (Request $r, array $p) use ($auth, $generatorCtrl, $draftModel) {
    $ctx = $auth->resolveContext($r);
    $id  = (int) $p['id'];
    if ($ctx['type'] !== 'user') {
        Response::error('Apenas usuários OAuth podem reenviar campanhas', 403);
    }
    if (!$draftModel->belongsToUser($id, $ctx['user_id'])) {
        Response::error('Não autorizado', 403);
    }
    $generatorCtrl->resubmit($r, ['id' => $id]);
});

// API — Gerenciamento de usuários (admin only)
$router->addRoute('GET', '/api/users', function (Request $r) use ($auth) {
    $ctx = $auth->resolveContext($r);
    if ($ctx['type'] !== 'admin') {
        Response::error('Forbidden', 403);
    }
    Response::json(['status' => 'success', 'data' => (new User())->all()]);
});
$router->addRoute('PUT', '/api/users/{id}/accounts', function (Request $r, array $p) use ($auth) {
    $ctx        = $auth->resolveContext($r);
    if ($ctx['type'] !== 'admin') {
        Response::error('Forbidden', 403);
    }
    $data       = json_decode(file_get_contents('php://input'), true) ?? [];
    $accountIds = (array) ($data['account_ids'] ?? []);
    (new User())->setAccounts((int) $p['id'], $accountIds);
    Response::json(['status' => 'success']);
});
$router->addRoute('DELETE', '/api/users/{id}', function (Request $r, array $p) use ($auth) {
    $ctx = $auth->resolveContext($r);
    if ($ctx['type'] !== 'admin') {
        Response::error('Forbidden', 403);
    }
    (new User())->delete((int) $p['id']);
    Response::json(['status' => 'success']);
});

// API — WordPress Sites
$router->addRoute('GET',    '/api/wordpress/sites',     function () use ($auth, $wpSiteModel) {
    $auth->handle(Request::fromGlobals());
    Response::json(['status' => 'success', 'data' => $wpSiteModel->all()]);
});
$router->addRoute('POST',   '/api/wordpress/sites',     function () use ($auth, $wpSiteModel) {
    $auth->handle(Request::fromGlobals());
    $data = json_decode(file_get_contents('php://input'), true) ?? [];
    if (empty($data['label']) || empty($data['url']) || empty($data['wp_username']) || empty($data['wp_app_password'])) {
        Response::error('label, url, wp_username e wp_app_password são obrigatórios', 422);
        return;
    }
    $id = $wpSiteModel->create($data);
    Response::json(['status' => 'success', 'id' => $id], 201);
});
$router->addRoute('PUT',    '/api/wordpress/sites/{id}', function ($req, array $params) use ($auth, $wpSiteModel) {
    $auth->handle(Request::fromGlobals());
    $data = json_decode(file_get_contents('php://input'), true) ?? [];
    $wpSiteModel->update((int) $params['id'], $data);
    Response::json(['status' => 'success']);
});
$router->addRoute('DELETE', '/api/wordpress/sites/{id}', function ($req, array $params) use ($auth, $wpSiteModel) {
    $auth->handle(Request::fromGlobals());
    $wpSiteModel->delete((int) $params['id']);
    Response::json(['status' => 'success']);
});

// API — WordPress Templates
$router->addRoute('GET',  '/api/wordpress/templates',     function () use ($auth, $wpTplModel) {
    $auth->handle(Request::fromGlobals());
    Response::json($wpTplModel->findAll());
});
$router->addRoute('GET',  '/api/wordpress/templates/{id}', function ($req, array $params) use ($auth, $wpTplModel) {
    $auth->handle(Request::fromGlobals());
    $tpl = $wpTplModel->find((int) $params['id']);
    if (!$tpl) { Response::error('Template não encontrado', 404); return; }
    Response::json($tpl);
});
$router->addRoute('POST', '/api/wordpress/templates',     function () use ($auth, $wpTplModel) {
    $auth->handle(Request::fromGlobals());
    $data = json_decode(file_get_contents('php://input'), true) ?? [];
    if (empty($data['name'])) { Response::error('name é obrigatório', 422); return; }
    $id = $wpTplModel->create($data);
    Response::json(['status' => 'success', 'id' => $id], 201);
});
$router->addRoute('PUT',  '/api/wordpress/templates/{id}', function ($req, array $params) use ($auth, $wpTplModel) {
    $auth->handle(Request::fromGlobals());
    $data    = json_decode(file_get_contents('php://input'), true) ?? [];
    $updated = $wpTplModel->update((int) $params['id'], $data);
    if (!$updated) { Response::error('Template não encontrado ou é de sistema', 404); return; }
    Response::json(['status' => 'success']);
});
$router->addRoute('DELETE', '/api/wordpress/templates/{id}', function ($req, array $params) use ($auth, $wpTplModel) {
    $auth->handle(Request::fromGlobals());
    $deleted = $wpTplModel->delete((int) $params['id']);
    if (!$deleted) { Response::error('Template não encontrado ou é de sistema', 404); return; }
    Response::json(['status' => 'success']);
});
$router->addRoute('POST', '/api/wordpress/templates/generate-from-url', function () use ($auth, $gemini) {
    $auth->handle(Request::fromGlobals());
    $data = json_decode(file_get_contents('php://input'), true) ?? [];
    if (empty($data['url'])) { Response::error('url é obrigatório', 422); return; }
    $html = $gemini->extractTemplateFromUrl($data['url']);
    Response::json(['status' => 'success', 'html' => $html]);
});

// API — WordPress Generate + Publish
$router->addRoute('POST', '/api/wordpress/generate', function () use ($auth, $gemini, $wpTplModel) {
    $auth->handle(Request::fromGlobals());
    $data       = json_decode(file_get_contents('php://input'), true) ?? [];
    $topic      = trim($data['topic'] ?? '');
    $language   = trim($data['language'] ?? 'English');
    $wordCount  = max(200, min(5000, (int) ($data['word_count'] ?? 1000)));
    $buttons    = (array) ($data['buttons'] ?? []);
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

$router->addRoute('POST', '/api/wordpress/generate-featured-image', function () use ($auth, $creativeGen) {
    $auth->handle(Request::fromGlobals());
    $data  = json_decode(file_get_contents('php://input'), true) ?? [];
    $topic = trim($data['topic'] ?? '');
    $title = trim($data['title'] ?? '');
    if ($topic === '' && $title === '') { Response::error('topic ou title é obrigatório', 422); return; }
    $result = $creativeGen->generateFeaturedImage($topic ?: $title, $title);
    Response::json(['status' => 'success', 'data' => $result['data'], 'mime_type' => $result['mimeType']]);
});

$router->addRoute('POST', '/api/wordpress/pages', function () use ($auth, $wpSiteModel, $wpService) {
    $auth->handle(Request::fromGlobals());
    $data   = json_decode(file_get_contents('php://input'), true) ?? [];
    $siteId = (int) ($data['site_id'] ?? 0);
    $title  = trim($data['title'] ?? '');
    $html   = trim($data['html_content'] ?? '');
    $status = in_array($data['status'] ?? '', ['publish', 'draft', 'private'], true) ? $data['status'] : 'publish';
    $type   = in_array($data['post_type'] ?? '', ['post', 'page'], true) ? $data['post_type'] : 'post';

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
$isApi   = str_starts_with($uri, '/api/') || str_starts_with($uri, '/oauth/') || $uri === '/logout';
$isLogin = $uri === '/login';

if (!$isApi && !$isLogin) {
    requireWebAuth($config['api_key']);
}

// ── Rotas Web (Views) ─────────────────────────────────────────────────────────
$router->addRoute('GET', '/',                  fn() => header('Location: /generator') ?: exit);
$router->addRoute('GET', '/login',             [$authCtrl, 'showLogin']);
$router->addRoute('GET', '/accounts',          fn() => require __DIR__ . '/views/accounts.php');
$router->addRoute('GET', '/generator',         fn() => require __DIR__ . '/views/generator.php');
$router->addRoute('GET', '/wordpress/pages',   fn() => require __DIR__ . '/views/wordpress/pages.php');
$router->addRoute('GET', '/approvals',         fn() => require __DIR__ . '/views/approvals.php');
$router->addRoute('GET', '/my-campaigns',      fn() => require __DIR__ . '/views/my-campaigns.php');

$router->dispatch($request);

// ── Função de autenticação web ────────────────────────────────────────────────
function requireWebAuth(string $apiKey): void
{
    $cookie = isset($_COOKIE['_auth']) ? urldecode($_COOKIE['_auth']) : '';

    if ($cookie === '') {
        header('Location: /login');
        exit;
    }

    // Admin via API key
    if (hash_equals($apiKey, $cookie)) {
        $GLOBALS['_authToken'] = $cookie;
        $GLOBALS['_authType']  = 'admin';
        return;
    }

    // Usuário OAuth via session_token
    try {
        $stmt = \App\Core\Database\Connection::getInstance()->prepare(
            'SELECT id, email FROM users WHERE session_token = ? AND token_expires_at > NOW() LIMIT 1'
        );
        $stmt->execute([$cookie]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        if ($row) {
            $GLOBALS['_authToken']  = $cookie;
            $GLOBALS['_authType']   = 'user';
            $GLOBALS['_authEmail']  = $row['email'];
            $GLOBALS['_authUserId'] = (int) $row['id'];
            return;
        }
    } catch (\Throwable) {}

    header('Location: /login');
    exit;
}
