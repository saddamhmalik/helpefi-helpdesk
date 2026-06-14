<?php

namespace App\Domains\Tenancy\Repositories;

use App\Domains\Tenancy\Models\CentralSetting;
use Illuminate\Support\Facades\Cache;

class CentralSettingRepository
{
    private const CACHE_KEY = 'central_settings.current_id';

    private const CACHE_TTL_SECONDS = 300;

    public function current(): CentralSetting
    {
        if (! app()->environment('testing')) {
            $cachedId = Cache::store('central')->get(self::CACHE_KEY);

            if (is_int($cachedId)) {
                $setting = CentralSetting::query()->find($cachedId);

                if ($setting instanceof CentralSetting) {
                    return $setting;
                }
            }
        }

        $setting = $this->resolveOrCreate();

        if (! app()->environment('testing')) {
            Cache::store('central')->put(self::CACHE_KEY, $setting->id, self::CACHE_TTL_SECONDS);
        }

        return $setting;
    }

    public function update(CentralSetting $setting, array $data): CentralSetting
    {
        $setting->update($data);

        Cache::store('central')->forget(self::CACHE_KEY);

        return $setting->fresh();
    }

    private function resolveOrCreate(): CentralSetting
    {
        $setting = CentralSetting::query()->first();

        if ($setting instanceof CentralSetting) {
            return $setting;
        }

        return CentralSetting::query()->create([
            'trial_days' => (int) config('billing.trial_days', 14),
            'tenant_purge_grace_days' => 15,
            'tenant_purge_enabled' => true,
            'currency' => strtoupper((string) config('billing.currency', 'USD')),
        ]);
    }
}
