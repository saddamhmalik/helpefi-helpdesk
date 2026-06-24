<?php

namespace Tests\Unit;

use App\Support\PortalRateLimiters;
use Illuminate\Cache\RateLimiting\Unlimited;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class PortalRateLimitersTest extends TestCase
{
    public function test_local_environment_disables_portal_rate_limits(): void
    {
        $this->app->detectEnvironment(fn () => 'local');

        PortalRateLimiters::register();

        $request = Request::create('/portal/default/services/test', 'POST');
        $limit = RateLimiter::limiter('portal-ticket-submit')($request);

        $this->assertInstanceOf(Unlimited::class, $limit);
    }
}
