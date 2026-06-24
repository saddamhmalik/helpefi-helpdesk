<?php

namespace App\Domains\Integrations\Services;

use App\Domains\Billing\Contracts\FeatureEntitlementChecker;
use App\Domains\Integrations\Models\IntegrationConnection;
use App\Domains\Integrations\Models\Webhook;
use App\Domains\Integrations\Repositories\IntegrationConnectionRepository;
use App\Domains\Security\Support\AuditRecorder;
use App\Support\IntegrationWebhookUrlValidator;
use Illuminate\Support\Str;

class IntegrationConnectionService
{
    public function __construct(
        private IntegrationConnectionRepository $connections,
        private SlackIntegrationService $slack,
        private ShopifyIntegrationService $shopify,
        private HubspotIntegrationService $hubspot,
        private SalesforceIntegrationService $salesforce,
        private TeamsIntegrationService $teams,
        private FeatureEntitlementChecker $entitlements,
        private AuditRecorder $audit,
    ) {
    }

    public function snapshot(): array
    {
        $existing = $this->connections->all()->keyBy('provider');

        return collect(array_keys(config('integrations.providers', [])))
            ->map(function (string $provider) use ($existing) {
                $connection = $existing->get($provider);

                return [
                    'provider' => $provider,
                    'label' => config("integrations.providers.{$provider}.label", ucfirst($provider)),
                    'category' => config("integrations.providers.{$provider}.category", 'general'),
                    'is_active' => (bool) ($connection?->is_active ?? false),
                    'events' => $connection?->events ?? $this->defaultEvents($provider),
                    'config' => $this->maskedConfig($provider, $connection?->config ?? []),
                    'last_delivered_at' => $connection?->last_delivered_at?->toIso8601String(),
                    'last_error' => $connection?->last_error,
                ];
            })
            ->values()
            ->all();
    }

    public function meta(): array
    {
        return [
            'providers' => config('integrations.providers', []),
            'slack_events' => config('integrations.providers.slack.events', []),
            'inbound_urls' => [
                'jira' => url('/api/v1/integrations/inbound/jira'),
                'linear' => url('/api/v1/integrations/inbound/linear'),
            ],
            'zapier_hook_url' => url('/api/v1/integrations/inbound/zapier'),
        ];
    }

    public function updateShopify(array $data): IntegrationConnection
    {
        $this->entitlements->assertFeature('integrations');

        $existing = $this->connections->findByProvider(IntegrationConnection::PROVIDER_SHOPIFY);
        $config = $this->mergeSecrets($existing?->config ?? [], [
            'shop' => $data['shop'] ?? null,
            'access_token' => $data['access_token'] ?? null,
            'api_key' => $data['api_key'] ?? null,
            'api_secret' => $data['api_secret'] ?? null,
        ], ['access_token', 'api_secret']);

        $connection = $this->connections->upsert(IntegrationConnection::PROVIDER_SHOPIFY, [
            'config' => $config,
            'is_active' => $data['is_active'] ?? false,
        ]);

        $this->audit->record('integration.updated', $connection, ['provider' => 'shopify']);

        return $connection;
    }

    public function updateHubspot(array $data): IntegrationConnection
    {
        $this->entitlements->assertFeature('integrations');

        $existing = $this->connections->findByProvider(IntegrationConnection::PROVIDER_HUBSPOT);
        $config = $this->mergeSecrets($existing?->config ?? [], [
            'access_token' => $data['access_token'] ?? null,
        ], ['access_token']);

        $connection = $this->connections->upsert(IntegrationConnection::PROVIDER_HUBSPOT, [
            'config' => $config,
            'is_active' => $data['is_active'] ?? false,
        ]);

        $this->audit->record('integration.updated', $connection, ['provider' => 'hubspot']);

        return $connection;
    }

    public function updateSalesforce(array $data): IntegrationConnection
    {
        $this->entitlements->assertFeature('integrations');

        $existing = $this->connections->findByProvider(IntegrationConnection::PROVIDER_SALESFORCE);
        $config = $this->mergeSecrets($existing?->config ?? [], [
            'consumer_key' => $data['consumer_key'] ?? null,
            'consumer_secret' => $data['consumer_secret'] ?? null,
            'username' => $data['username'] ?? null,
            'password' => $data['password'] ?? null,
            'security_token' => $data['security_token'] ?? null,
            'login_url' => $data['login_url'] ?? 'https://login.salesforce.com',
        ], ['consumer_secret', 'password', 'security_token']);

        $connection = $this->connections->upsert(IntegrationConnection::PROVIDER_SALESFORCE, [
            'config' => $config,
            'is_active' => $data['is_active'] ?? false,
        ]);

        $this->audit->record('integration.updated', $connection, ['provider' => 'salesforce']);

        return $connection;
    }

