<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Http\Request;
use App\Core\Http\Response;
use App\Middleware\AuthMiddleware;
use App\Models\CampaignReport;

class CampaignController
{
    public function __construct(
        private readonly CampaignReport $report,
        private readonly AuthMiddleware $auth
    ) {}

    public function index(Request $request, array $params): never
    {
        $this->auth->handle($request);

        $filters = [
            'account_key'   => $_GET['account_key']   ?? '',
            'campaign_name' => $_GET['campaign_name'] ?? '',
            'start_date'    => $_GET['start_date']    ?? '',
            'end_date'      => $_GET['end_date']      ?? date('Y-m-d'),
        ];

        Response::json(['data' => $this->report->query($filters)]);
    }

    public function dashboard(Request $request, array $params): never
    {
        $this->auth->handle($request);

        $accountKey = $_GET['account_key'] ?? '';
        $days       = (int) ($_GET['days'] ?? 30);

        $overview = $this->report->getOverviewMetrics($accountKey, $days);
        $topRoas  = $this->report->getTopByRoas($days, 20);

        Response::json([
            'overview' => $overview,
            'top_roas' => $topRoas,
        ]);
    }
}
