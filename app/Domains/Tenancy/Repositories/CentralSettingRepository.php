<?php

namespace App\Domains\Tenancy\Repositories;

use App\Domains\Tenancy\Models\CentralSetting;

class CentralSettingRepository
{
    public function current(): CentralSetting
    {
        $setting = CentralSetting::query()->first();

        if ($setting) {
            return $setting;
        }

        return CentralSetting::query()->create([
            'trial_days' => (int) config('billing.trial_days', 14),
            'tenant_purge_grace_days' => 15,
            'tenant_purge_enabled' => true,
            'currency' => strtoupper((string) config('billing.currency', 'USD')),
        ]);
    }

    public function update(CentralSetting $setting, array $data): CentralSetting
    {
        $setting->update($data);

        return $setting->fresh();
    }
}
