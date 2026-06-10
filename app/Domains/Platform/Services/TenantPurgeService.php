<?php

namespace App\Domains\Platform\Services;

use App\Domains\Billing\Models\Subscription;
use App\Domains\Platform\Support\PlatformAuditRecorder;
use App\Domains\Tenancy\Services\CentralSettingsService;
use App\Models\Tenant;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class TenantPurgeService
{
    public function __construct(
        private CentralSettingsService $settings,
        private PlatformAuditRecorder $audit,
    ) {
    }

    public function graceDays(): int
    {
        return $this->settings->tenantPurgeGraceDays();
    }

    public function isEnabled(): bool
    {
        return $this->settings->tenantPurgeEnabled();
    }

    public function purgeExpired(bool $dryRun = false): array
    {
        $purged = [];
        $candidates = $this->candidates();

        foreach ($candidates as $tenant) {
            if ($dryRun) {
                $purged[] = $this->presentCandidate($tenant);

                continue;
            }

            $snapshot = $this->presentCandidate($tenant);
            $tenantId = $tenant->id;
            $tenant->delete();
            $this->audit->record(
                'platform.tenant.purged',
                properties: $snapshot,
                tenantId: $tenantId,
            );
            $purged[] = $snapshot;
        }

        return $purged;
    }

    public function candidates(): Collection
    {
        if (! $this->isEnabled()) {
            return collect();
        }

        $cutoff = now()->subDays($this->graceDays());

        return Tenant::query()
            ->with(['subscription', 'domains'])
            ->whereHas('subscription', function ($query) use ($cutoff) {
                $query->where(function ($expired) use ($cutoff) {
                    $expired->where(function ($trial) use ($cutoff) {
                        $trial
                            ->where('status', Subscription::STATUS_TRIAL)
                            ->whereNotNull('trial_ends_at')
                            ->where('trial_ends_at', '<=', $cutoff);
                    })->orWhere(function ($access) use ($cutoff) {
                        $access
                            ->whereNotNull('access_ends_at')
                            ->where('access_ends_at', '<=', $cutoff)
                            ->whereIn('status', [
                                Subscription::STATUS_CANCELLED,
                                Subscription::STATUS_PAST_DUE,
                            ]);
                    });
                });
            })
            ->orderBy('created_at')
            ->get();
    }

    public function expiredAt(?Subscription $subscription): ?Carbon
    {
        if (! $subscription) {
            return null;
        }

        if ($subscription->isTrialExpired()) {
            return $subscription->trial_ends_at;
        }

        if ($subscription->access_ends_at?->isPast()) {
            return $subscription->access_ends_at;
        }

        return null;
    }

    private function presentCandidate(Tenant $tenant): array
    {
        $subscription = $tenant->subscription;

        return [
            'id' => $tenant->id,
            'name' => $tenant->name,
            'slug' => $tenant->slug,
            'database' => $tenant->database()->getName(),
            'expired_at' => $this->expiredAt($subscription)?->toIso8601String(),
            'subscription_status' => $subscription?->status,
        ];
    }
}
