<?php

namespace App\Domains\Channels\Services;

use App\Domains\Billing\Services\BillingService;
use App\Domains\Channels\Models\EmailInbox;
use App\Domains\Channels\Repositories\EmailInboxRepository;
use App\Domains\Security\Support\AuditRecorder;
use App\Domains\Tenancy\Services\TenantRouteRegistryService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\ValidationException;

class EmailInboxService
{
    public function __construct(
        private EmailInboxRepository $inboxes,
        private BillingService $billing,
        private AuditRecorder $audit,
        private TenantRouteRegistryService $tenantRoutes,
    ) {
    }

    public function list(): Collection
    {
        return $this->inboxes->all();
    }

    public function listForSettings(): array
    {
        return $this->inboxes->all()->map(function (EmailInbox $inbox) {
            $inbox->makeVisible(['inbound_token']);

            return $this->toArray($inbox, revealToken: true);
        })->all();
    }

    public function mailboxProviders(): array
    {
        return config('helpdesk.mailbox_providers', []);
    }

    public function oauthProviders(): array
    {
        return app(\App\Domains\Channels\Services\OAuth\MailOAuthProviderFactory::class)->configuredProviders();
    }

    public function create(array $data): array
    {
        $this->billing->assertFeature('channels');

        $inbox = $this->inboxes->create([
            'name' => $data['name'],
            'address' => $data['address'],
            'brand_id' => $data['brand_id'] ?? app(\App\Domains\Brands\Services\BrandService::class)->default()->id,
            'department_id' => $data['department_id'] ?? null,
            'team_id' => $data['team_id'] ?? null,
            'aliases' => $this->normalizeAliases($data['aliases'] ?? []),
            'is_active' => $data['is_active'] ?? true,
            'inbound_method' => $data['inbound_method'] ?? 'webhook',
        ]);

        $payload = $this->buildInboundPayload($data, $inbox);

        if (($payload['mailbox_encryption'] ?? null) === 'none') {
            $payload['mailbox_encryption'] = null;
        }

        if (array_key_exists('mailbox_password', $data) && ($data['mailbox_password'] === null || $data['mailbox_password'] === '')) {
            unset($payload['mailbox_password']);
        }

        $inbox = $this->inboxes->update($inbox, $payload);

        $this->audit->record('email.inbox_created', $inbox, [
            'address' => $inbox->address,
            'name' => $inbox->name,
        ]);

        $this->syncInboundRoutes($inbox);

        return $this->toArray($inbox, revealToken: true);
    }

    public function update(int $id, array $data): array
    {
        $this->billing->assertFeature('channels');

        $inbox = $this->inboxes->find($id);
        $previous = $this->routeSnapshot($inbox);
        $payload = array_merge(
            $this->buildBasicPayload($data),
            $this->buildInboundPayload($data, $inbox),
        );

        if (($payload['mailbox_encryption'] ?? null) === 'none') {
            $payload['mailbox_encryption'] = null;
        }

        if (array_key_exists('mailbox_password', $data) && ($data['mailbox_password'] === null || $data['mailbox_password'] === '')) {
            unset($payload['mailbox_password']);
        }

        $inbox = $this->inboxes->update($inbox, $payload);

        $this->audit->record('email.inbox_updated', $inbox, [
            'address' => $inbox->address,
            'name' => $inbox->name,
        ]);

        $this->replaceInboundRoutes($previous, $inbox);

        return $this->toArray($inbox, revealToken: true);
    }

    public function find(int $id): EmailInbox
    {
        return $this->inboxes->find($id);
    }

    public function delete(int $id): void
    {
        $inbox = $this->inboxes->find($id);
        $previous = $this->routeSnapshot($inbox);
        $this->inboxes->delete($inbox);

        $this->audit->record('email.inbox_deleted', $inbox, [
            'address' => $inbox->address,
        ]);

        $this->removeInboundRoutes($previous);
    }