    public function updateTeams(array $data): IntegrationConnection
    {
        $this->entitlements->assertFeature('integrations');

        $existing = $this->connections->findByProvider(IntegrationConnection::PROVIDER_MICROSOFT_TEAMS);
        $config = $this->mergeSecrets($existing?->config ?? [], [
            'webhook_url' => $data['webhook_url'] ?? null,
        ], ['webhook_url']);

        if (! empty($config['webhook_url'])) {
            IntegrationWebhookUrlValidator::assertTeamsWebhookUrl($config['webhook_url']);
        }

        $connection = $this->connections->upsert(IntegrationConnection::PROVIDER_MICROSOFT_TEAMS, [
            'config' => $config,
            'is_active' => $data['is_active'] ?? false,
        ]);

        $this->audit->record('integration.updated', $connection, ['provider' => 'microsoft_teams']);

        return $connection;
    }

    public function updateZapier(array $data): IntegrationConnection
    {
        $this->entitlements->assertFeature('integrations');

        $existing = $this->connections->findByProvider(IntegrationConnection::PROVIDER_ZAPIER);
        $config = array_merge($existing?->config ?? [], [
            'subscribe_secret' => $data['subscribe_secret'] ?? ($existing?->config['subscribe_secret'] ?? Str::random(32)),
        ]);

        $connection = $this->connections->upsert(IntegrationConnection::PROVIDER_ZAPIER, [
            'config' => $config,
            'is_active' => $data['is_active'] ?? false,
        ]);

        $this->audit->record('integration.updated', $connection, ['provider' => 'zapier']);

        return $connection;
    }

    public function testShopify(): bool
    {
        return $this->shopify->testConnection();
    }

    public function testHubspot(): bool
    {
        return $this->hubspot->testConnection();
    }

    public function testSalesforce(): bool
    {
        return $this->salesforce->testConnection();
    }

    public function testTeams(): bool
    {
        return $this->teams->sendTest();
    }

    public function updateSlack(array $data): IntegrationConnection
    {
        $this->entitlements->assertFeature('integrations');

        $existing = $this->connections->findByProvider(IntegrationConnection::PROVIDER_SLACK);
        $config = $this->mergeSecrets($existing?->config ?? [], [
            'webhook_url' => $data['webhook_url'] ?? null,
            'channel' => $data['channel'] ?? null,
        ], ['webhook_url']);

        if (! empty($config['webhook_url'])) {
            IntegrationWebhookUrlValidator::assertSlackWebhookUrl($config['webhook_url']);
        }

        $connection = $this->connections->upsert(IntegrationConnection::PROVIDER_SLACK, [
            'config' => $config,
            'events' => $data['events'] ?? $this->defaultEvents(IntegrationConnection::PROVIDER_SLACK),
            'is_active' => $data['is_active'] ?? false,
        ]);

        $this->audit->record('integration.updated', $connection, ['provider' => 'slack']);

        return $connection;
    }

    public function updateJira(array $data): IntegrationConnection
    {
        $this->entitlements->assertFeature('integrations');

        $existing = $this->connections->findByProvider(IntegrationConnection::PROVIDER_JIRA);
        $config = $this->mergeSecrets($existing?->config ?? [], [
            'site_url' => rtrim((string) ($data['site_url'] ?? ''), '/'),
            'email' => $data['email'] ?? null,
            'api_token' => $data['api_token'] ?? null,
            'project_key' => $data['project_key'] ?? null,
            'issue_type' => $data['issue_type'] ?? 'Task',
            'done_transition' => $data['done_transition'] ?? 'Done',
            'reopen_transition' => $data['reopen_transition'] ?? 'To Do',
            'webhook_secret' => $data['webhook_secret'] ?? ($existing?->config['webhook_secret'] ?? Str::random(32)),
        ], ['api_token', 'webhook_secret']);

        if (! empty($config['site_url'])) {
            IntegrationWebhookUrlValidator::assertJiraSiteUrl($config['site_url']);
        }

        $connection = $this->connections->upsert(IntegrationConnection::PROVIDER_JIRA, [
            'config' => $config,
            'is_active' => $data['is_active'] ?? false,
        ]);

        $this->audit->record('integration.updated', $connection, ['provider' => 'jira']);

        return $connection;
    }

    public function updateLinear(array $data): IntegrationConnection
    {
        $this->entitlements->assertFeature('integrations');

        $existing = $this->connections->findByProvider(IntegrationConnection::PROVIDER_LINEAR);
        $apiKey = isset($data['api_key']) ? preg_replace('/^Bearer\s+/i', '', trim((string) $data['api_key'])) : null;
        $teamId = isset($data['team_id'])
            ? LinearIntegrationService::normalizeTeamReference((string) $data['team_id'])
            : null;
        $config = $this->mergeSecrets($existing?->config ?? [], [
            'api_key' => $apiKey,
            'team_id' => $teamId,
            'done_state' => $data['done_state'] ?? 'Done',
            'open_state' => $data['open_state'] ?? 'Todo',
            'webhook_secret' => $data['webhook_secret'] ?? ($existing?->config['webhook_secret'] ?? Str::random(32)),
        ], ['api_key', 'webhook_secret']);

        $connection = $this->connections->upsert(IntegrationConnection::PROVIDER_LINEAR, [
            'config' => $config,
            'is_active' => $data['is_active'] ?? false,
        ]);

        $this->audit->record('integration.updated', $connection, ['provider' => 'linear']);

        return $connection;
    }

