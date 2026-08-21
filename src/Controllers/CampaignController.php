<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Http\Request;
use App\Core\Http\Response;
use App\Middleware\AuthMiddleware;
use App\Models\CampaignReport;
use App\Models\User;

class CampaignController
{
    public function __construct(
        private readonly CampaignReport $report,
        private readonly AuthMiddleware $auth,
        private readonly User           $userModel = new User(),
    ) {}

    public function index(Request $request, array $params): never
    {
        $ctx = $this->auth->resolveContext($request);
        [$accountKey, $allowedAccountKeys, $empty] = $this->resolveAccountScope($ctx, $_GET['account_key'] ?? '');

        if ($empty) {
            Response::json(['data' => []]);
        }

        $filters = [
            'account_key'   => $accountKey,
            'account_keys'  => $allowedAccountKeys,
            'campaign_name' => $_GET['campaign_name'] ?? '',
            'start_date'    => $_GET['start_date']    ?? '',
            'end_date'      => $_GET['end_date']      ?? date('Y-m-d'),
        ];

        Response::json(['data' => $this->report->query($filters)]);
    }

    public function dashboard(Request $request, array $params): never
    {
        $ctx = $this->auth->resolveContext($request);
        [$accountKey, $allowedAccountKeys, $empty] = $this->resolveAccountScope($ctx, $_GET['account_key'] ?? '');

        if ($empty) {
            Response::json(['overview' => [], 'top_roas' => [], 'daily_series' => []]);
        }

        $days      = (int) ($_GET['days'] ?? 30);
        $startDate = ($_GET['start_date'] ?? '') !== '' ? $_GET['start_date'] : null;
        $endDate   = ($_GET['end_date']   ?? '') !== '' ? $_GET['end_date']   : null;

        $overview    = $this->report->getOverviewMetrics($accountKey, $days, $allowedAccountKeys, $startDate, $endDate);
        $topRoas     = $this->report->getTopByRoas($days, 20, $accountKey, $allowedAccountKeys, $startDate, $endDate);
        $dailySeries = $this->report->getDailySeries($accountKey, $days, $allowedAccountKeys, $startDate, $endDate);

        Response::json([
            'overview'     => $overview,
            'top_roas'     => $topRoas,
            'daily_series' => $dailySeries,
        ]);
    }

    /**
     * Resolve o escopo de contas pro contexto autenticado.
     *
     * @return array{0: string, 1: ?array, 2: bool} [account_key validado, contas
     *         permitidas (null = sem restrição, admin), true se o usuário não tem
     *         nenhuma conta vinculada e o filtro pedido não é dele mesmo]
     */
    private function resolveAccountScope(array $ctx, string $requestedAccountKey): array
    {
        if ($ctx['type'] === 'admin') {
            return [$requestedAccountKey, null, false];
        }

        $allowed = array_column($this->userModel->getAccounts($ctx['user_id']), 'account_key');

        if ($requestedAccountKey !== '') {
            if (!in_array($requestedAccountKey, $allowed, true)) {
                return ['', $allowed, true];
            }
            return [$requestedAccountKey, $allowed, false];
        }

        if (empty($allowed)) {
            return ['', $allowed, true];
        }

        return ['', $allowed, false];
    }
}
