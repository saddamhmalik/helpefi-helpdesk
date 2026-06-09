<?php

namespace App\Domains\Tenancy\Services;

use App\Domains\Channels\Models\Channel;
use App\Domains\Channels\Models\EmailInbox;
use App\Domains\Tenancy\Models\TenantRouteMapping;
use App\Domains\Tenancy\Repositories\TenantRouteMappingRepository;
use App\Models\Tenant;

class TenantRouteRegistryService
{
    public function __construct(private TenantRouteMappingRepository $mappings)
    {
    }

    public function registerWidgetKey(string $tenantId, string $widgetKey): void
    {
        if ($widgetKey === '') {
            return;
        }

        $this->mappings->upsert($tenantId, TenantRouteMapping::TYPE_WIDGET_KEY, $widgetKey);
    }

    public function unregisterWidgetKey(string $widgetKey): void
    {
        if ($widgetKey === '') {
            return;
        }

        $this->mappings->deleteByTypeAndKey(TenantRouteMapping::TYPE_WIDGET_KEY, $widgetKey);
    }

    public function registerInboundToken(string $tenantId, string $token): void
    {
        if ($token === '') {
            return;
        }

        $this->mappings->upsert($tenantId, TenantRouteMapping::TYPE_INBOUND_TOKEN, $token);
    }

    public function registerInboundEmail(string $tenantId, string $email): void
    {
        $normalized = strtolower(trim($email));

        if ($normalized === '') {
            return;
        }

        $this->mappings->upsert($tenantId, TenantRouteMapping::TYPE_INBOUND_EMAIL, $normalized);
    }

    public function unregisterInboundToken(string $token): void
    {
        if ($token === '') {
            return;
        }

        $this->mappings->deleteByTypeAndKey(TenantRouteMapping::TYPE_INBOUND_TOKEN, $token);
    }

    public function unregisterInboundEmail(string $email): void
    {
        $normalized = strtolower(trim($email));

        if ($normalized === '') {
            return;
        }

        $this->mappings->deleteByTypeAndKey(TenantRouteMapping::TYPE_INBOUND_EMAIL, $normalized);
    }

    public function resolveTenantId(string $type, string $lookupKey): ?string
    {
        return $this->mappings->findTenantId($type, $lookupKey);
    }

    public function resolveInboundTenant(?string $token, ?string $toEmail): ?string
    {
        if ($token) {
            $tenantId = $this->resolveTenantId(TenantRouteMapping::TYPE_INBOUND_TOKEN, $token);

            if ($tenantId) {
                return $tenantId;
            }
        }

        if ($toEmail) {
            return $this->resolveTenantId(
                TenantRouteMapping::TYPE_INBOUND_EMAIL,
                strtolower(trim($toEmail)),
            );
        }

        return null;
    }

    public function syncCurrentTenant(): void
    {
        $tenantId = tenant('id');

        if (! $tenantId) {
            return;
        }

        $this->mappings->deleteByTenant($tenantId);

        $chat = Channel::query()->where('slug', 'chat')->first();

        if ($chat && filled($chat->settings['widget_key'] ?? null)) {
            $this->registerWidgetKey($tenantId, $chat->settings['widget_key']);
        }

        EmailInbox::query()->get()->each(function (EmailInbox $inbox) use ($tenantId): void {
            $this->registerInboundToken($tenantId, $inbox->inbound_token);
            $this->registerInboundEmail($tenantId, $inbox->address);

            foreach ($inbox->aliases ?? [] as $alias) {
                $this->registerInboundEmail($tenantId, (string) $alias);
            }
        });
    }

    public function syncTenant(Tenant $tenant): void
    {
        tenancy()->initialize($tenant);

        try {
            $this->syncCurrentTenant();
        } finally {
            tenancy()->end();
        }
    }
}
