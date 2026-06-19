<?php

namespace App\Domains\Settings\Repositories;

use App\Domains\Settings\Models\HelpdeskSetting;
use App\Support\TenantCache;
use Illuminate\Support\Facades\Cache;

class HelpdeskSettingRepository
{
    private const CACHE_TTL_SECONDS = 300;

    private static array $currentByTenant = [];

    public function current(): HelpdeskSetting
    {
        if (! tenancy()->initialized) {
            return $this->loadCurrent();
        }

        $tenantId = tenant('id');

        if ($tenantId !== null && isset(self::$currentByTenant[$tenantId])) {
            return self::$currentByTenant[$tenantId];
        }

        $cacheKey = TenantCache::key('helpdesk_settings_id');

        $settingId = Cache::get($cacheKey);

        if (is_int($settingId)) {
            $setting = HelpdeskSetting::query()->find($settingId);
        } else {
            $setting = null;
        }

        if ($setting === null) {
            $setting = $this->loadCurrent();
            Cache::put($cacheKey, $setting->id, self::CACHE_TTL_SECONDS);
        }

        if ($tenantId !== null) {
            self::$currentByTenant[$tenantId] = $setting;
        }

        return $setting;
    }

    public function update(HelpdeskSetting $setting, array $data): HelpdeskSetting
    {
        $setting->update($data);
        $this->forgetCurrentCache();

        return $setting->fresh();
    }

    public function forgetCurrentCache(): void
    {
        if (! tenancy()->initialized) {
            return;
        }

        $tenantId = tenant('id');

        Cache::forget(TenantCache::key('helpdesk_settings_id'));
        Cache::forget(TenantCache::key('helpdesk_settings'));

        if ($tenantId !== null) {
            unset(self::$currentByTenant[$tenantId]);
        }
    }

    private function loadCurrent(): HelpdeskSetting
    {
        return HelpdeskSetting::query()->firstOrCreate([], [
            'ticket_number_prefix' => 'HD-',
            'contact_fields' => [],
            'ticket_fields' => [],
            'user_fields' => [],
            'auto_first_response_enabled' => false,
            'auto_first_response_body' => null,
            'email_blocklist' => [],
            'kb_deflection_enabled' => true,
            'kb_locales' => ['en'],
            'kb_default_locale' => 'en',
        ]);
    }
}
