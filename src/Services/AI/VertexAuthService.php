<?php

declare(strict_types=1);

namespace App\Services\AI;

use App\Core\Cache\RedisCache;
use App\Core\HttpClient\HttpClient;

class VertexAuthService
{
    private const SCOPE     = 'https://www.googleapis.com/auth/cloud-platform';
    private const CACHE_KEY = 'vertex:access_token';
    private const TOKEN_URL = 'https://oauth2.googleapis.com/token';

    private ?array $credentials = null;

    public function __construct(
        private readonly array      $config,
        private readonly HttpClient $http,
        private readonly RedisCache $cache,
    ) {}

    public function getAccessToken(): string
    {
        $cached = $this->cache->get(self::CACHE_KEY);
        if (is_string($cached) && $cached !== '') {
            return $cached;
        }

        $credentials = $this->loadCredentials();
        $tokenUri    = $credentials['token_uri'] ?? self::TOKEN_URL;
        $now         = time();

        $header = ['alg' => 'RS256', 'typ' => 'JWT'];
        $claim  = [
            'iss'   => $credentials['client_email'],
            'scope' => self::SCOPE,
            'aud'   => $tokenUri,
            'iat'   => $now,
            'exp'   => $now + 3600,
        ];

        $signingInput = $this->base64UrlEncode(json_encode($header))
            . '.' . $this->base64UrlEncode(json_encode($claim));

        $privateKey = openssl_pkey_get_private($credentials['private_key']);
        if ($privateKey === false) {
            throw new \RuntimeException('Chave privada da service account do Vertex é inválida.');
        }

        if (!openssl_sign($signingInput, $signature, $privateKey, OPENSSL_ALGO_SHA256)) {
            throw new \RuntimeException('Falha ao assinar o JWT da service account do Vertex.');
        }

        $jwt = $signingInput . '.' . $this->base64UrlEncode($signature);

        $response = $this->http->postForm($tokenUri, [], [
            'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
            'assertion'  => $jwt,
        ]);

        $token     = $response['access_token'] ?? null;
        $expiresIn = (int) ($response['expires_in'] ?? 0);

        if (!$token) {
            throw new \RuntimeException('Vertex não retornou access_token. Resposta: ' . json_encode($response));
        }

        $this->cache->set(self::CACHE_KEY, $token, max(60, $expiresIn - 300));

        return $token;
    }

    private function loadCredentials(): array
    {
        if ($this->credentials !== null) {
            return $this->credentials;
        }

        $path = $this->config['key_path'] ?? '';
        if ($path === '' || !file_exists($path)) {
            throw new \RuntimeException("Chave da service account do Vertex não encontrada em: {$path}");
        }

        $json = json_decode((string) file_get_contents($path), true);
        if (!is_array($json) || empty($json['client_email']) || empty($json['private_key'])) {
            throw new \RuntimeException('Chave da service account do Vertex é inválida ou incompleta.');
        }

        return $this->credentials = $json;
    }

    private function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}
