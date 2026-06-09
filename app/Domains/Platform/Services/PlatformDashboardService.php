<?php

namespace App\Domains\Platform\Services;

use App\Domains\Billing\Services\PlatformPaymentService;
use App\Domains\Platform\Repositories\PlatformTenantRepository;
use App\Domains\Platform\Repositories\PlatformUserRepository;
use App\Domains\Tenancy\Services\CentralSettingsService;
use App\Models\PlatformUser;
use App\Models\Tenant;

class PlatformDashboardService
{
    public function __construct(
        private PlatformTenantRepository $tenants,
        private PlatformUserRepository $users,
        private PlatformAuthorizationService $authorization,
        private PlatformTenantService $tenantService,
        private PlatformPaymentService $payments,
        private CentralSettingsService $settings,
    ) {
    }

    public function snapshot(PlatformUser $user): array
    {
        $data = [
            'stripe_enabled' => (bool) config('stripe.enabled'),
        ];

        if ($this->authorization->allows($user, 'tenants.view')) {
            $data['workspace_stats'] = $this->tenants->stats();
            $data['recent_workspaces'] = $this->tenants->recent(6)
                ->map(fn (Tenant $tenant) => $this->tenantService->presentForList($tenant))
                ->all();
        }

        if ($this->authorization->allows($user, 'users.view')) {
            $data['platform_user_count'] = PlatformUser::query()->count();
        }

        if ($this->authorization->allows($user, 'payments.view')) {
            $data['payment_stats'] = $this->payments->stats();
            $data['currency'] = $this->settings->currencyMeta();
        }

        return $data;
    }
}
