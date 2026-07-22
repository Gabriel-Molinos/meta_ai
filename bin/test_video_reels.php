<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

$config = require __DIR__ . '/../config/config.php';

use App\Core\HttpClient\HttpClient;
use App\Services\MetaAds\CampaignCreatorService;

// ── Parâmetros ────────────────────────────────────────────────────────────────
$videoPath = $argv[1] ?? null;
if (!$videoPath || !file_exists($videoPath)) {
    echo "Uso: php bin/test_video_reels.php <caminho_do_video.mp4>\n";
    echo "Exemplo: php bin/test_video_reels.php C:\\videos\\reels_test.mp4\n";
    exit(1);
}

// Conta pxmind-teste (act_834518578730293) — testada e funcionando
$accountId   = $argv[2] ?? 'act_834518578730293';
$accessToken = $config['meta_ads']['access_token'];
$apiVersion  = $config['meta_ads']['api_version'] ?? 'v22.0';

// Parâmetros do anúncio — ajuste conforme necessário
$PAGE_ID       = '422491920949325';
$PIXEL_ID      = '820776303461311';
$PIXEL_EVENT   = 'Purchase';
$IG_USER_ID    = '17841469630403537';

// ── Init ─────────────────────────────────────────────────────────────────────
$http = new HttpClient($config['retry'], $config['curl'] ?? []);
$metaConfig = [
    'access_token' => $accessToken,
    'account_id'   => $accountId,
    'api_version'  => $apiVersion,
];
$creator = new CampaignCreatorService($metaConfig, $http);

$ts = date('Ymd_His');
echo "=== Teste Vídeo Reels ===\n";
echo "Conta   : {$accountId}\n";
echo "Vídeo   : {$videoPath} (" . round(filesize($videoPath) / 1024 / 1024, 2) . " MB)\n";
echo "Token   : " . substr($accessToken, 0, 30) . "...\n\n";

// ── 1. Campanha ───────────────────────────────────────────────────────────────
echo "[1] Criando campanha...\n";
try {
    $campaignId = $creator->createCampaign("REELS_VIDEO_TEST_{$ts}", 'OUTCOME_SALES');
    echo "    OK — campaign_id: {$campaignId}\n\n";
} catch (\Throwable $e) {
    echo "    FALHOU — " . $e->getMessage() . "\n";
    exit(1);
}

// ── 2. Ad Set — somente Instagram Reels ───────────────────────────────────────
echo "[2] Criando ad set (Instagram Reels)...\n";
try {
    $adSetId = $creator->createAdSet($campaignId, [
        'campaign_name'       => "REELS_VIDEO_TEST_{$ts}",
        'objective'           => 'OUTCOME_SALES',
        'countries'           => ['BR'],
        'daily_budget_cents'  => 500,   // R$5,00
        'advantage_audience'  => 1,
        'publisher_platforms' => ['instagram'],
        'instagram_positions' => ['reels'],
        'pixel_id'            => $PIXEL_ID,
        'pixel_event'         => $PIXEL_EVENT,
        'start_time'          => date('Y-m-d'),
    ]);
    echo "    OK — adset_id: {$adSetId}\n\n";
} catch (\Throwable $e) {
    echo "    FALHOU — " . $e->getMessage() . "\n";
    echo "    (campanha {$campaignId} permanece PAUSED no Meta)\n";
    exit(1);
}

// ── 3. Upload do vídeo ────────────────────────────────────────────────────────
echo "[3] Fazendo upload do vídeo (pode demorar)...\n";
try {
    $videoResult = $creator->uploadVideo($videoPath, "REELS_VIDEO_TEST_{$ts}");
    $videoId      = $videoResult['id'];
    $thumbnailUrl = $videoResult['thumbnail_url'];
    echo "    OK — video_id: {$videoId}\n";
    echo "    Thumbnail: " . ($thumbnailUrl ? substr($thumbnailUrl, 0, 80) . '...' : 'nenhuma') . "\n\n";
} catch (\Throwable $e) {
    echo "    FALHOU — " . $e->getMessage() . "\n";
    exit(1);
}

// ── 4. Creative de vídeo ──────────────────────────────────────────────────────
echo "[4] Criando video creative...\n";
try {
    $creativeId = $creator->createVideoCreative([
        'name'             => "REELS_VIDEO_TEST_{$ts}",
        'page_id'          => $PAGE_ID,
        'instagram_user_id'=> $IG_USER_ID,
        'video_id'         => $videoId,
        'thumbnail_url'    => $thumbnailUrl,
        'primary_text'     => 'Teste de Reels via API 🎬',
        'headline'         => 'Confira agora',
        'link_description' => 'Saiba mais',
        'destination_url'  => 'https://facebook.com',
        'cta'              => 'LEARN_MORE',
    ]);
    echo "    OK — creative_id: {$creativeId}\n\n";
} catch (\Throwable $e) {
    echo "    FALHOU — " . $e->getMessage() . "\n";
    exit(1);
}

// ── 5. Ad ─────────────────────────────────────────────────────────────────────
echo "[5] Criando ad...\n";
try {
    $adId = $creator->createAd($adSetId, $creativeId, "REELS_VIDEO_TEST_{$ts}");
    echo "    OK — ad_id: {$adId}\n\n";
} catch (\Throwable $e) {
    echo "    FALHOU — " . $e->getMessage() . "\n";
    exit(1);
}

// ── Resultado ─────────────────────────────────────────────────────────────────
echo "╔══════════════════════════════════════╗\n";
echo "║          SUCESSO — TUDO CRIADO       ║\n";
echo "╚══════════════════════════════════════╝\n";
echo "campaign_id  : {$campaignId}\n";
echo "adset_id     : {$adSetId}\n";
echo "video_id     : {$videoId}\n";
echo "creative_id  : {$creativeId}\n";
echo "ad_id        : {$adId}\n";
echo "\nTodos criados com status PAUSED — sem gasto.\n";
echo "Visualize em: https://www.facebook.com/adsmanager\n";