    public function regenerateToken(int $id): array
    {
        $inbox = $this->inboxes->find($id);
        $previousToken = $inbox->inbound_token;
        $inbox = $this->inboxes->update($inbox, [
            'inbound_token' => $this->inboxes->generateToken(),
        ]);

        $this->audit->record('email.inbox_token_regenerated', $inbox, [
            'address' => $inbox->address,
        ]);

        if (tenant('id')) {
            $this->tenantRoutes->unregisterInboundToken($previousToken);
            $this->tenantRoutes->registerInboundToken(tenant('id'), $inbox->inbound_token);
        }

        return $this->toArray($inbox, revealToken: true);
    }

    public function resolveForInbound(?string $token, ?string $toEmail): ?EmailInbox
    {
        if ($token) {
            $inbox = $this->inboxes->findByToken($token);

            if ($inbox) {
                return $inbox;
            }
        }

        if ($toEmail) {
            return $this->inboxes->findByAddress(strtolower($toEmail));
        }

        return $this->inboxes->findByToken(config('helpdesk.inbound_email_token') ?: 'dev-inbound-token')
            ?? $this->inboxes->all()->firstWhere('is_active', true);
    }

    public function assertUniqueAddress(string $address, ?int $ignoreId = null): void
    {
        $query = EmailInbox::query()->where('address', strtolower($address));

        if ($ignoreId) {
            $query->where('id', '!=', $ignoreId);
        }

        if ($query->exists()) {
            throw ValidationException::withMessages([
                'address' => 'This email address is already connected. Edit the existing inbox below instead of creating a new one.',
            ]);
        }
    }

    private function buildBasicPayload(array $data): array
    {
        $payload = [];

        foreach (['name', 'address', 'is_active', 'brand_id', 'department_id', 'team_id', 'aliases'] as $field) {
            if (array_key_exists($field, $data)) {
                $payload[$field] = $field === 'aliases'
                    ? $this->normalizeAliases($data['aliases'] ?? [])
                    : $data[$field];
            }
        }

        return $payload;
    }

    private function buildInboundPayload(array $data, EmailInbox $inbox): array
    {
        $method = $data['inbound_method'] ?? $inbox->inbound_method ?? 'webhook';

        if ($method === 'oauth') {
            return [
                'inbound_method' => 'oauth',
                'poll_enabled' => true,
                'oauth_provider' => $data['oauth_provider'] ?? $inbox->oauth_provider,
                'mailbox_provider' => null,
                'mailbox_protocol' => null,
                'mailbox_host' => null,
                'mailbox_port' => null,
                'mailbox_encryption' => null,
                'mailbox_username' => null,
                'mailbox_password' => null,
            ];
        }

        $payload = [
            'inbound_method' => $method,
            'poll_enabled' => $method === 'poll',
            'oauth_provider' => null,
            ...$this->clearOAuthFields(),
        ];

        if ($method === 'poll') {
            $provider = $data['mailbox_provider'] ?? $inbox->mailbox_provider;
            $preset = $provider ? config("helpdesk.mailbox_providers.{$provider}") : null;

            $payload = array_merge($payload, [
                'mailbox_provider' => $provider,
                'mailbox_protocol' => $data['mailbox_protocol'] ?? ($preset['protocol'] ?? $inbox->mailbox_protocol ?? 'imap'),
                'mailbox_host' => $data['mailbox_host'] ?? ($preset['host'] ?? $inbox->mailbox_host),
                'mailbox_port' => $data['mailbox_port'] ?? ($preset['port'] ?? $inbox->mailbox_port),
                'mailbox_encryption' => $data['mailbox_encryption'] ?? ($preset['encryption'] ?? $inbox->mailbox_encryption),
                'mailbox_username' => $data['mailbox_username'] ?? $inbox->mailbox_username ?? $inbox->address,
                'mailbox_folder' => $data['mailbox_folder'] ?? ($preset['folder'] ?? $inbox->mailbox_folder ?? 'INBOX'),
            ]);

            if (array_key_exists('mailbox_password', $data) && $data['mailbox_password'] !== null && $data['mailbox_password'] !== '') {
                $payload['mailbox_password'] = app(OutboundSmtpResolver::class)->normalizePassword($data['mailbox_password']);
            }
        }

        if ($method === 'webhook') {
            $payload['poll_error'] = null;
        }

        return $payload;
    }