    public function testSlack(): bool
    {
        return $this->slack->sendTest();
    }

    public function updateProvider(string $provider, array $data): IntegrationConnection
    {
        return match ($provider) {
            IntegrationConnection::PROVIDER_SLACK => $this->updateSlack($data),
            IntegrationConnection::PROVIDER_JIRA => $this->updateJira($data),
            IntegrationConnection::PROVIDER_LINEAR => $this->updateLinear($data),
            IntegrationConnection::PROVIDER_SHOPIFY => $this->updateShopify($data),
            IntegrationConnection::PROVIDER_HUBSPOT => $this->updateHubspot($data),
            IntegrationConnection::PROVIDER_SALESFORCE => $this->updateSalesforce($data),
            IntegrationConnection::PROVIDER_MICROSOFT_TEAMS => $this->updateTeams($data),
            IntegrationConnection::PROVIDER_ZAPIER => $this->updateZapier($data),
            default => throw new \InvalidArgumentException('Unknown integration provider.'),
        };
    }

    public function testProvider(string $provider): bool
    {
        return match ($provider) {
            IntegrationConnection::PROVIDER_SLACK => $this->testSlack(),
            IntegrationConnection::PROVIDER_SHOPIFY => $this->testShopify(),
            IntegrationConnection::PROVIDER_HUBSPOT => $this->testHubspot(),
            IntegrationConnection::PROVIDER_SALESFORCE => $this->testSalesforce(),
            IntegrationConnection::PROVIDER_MICROSOFT_TEAMS => $this->testTeams(),
            default => throw new \InvalidArgumentException('Provider does not support connection tests.'),
        };
    }

    public function maskedProviderConfig(string $provider, array $config): array
    {
        return $this->maskedConfig($provider, $config);
    }

    public function revealSecret(string $provider, string $key): ?string
    {
        $connection = $this->connections->findByProvider($provider);

        return $connection?->config[$key] ?? null;
    }

    private function defaultEvents(string $provider): array
    {
        if ($provider !== IntegrationConnection::PROVIDER_SLACK) {
            return [];
        }

        return [
            Webhook::EVENT_TICKET_CREATED,
            Webhook::EVENT_TICKET_UPDATED,
            Webhook::EVENT_CUSTOMER_MESSAGE,
        ];
    }

    private function maskedConfig(string $provider, array $config): array
    {
        return match ($provider) {
            IntegrationConnection::PROVIDER_SLACK => [
                'webhook_url' => $config['webhook_url'] ?? '',
                'channel' => $config['channel'] ?? '',
                'has_webhook_url' => ! empty($config['webhook_url']),
            ],
            IntegrationConnection::PROVIDER_JIRA => [
                'site_url' => $config['site_url'] ?? '',
                'email' => $config['email'] ?? '',
                'project_key' => $config['project_key'] ?? '',
                'issue_type' => $config['issue_type'] ?? 'Task',
                'done_transition' => $config['done_transition'] ?? 'Done',
                'reopen_transition' => $config['reopen_transition'] ?? 'To Do',
                'has_api_token' => ! empty($config['api_token']),
                'has_webhook_secret' => ! empty($config['webhook_secret']),
            ],
            IntegrationConnection::PROVIDER_LINEAR => [
                'team_id' => $config['team_id'] ?? '',
                'done_state' => $config['done_state'] ?? 'Done',
                'open_state' => $config['open_state'] ?? 'Todo',
                'has_api_key' => ! empty($config['api_key']),
                'has_webhook_secret' => ! empty($config['webhook_secret']),
            ],
            IntegrationConnection::PROVIDER_SHOPIFY => [
                'shop' => $config['shop'] ?? '',
                'has_access_token' => ! empty($config['access_token']),
            ],
            IntegrationConnection::PROVIDER_HUBSPOT => [
                'has_access_token' => ! empty($config['access_token']),
            ],
            IntegrationConnection::PROVIDER_SALESFORCE => [
                'username' => $config['username'] ?? '',
                'login_url' => $config['login_url'] ?? 'https://login.salesforce.com',
                'has_consumer_secret' => ! empty($config['consumer_secret']),
                'has_password' => ! empty($config['password']),
            ],
            IntegrationConnection::PROVIDER_MICROSOFT_TEAMS => [
                'webhook_url' => $config['webhook_url'] ?? '',
                'has_webhook_url' => ! empty($config['webhook_url']),
            ],
            IntegrationConnection::PROVIDER_ZAPIER => [
                'has_subscribe_secret' => ! empty($config['subscribe_secret']),
            ],
            default => [],
        };
    }

    private function mergeSecrets(array $existing, array $incoming, array $secretKeys): array
    {
        $merged = array_merge($existing, array_filter($incoming, fn ($value) => $value !== null && $value !== ''));

        foreach ($secretKeys as $key) {
            if (($incoming[$key] ?? '') === '' && ! empty($existing[$key])) {
                $merged[$key] = $existing[$key];
            }
        }

        return $merged;
    }
}
