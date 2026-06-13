<?php

namespace App\Domains\Platform\Services;

use App\Domains\Platform\Support\PlatformAuditRecorder;
use App\Domains\Tenancy\Models\PendingRegistration;
use App\Domains\Tenancy\Repositories\PendingRegistrationRepository;
use App\Domains\Tenancy\Services\TenantProvisioningService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class PlatformPendingRegistrationService
{
    public function __construct(
        private PendingRegistrationRepository $registrations,
        private TenantProvisioningService $provisioning,
        private PlatformAuditRecorder $audit,
    ) {}

    public function list(int $perPage = 20, ?string $search = null, ?string $status = null): LengthAwarePaginator
    {
        return $this->registrations
            ->paginateUnverified($perPage, $search, $status === 'all' ? null : $status)
            ->through(fn (PendingRegistration $registration) => $this->present($registration));
    }

    public function stats(): array
    {
        return $this->registrations->stats();
    }

    public function delete(int $id): void
    {
        $registration = $this->registrations->findUnverified($id);

        if (! $registration) {
            throw (new ModelNotFoundException)->setModel(PendingRegistration::class, [$id]);
        }

        $snapshot = $this->present($registration);

        $this->registrations->delete($registration);

        $this->audit->record(
            'platform.registration.deleted',
            $registration,
            $snapshot,
        );
    }

    public function purgeExpired(): int
    {
        $expired = $this->registrations->stats()['expired'];

        if ($expired === 0) {
            return 0;
        }

        $removed = $this->registrations->deleteExpiredUnverified();

        if ($removed > 0) {
            $this->audit->record(
                'platform.registrations.purged_expired',
                properties: ['count' => $removed],
            );
        }

        return $removed;
    }

    private function present(PendingRegistration $registration): array
    {
        return [
            'id' => $registration->id,
            'organization_name' => $registration->organization_name,
            'slug' => $registration->slug,
            'workspace_url' => $this->provisioning->tenantDomain($registration->slug),
            'admin_name' => $registration->admin_name,
            'admin_email' => $registration->admin_email,
            'expires_at' => $registration->expires_at?->toIso8601String(),
            'created_at' => $registration->created_at?->toIso8601String(),
            'is_expired' => $registration->isExpired(),
            'status' => $registration->isExpired() ? 'expired' : 'active',
        ];
    }
}
