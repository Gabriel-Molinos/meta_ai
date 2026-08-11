<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Http\Request;
use App\Core\Http\Response;
use App\Middleware\AuthMiddleware;
use App\Models\Account;

class AccountController
{
    public function __construct(
        private readonly Account        $account,
        private readonly AuthMiddleware $auth
    ) {}

    public function index(Request $request, array $params): never
    {
        $this->auth->handle($request);
        Response::json(['data' => $this->account->all()]);
    }

    public function store(Request $request, array $params): never
    {
        $this->auth->handle($request);

        $data = $request->getBody();

        if (empty($data['account_key']) || empty($data['label'])) {
            Response::error('account_key e label são obrigatórios', 422);
        }

        if (empty($data['meta_access_token']) || empty($data['meta_account_id'])) {
            Response::error('Meta Access Token e Meta Account ID são obrigatórios', 422);
        }

        try {
            $this->account->create($data);
            Response::json(['status' => 'created'], 201);
        } catch (\Throwable $e) {
            Response::error('Erro ao criar conta: ' . $e->getMessage());
        }
    }

    public function update(Request $request, array $params): never
    {
        $this->auth->handle($request);

        $key      = $params['key'] ?? '';
        $existing = $this->account->find($key);

        if ($existing === null) {
            Response::error('Conta não encontrada', 404);
        }

        try {
            $this->account->update($key, $request->getBody(), $existing);
            Response::json(['status' => 'updated']);
        } catch (\Throwable $e) {
            Response::error('Erro ao atualizar conta: ' . $e->getMessage());
        }
    }

    public function destroy(Request $request, array $params): never
    {
        $this->auth->handle($request);

        $key = $params['key'] ?? '';

        if ($this->account->find($key) === null) {
            Response::error('Conta não encontrada', 404);
        }

        $this->account->delete($key);
        Response::json(['status' => 'deleted']);
    }
}
