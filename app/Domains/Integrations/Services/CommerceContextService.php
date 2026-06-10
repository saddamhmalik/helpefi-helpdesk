<?php

namespace App\Domains\Integrations\Services;

use App\Domains\Integrations\Models\IntegrationConnection;
use App\Domains\Integrations\Repositories\IntegrationConnectionRepository;
use Shopify\Clients\Rest;
use Shopify\Context;

class CommerceContextService
{
    public function __construct(private IntegrationConnectionRepository $connections)
    {
    }

    public function snapshotForEmail(?string $email): ?array
    {
        if (! $email) {
            return null;
        }

        $connection = $this->connections->findByProvider(IntegrationConnection::PROVIDER_SHOPIFY);
        $config = $connection?->config ?? [];

        if (! $connection?->is_active || empty($config['shop']) || empty($config['access_token'])) {
            return null;
        }

        $orders = $this->recentOrdersForEmail($config, $email);

        if ($orders === []) {
            return null;
        }

        return [
            'provider' => IntegrationConnection::PROVIDER_SHOPIFY,
            'provider_label' => 'Shopify',
            'shop' => $config['shop'],
            'recent_orders' => $orders,
        ];
    }

    private function recentOrdersForEmail(array $config, string $email): array
    {
        try {
            $this->initializeContext($config);
            $client = new Rest($config['shop'], $config['access_token']);

            $search = $client->get('customers/search', ['query' => 'email:'.$email]);
            $customers = $search->getDecodedBody()['customers'] ?? [];
            $customerId = $customers[0]['id'] ?? null;

            if (! $customerId) {
                return [];
            }

            $ordersResponse = $client->get('customers/'.$customerId.'/orders', [
                'limit' => 3,
                'status' => 'any',
            ]);
            $orders = $ordersResponse->getDecodedBody()['orders'] ?? [];
            $shop = $config['shop'];

            return collect($orders)->map(function (array $order) use ($shop) {
                return [
                    'id' => (string) ($order['id'] ?? ''),
                    'name' => $order['name'] ?? '#'.$order['order_number'] ?? '',
                    'total' => $order['total_price'] ?? null,
                    'currency' => $order['currency'] ?? null,
                    'financial_status' => $order['financial_status'] ?? null,
                    'fulfillment_status' => $order['fulfillment_status'] ?? null,
                    'created_at' => isset($order['created_at'])
                        ? date('c', strtotime($order['created_at']))
                        : null,
                    'url' => 'https://'.$shop.'/admin/orders/'.($order['id'] ?? ''),
                ];
            })->values()->all();
        } catch (\Throwable) {
            return [];
        }
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
