<?php

namespace App\Domains\Security\Repositories;

use App\Domains\Security\Models\SecuritySetting;

class SecuritySettingRepository
{
    public function current(): SecuritySetting
    {
        return SecuritySetting::query()->firstOrCreate([], [
            'mfa_required_for_agents' => false,
            'audit_retention_days' => 90,
            'closed_ticket_retention_days' => null,
        ]);
    }

    public function update(SecuritySetting $setting, array $data): SecuritySetting
    {
        $setting->update($data);

        return $setting->fresh();
    }
}
