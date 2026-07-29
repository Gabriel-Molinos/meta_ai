<?php

declare(strict_types=1);

namespace App\Services\MetaAds;

use App\Core\HttpClient\HttpClient;

class PixelService
{
    private string $baseUrl;
    private string $accessToken;
    private string $accountId;

    private const STANDARD_EVENTS = [
        'AddPaymentInfo', 'AddToCart', 'AddToWishlist', 'CompleteRegistration',
        'Contact', 'CustomizeProduct', 'Donate', 'FindLocation',
        'InitiateCheckout', 'Lead', 'Purchase', 'Schedule',
        'Search', 'StartTrial', 'SubmitApplication', 'Subscribe', 'ViewContent',
    ];

    public function __construct(array $config, private readonly HttpClient $http)
    {
        $version           = $config['api_version'] ?? 'v22.0';
        $this->baseUrl     = "https://graph.facebook.com/{$version}";
        $this->accessToken = $config['access_token'];
        $this->accountId   = ltrim($config['account_id'], 'act_');
    }

    public function fetchPixels(): array
    {
        $response = $this->http->get(
            "{$this->baseUrl}/act_{$this->accountId}/adspixels",
            [],
            ['fields' => 'id,name,creation_time', 'access_token' => $this->accessToken, 'limit' => 100],
            30
        );
        return $response['data'] ?? [];
    }

    public function fetchPixelEvents(string $pixelId): array
    {
        $fired = [];

        // Eventos disparados nos últimos 90 dias
        // Resposta: data[*].data[*].value (agregação por hora, cada entrada tem lista de eventos)
        try {
            $response = $this->http->get(
                "{$this->baseUrl}/{$pixelId}/stats",
                [],
                [
                    'aggregation'  => 'event',
                    'start_time'   => (string) (time() - 90 * 86400),
                    'limit'        => '200',
                    'access_token' => $this->accessToken,
                ],
                30
            );
            foreach ($response['data'] ?? [] as $bucket) {
                foreach ($bucket['data'] ?? [] as $entry) {
                    if (isset($entry['value'])) {
                        $fired[] = $entry['value'];
                    }
                }
            }
        } catch (\Throwable) {}

        $all = array_values(array_unique(array_merge($fired, self::STANDARD_EVENTS)));
        sort($all);
        return $all;
    }

    public function fetchCustomConversions(string $pixelId = ''): array
    {
        try {
            $response = $this->http->get(
                "{$this->baseUrl}/act_{$this->accountId}/customconversions",
                [],
                ['fields' => 'id,name,pixel_id,rule,creation_time', 'access_token' => $this->accessToken, 'limit' => 100],
                30
            );
            $data = $response['data'] ?? [];
            if ($pixelId !== '') {
                $data = array_values(array_filter($data, fn($c) => ($c['pixel_id'] ?? '') === $pixelId));
            }
            return $data;
        } catch (\Throwable) {
            return [];
        }
    }

    public function fetchPages(): array
    {
        $response = $this->http->get(
            "{$this->baseUrl}/act_{$this->accountId}/promote_pages",
            [],
            ['fields' => 'id,name,category', 'access_token' => $this->accessToken, 'limit' => 100],
            30
        );
        return $response['data'] ?? [];
    }
}
