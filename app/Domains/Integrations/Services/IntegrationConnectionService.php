<?php

namespace App\Domains\Integrations\Services;

use App\Domains\Billing\Services\BillingService;
use App\Domains\Integrations\Models\IntegrationConnection;
use App\Domains\Integrations\Models\Webhook;
use App\Domains\Integrations\Repositories\IntegrationConnectionRepository;
use App\Domains\Security\Support\AuditRecorder;
use Illuminate\Support\Str;

class IntegrationConnectionService
{
    public function __construct(
        private IntegrationConnectionRepository $connections,
        private SlackIntegrationService $slack,
        private BillingService $billing,
        private AuditRecorder $audit,
    ) {
    }

    public function snapshot(): array
    {
        $existing = $this->connections->all()->keyBy('provider');

        return collect([IntegrationConnection::PROVIDER_SLACK, IntegrationConnection::PROVIDER_JIRA, IntegrationConnection::PROVIDER_LINEAR])
            ->map(function (string $provider) use ($existing) {
                $connection = $existing->get($provider);

                return [
                    'provider' => $provider,
                    'label' => config("integrations.providers.{$provider}.label", ucfirst($provider)),
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
        ];
    }

    public function updateSlack(array $data): IntegrationConnection
    {
        $this->billing->assertFeature('integrations');

        $existing = $this->connections->findByProvider(IntegrationConnection::PROVIDER_SLACK);
        $config = $this->mergeSecrets($existing?->config ?? [], [
            'webhook_url' => $data['webhook_url'] ?? null,
            'channel' => $data['channel'] ?? null,
        ], ['webhook_url']);

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
        $this->billing->assertFeature('integrations');

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

        $connection = $this->connections->upsert(IntegrationConnection::PROVIDER_JIRA, [
            'config' => $config,
            'is_active' => $data['is_active'] ?? false,
        ]);

        $this->audit->record('integration.updated', $connection, ['provider' => 'jira']);

        return $connection;
    }

    public function updateLinear(array $data): IntegrationConnection
    {
        $this->billing->assertFeature('integrations');

        $existing = $this->connections->findByProvider(IntegrationConnection::PROVIDER_LINEAR);
        $config = $this->mergeSecrets($existing?->config ?? [], [
            'api_key' => $data['api_key'] ?? null,
            'team_id' => $data['team_id'] ?? null,
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
                'webhook_secret' => $config['webhook_secret'] ?? '',
            ],
            IntegrationConnection::PROVIDER_LINEAR => [
                'team_id' => $config['team_id'] ?? '',
                'done_state' => $config['done_state'] ?? 'Done',
                'open_state' => $config['open_state'] ?? 'Todo',
                'has_api_key' => ! empty($config['api_key']),
                'webhook_secret' => $config['webhook_secret'] ?? '',
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
