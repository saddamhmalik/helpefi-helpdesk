<?php

namespace App\Domains\Integrations\Services;

use App\Domains\Integrations\Models\IntegrationConnection;
use App\Domains\Integrations\Repositories\IntegrationConnectionRepository;
use Shopify\Clients\Rest;
use Shopify\Context;

class ShopifyIntegrationService
{
    public function __construct(private IntegrationConnectionRepository $connections)
    {
    }

    public function testConnection(): bool
    {
        $connection = $this->connections->findByProvider(IntegrationConnection::PROVIDER_SHOPIFY);
        $config = $connection?->config ?? [];

        if (! $connection?->is_active || empty($config['shop']) || empty($config['access_token'])) {
            return false;
        }

        $this->initializeContext($config);
        $client = new Rest($config['shop'], $config['access_token']);
        $response = $client->get('shop');

        return $response->getStatusCode() === 200;
    }

    public function shopSnapshot(): ?array
    {
        $connection = $this->connections->findByProvider(IntegrationConnection::PROVIDER_SHOPIFY);
        $config = $connection?->config ?? [];

        if (! $connection?->is_active || empty($config['shop']) || empty($config['access_token'])) {
            return null;
        }

        $this->initializeContext($config);
        $client = new Rest($config['shop'], $config['access_token']);
        $response = $client->get('shop');
        $body = $response->getDecodedBody();

        return $body['shop'] ?? null;
    }

    private function initializeContext(array $config): void
    {
        Context::initialize(
            apiKey: $config['api_key'] ?? 'placeholder',
            apiSecretKey: $config['api_secret'] ?? 'placeholder',
            scopes: $config['scopes'] ?? 'read_orders,read_customers',
            hostName: parse_url((string) config('app.url'), PHP_URL_HOST) ?: 'localhost',
            sessionStorage: new \Shopify\Auth\FileSessionStorage('/tmp'),
            apiVersion: '2024-10',
            isEmbeddedApp: false,
            isPrivateApp: true,
        );
    }
}
