<?php

namespace App\Domains\Tickets\Support;

use App\Support\TenantCache;
use Illuminate\Support\Facades\Cache;

class TicketFormReferenceCache
{
    public static function forget(): void
    {
        if (tenancy()->initialized) {
            Cache::forget(TenantCache::key('ticket_form_reference'));
        }
    }
}
