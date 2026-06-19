<?php

namespace App\Domains\Tenancy\Services;

use App\Domains\Platform\Mail\TenantInfrastructureFailureMail;
use App\Domains\Tenancy\Models\TenantInfrastructure;
use App\Models\PlatformUser;
use Illuminate\Support\Facades\Mail;

class TenantInfrastructureAlertService
{
    public function notifyFailure(TenantInfrastructure $infrastructure, string $message, string $source): void
    {
        if (! (bool) config('tenant_infrastructure.alert_on_failure', true)) {
            return;
        }

        $recipients = $this->recipients();

        if ($recipients === []) {
            return;
        }

        $tenant = $infrastructure->tenant;

        foreach ($recipients as $email) {
            Mail::send(new TenantInfrastructureFailureMail(
                recipientEmail: $email,
                workspaceName: $tenant?->name ?? $infrastructure->tenant_id,
                workspaceSlug: $tenant?->slug ?? $infrastructure->tenant_id,
                message: $message,
                source: $source,
            ));
        }
    }

    private function recipients(): array
    {
        $configured = array_values(array_filter(array_map(
            'trim',
            explode(',', (string) config('tenant_infrastructure.alert_emails', '')),
        )));

        if ($configured !== []) {
            return $configured;
        }

        return PlatformUser::query()
            ->where('is_active', true)
            ->orderBy('id')
            ->get()
            ->filter(fn (PlatformUser $user) => $user->isBootstrapAdmin() || $user->hasPermission('tenants.manage'))
            ->pluck('email')
            ->filter()
            ->unique()
            ->values()
            ->all();
    }
}
