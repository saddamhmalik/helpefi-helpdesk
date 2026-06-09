<?php

namespace App\Domains\Security\Services;

use App\Domains\Security\Repositories\SecuritySettingRepository;
use App\Models\User;

class SecuritySettingService
{
    public function __construct(
        private SecuritySettingRepository $settings,
        private AuditLogService $audit,
    ) {
    }

    public function snapshot(): array
    {
        $setting = $this->settings->current();

        return [
            'mfa_required_for_agents' => $setting->mfa_required_for_agents,
            'audit_retention_days' => $setting->audit_retention_days,
            'closed_ticket_retention_days' => $setting->closed_ticket_retention_days,
        ];
    }

    public function observability(): array
    {
        $agents = User::query()
            ->whereHas('roles', fn ($query) => $query->whereIn('name', ['admin', 'agent']))
            ->get(['id', 'two_factor_confirmed_at']);

        $enabled = $agents->filter(fn (User $user) => $user->hasTwoFactorEnabled())->count();

        return [
            'settings' => $this->snapshot(),
            'mfa_adoption' => [
                'enabled' => $enabled,
                'total' => $agents->count(),
            ],
            'audit_summary' => app(AuditLogService::class)->summary(7),
        ];
    }

    public function update(array $data): array
    {
        $setting = $this->settings->update($this->settings->current(), [
            'mfa_required_for_agents' => $data['mfa_required_for_agents'] ?? false,
            'audit_retention_days' => $data['audit_retention_days'] ?? 90,
            'closed_ticket_retention_days' => $data['closed_ticket_retention_days'] ?? null,
        ]);

        $this->audit->record(
            'security.settings_updated',
            auth()->id(),
            auth()->user()?->email,
            properties: $this->snapshot(),
        );

        return [
            'mfa_required_for_agents' => $setting->mfa_required_for_agents,
            'audit_retention_days' => $setting->audit_retention_days,
            'closed_ticket_retention_days' => $setting->closed_ticket_retention_days,
        ];
    }

    public function mfaRequiredForAgents(): bool
    {
        return $this->settings->current()->mfa_required_for_agents;
    }

    public function userMustEnrollMfa(User $user): bool
    {
        if (! $this->mfaRequiredForAgents()) {
            return false;
        }

        if ($user->hasRole('customer')) {
            return false;
        }

        return ! $user->hasTwoFactorEnabled();
    }
}
