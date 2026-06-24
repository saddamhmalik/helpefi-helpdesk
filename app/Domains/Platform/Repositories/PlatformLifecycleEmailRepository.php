<?php

namespace App\Domains\Platform\Repositories;

use App\Domains\Platform\Models\TrialNurtureSend;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class PlatformLifecycleEmailRepository
{
    public function recordSend(string $tenantId, string $slug, ?Carbon $sentAt = null): void
    {
        TrialNurtureSend::query()->updateOrCreate(
            [
                'tenant_id' => $tenantId,
                'template_slug' => $slug,
            ],
            [
                'sent_at' => $sentAt ?? now(),
            ],
        );
    }

    public function alreadySent(string $tenantId, string $slug): bool
    {
        return TrialNurtureSend::query()
            ->where('tenant_id', $tenantId)
            ->where('template_slug', $slug)
            ->exists();
    }

    public function sentAt(string $tenantId, string $slug): ?Carbon
    {
        return TrialNurtureSend::query()
            ->where('tenant_id', $tenantId)
            ->where('template_slug', $slug)
            ->value('sent_at');
    }

    public function sendsForTenant(string $tenantId): Collection
    {
        return TrialNurtureSend::query()
            ->where('tenant_id', $tenantId)
            ->get(['template_slug', 'sent_at']);
    }
}
