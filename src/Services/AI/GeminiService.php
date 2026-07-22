<?php

declare(strict_types=1);

namespace App\Services\AI;

use App\Core\HttpClient\HttpClient;

class GeminiService
{
    private const BASE_URL = 'https://generativelanguage.googleapis.com/v1beta/models';

    private string $apiKey;
    private string $model;
    private int    $timeout;

    public function __construct(array $config, private readonly HttpClient $http)
    {
        $this->apiKey  = $config['api_key'];
        $this->model   = $config['model']   ?? 'gemini-2.5-flash';
        $this->timeout = (int) ($config['timeout'] ?? 180);
    }

    /**
     * Análise narrativa de campanhas Meta Ads com cruzamento de ROAS.
     */
    public function analyzeCampaigns(array $campaigns, int $days): string
    {
        if (empty($campaigns)) {
            return 'Nenhum dado de campanha disponível para análise.';
        }

        $url     = self::BASE_URL . '/' . $this->model . ':generateContent';
        $headers = ['x-goog-api-key' => $this->apiKey];
        $body    = [
            'contents' => [
                ['parts' => [['text' => $this->buildCampaignPrompt($campaigns, $days)]]]
            ],
            'generationConfig' => [
                'temperature'     => 0.3,
                'maxOutputTokens' => 8192,
                'thinkingConfig'  => ['thinkingBudget' => 0],
            ],
        ];

        $response = $this->http->post($url, $headers, $body, $this->timeout);

        return $response['candidates'][0]['content']['parts'][0]['text']
            ?? 'Análise indisponível. Verifique a configuração da API Gemini.';
    }

    /**
     * Retorna veredito JSON por campanha: {campaign_id, verdict, reasoning, action}.
     */
    public function analyzeRoasTrend(array $campaigns, int $days): array
    {
        if (empty($campaigns)) {
            return [];
        }

        $url     = self::BASE_URL . '/' . $this->model . ':generateContent';
        $headers = ['x-goog-api-key' => $this->apiKey];
        $body    = [
            'contents' => [
                ['parts' => [['text' => $this->buildTrendPrompt($campaigns, $days)]]]
            ],
            'generationConfig' => [
                'temperature'      => 0.2,
                'maxOutputTokens'  => 4096,
                'responseMimeType' => 'application/json',
                'thinkingConfig'   => ['thinkingBudget' => 0],
            ],
        ];

        $response = $this->http->post($url, $headers, $body, $this->timeout);
        $text     = $response['candidates'][0]['content']['parts'][0]['text'] ?? '[]';

        return json_decode($text, true) ?? [];
    }

    private function buildCampaignPrompt(array $campaigns, int $days): string
    {
        $rows = '';
        foreach ($campaigns as $c) {
            $roas = number_format((float) ($c['roas'] ?? 0), 2);
            $rows .= sprintf(
                "- %s | Gasto: $%.2f | Receita AV: $%.2f | ROAS: %s | Impressões: %s | Cliques: %s | CTR: %.2f%% | CPM: $%.2f\n",
                $c['campaign_name'] ?? 'N/A',
                $c['spend_usd']     ?? 0,
                $c['av_revenue_usd'] ?? 0,
                $roas,
                number_format((int) ($c['impressions'] ?? 0)),
                number_format((int) ($c['clicks'] ?? 0)),
                $c['ctr']  ?? 0,
                $c['cpm_usd'] ?? 0
            );
        }

        return <<<PROMPT
        Você é um especialista em marketing digital com foco em Meta Ads e monetização programática (AdSense/GAM).

        Analise os dados de performance das campanhas Meta Ads dos últimos {$days} dias abaixo e forneça:
        1. Um diagnóstico geral do portfolio de campanhas
        2. As 3 campanhas com melhor e pior ROAS, com reasoning
        3. Anomalias ou oportunidades identificadas (CTR, CPM, ROAS)
        4. Recomendações de ação prioritárias (em bullet points)

        Dados das campanhas:
        {$rows}

        ROAS = Receita ActiveView / Gasto Meta Ads. ROAS > 1 significa campanha lucrativa.
        Responda em português, com formatação Markdown.
        PROMPT;
    }

    private function buildTrendPrompt(array $campaigns, int $days): string
    {
        $json = json_encode(array_map(fn($c) => [
            'campaign_id'    => $c['campaign_id']    ?? '',
            'campaign_name'  => $c['campaign_name']  ?? '',
            'roas'           => round((float) ($c['roas'] ?? 0), 4),
            'spend_usd'      => round((float) ($c['spend_usd'] ?? 0), 2),
            'av_revenue_usd' => round((float) ($c['av_revenue_usd'] ?? 0), 2),
            'impressions'    => (int) ($c['impressions'] ?? 0),
            'ctr'            => round((float) ($c['ctr'] ?? 0), 3),
        ], $campaigns), JSON_PRETTY_PRINT);

        return <<<PROMPT
        Analise as campanhas Meta Ads dos últimos {$days} dias e retorne um array JSON.
        Cada elemento deve ter: campaign_id, verdict (SCALE/MAINTAIN/PAUSE/INVESTIGATE), reasoning (1 frase), action (ação concreta).
        ROAS > 1.5 = SCALE, 0.8–1.5 = MAINTAIN, < 0.8 = PAUSE ou INVESTIGATE.

        Dados:
        {$json}

        Retorne APENAS o array JSON, sem markdown.
        PROMPT;
    }
}
