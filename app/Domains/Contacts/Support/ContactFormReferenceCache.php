<?php

namespace App\Domains\Contacts\Support;

use App\Support\TenantCache;
use Illuminate\Support\Facades\Cache;

class ContactFormReferenceCache
{
    public static function forget(): void
    {
        if (tenancy()->initialized) {
            Cache::forget(TenantCache::key('contact_form_reference'));
        }
    }
}
