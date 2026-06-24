<?php

namespace Tests\Feature;

use App\Support\ReferenceCacheInvalidator;
use App\Support\TenantCache;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TenantTestCase;

class ReferenceCacheInvalidatorTest extends TenantTestCase
{
    use RefreshDatabase;

    public function test_forget_all_clears_ticket_and_contact_reference_caches(): void
    {
        Cache::put(TenantCache::key('ticket_form_reference'), ['statuses' => []], 300);
        Cache::put(TenantCache::key('contact_form_reference'), ['tags' => []], 300);

        app(ReferenceCacheInvalidator::class)->forgetAll();

        $this->assertFalse(Cache::has(TenantCache::key('ticket_form_reference')));
        $this->assertFalse(Cache::has(TenantCache::key('contact_form_reference')));
    }
}
