<?php

namespace App\Domains\Channels\Support;

use App\Support\TenantCache;
use Illuminate\Support\Facades\Cache;

class EmailSettingsPageCache
{
    public static function forget(): void
    {
        if (tenancy()->initialized) {
            Cache::forget(TenantCache::key('email_settings_reference'));
        }
    }
}
