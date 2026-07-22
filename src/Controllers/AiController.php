<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Http\Request;
use App\Core\Http\Response;
use App\Middleware\AuthMiddleware;
use App\Models\CampaignReport;
use App\Services\AI\GeminiService;

class AiController
{
    public function __construct(
        private readonly GeminiService  $gemini,
        private readonly CampaignReport $report,
        private readonly AuthMiddleware $auth
    ) {}

    public function analyze(Request $request, array $params): never
    {
        $this->auth->handle($request);

        $body       = $request->getBody();
        $days       = (int) ($body['days'] ?? 30);
        $accountKey = $body['account_key'] ?? '';

        $campaigns = $this->report->getTopByRoas($days, 50);

        if (!empty($accountKey)) {
            $campaigns = array_filter($campaigns, fn($c) => $c['account_key'] === $accountKey);
        }

        $analysis = $this->gemini->analyzeCampaigns(array_values($campaigns), $days);

        Response::json(['analysis' => $analysis]);
    }

    public function trend(Request $request, array $params): never
    {
        $this->auth->handle($request);

        $body       = $request->getBody();
        $days       = (int) ($body['days'] ?? 30);
        $accountKey = $body['account_key'] ?? '';

        $campaigns = $this->report->getTopByRoas($days, 30);

        if (!empty($accountKey)) {
            $campaigns = array_filter($campaigns, fn($c) => $c['account_key'] === $accountKey);
        }

        $verdicts = $this->gemini->analyzeRoasTrend(array_values($campaigns), $days);

        Response::json(['verdicts' => $verdicts]);
    }
}