    private function clearOAuthFields(): array
    {
        return [
            'oauth_access_token' => null,
            'oauth_refresh_token' => null,
            'oauth_token_expires_at' => null,
            'oauth_connected_email' => null,
            'oauth_metadata' => null,
        ];
    }

    private function toArray(EmailInbox $inbox, bool $revealToken = false): array
    {
        $data = [
            'id' => $inbox->id,
            'brand_id' => $inbox->brand_id,
            'department_id' => $inbox->department_id,
            'team_id' => $inbox->team_id,
            'name' => $inbox->name,
            'address' => $inbox->address,
            'aliases' => $inbox->aliases ?? [],
            'is_active' => $inbox->is_active,
            'inbound_method' => $inbox->inbound_method ?? 'webhook',
            'inbound_token_preview' => substr($inbox->inbound_token, 0, 8).'…',
            'inbound_webhook_url' => url('/api/v1/channels/inbound/email'),
            'mailbox_provider' => $inbox->mailbox_provider,
            'mailbox_protocol' => $inbox->mailbox_protocol ?? 'imap',
            'mailbox_host' => $inbox->mailbox_host,
            'mailbox_port' => $inbox->mailbox_port,
            'mailbox_encryption' => $inbox->mailbox_encryption ?? 'none',
            'mailbox_username' => $inbox->mailbox_username ?? $inbox->address,
            'mailbox_folder' => $inbox->mailbox_folder ?? 'INBOX',
            'has_mailbox_password' => (bool) $inbox->mailbox_password,
            'oauth_provider' => $inbox->oauth_provider,
            'oauth_connected_email' => $inbox->oauth_connected_email,
            'oauth_connected' => $inbox->isOAuthConnected(),
            'last_polled_at' => $inbox->last_polled_at?->toIso8601String(),
            'poll_error' => $inbox->poll_error,
        ];

        if ($revealToken) {
            $data['inbound_token'] = $inbox->inbound_token;
        }

        return $data;
    }

    private function normalizeAliases(array|string|null $aliases): array
    {
        if (is_string($aliases)) {
            $aliases = preg_split('/\r?\n/', $aliases) ?: [];
        }

        return collect($aliases ?? [])
            ->map(fn ($alias) => strtolower(trim((string) $alias)))
            ->filter(fn ($alias) => filter_var($alias, FILTER_VALIDATE_EMAIL))
            ->unique()
            ->values()
            ->all();
    }

    private function routeSnapshot(EmailInbox $inbox): array
    {
        return [
            'token' => $inbox->inbound_token,
            'emails' => $this->inboundEmails($inbox),
        ];
    }

    private function inboundEmails(EmailInbox $inbox): array
    {
        return array_values(array_unique(array_filter([
            strtolower(trim($inbox->address)),
            ...array_map(fn ($alias) => strtolower(trim((string) $alias)), $inbox->aliases ?? []),
        ])));
    }

    private function syncInboundRoutes(EmailInbox $inbox): void
    {
        if (! tenant('id')) {
            return;
        }

        $tenantId = tenant('id');
        $this->tenantRoutes->registerInboundToken($tenantId, $inbox->inbound_token);

        foreach ($this->inboundEmails($inbox) as $email) {
            $this->tenantRoutes->registerInboundEmail($tenantId, $email);
        }
    }

    private function replaceInboundRoutes(array $previous, EmailInbox $inbox): void
    {
        $this->removeInboundRoutes($previous);
        $this->syncInboundRoutes($inbox);
    }

    private function removeInboundRoutes(array $snapshot): void
    {
        if ($snapshot['token'] ?? null) {
            $this->tenantRoutes->unregisterInboundToken($snapshot['token']);
        }

        foreach ($snapshot['emails'] ?? [] as $email) {
            $this->tenantRoutes->unregisterInboundEmail($email);
        }
    }
}
