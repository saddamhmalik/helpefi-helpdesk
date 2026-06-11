<?php

namespace Tests\Unit;

use App\Domains\Auth\Services\AuthService;
use Illuminate\Http\Request;
use Tests\TestCase;

class AuthServiceTest extends TestCase
{
    public function test_post_login_redirect_ignores_intended_url_on_different_host(): void
    {
        $request = Request::create('http://help.helpefi.com/login', 'POST');
        $this->app->instance('request', $request);

        session()->put('url.intended', 'https://help.codikal.com/settings/billing?section=features');

        $redirect = app(AuthService::class)->resolvePostLoginRedirect('/settings');

        $this->assertSame('/settings', $redirect);
    }

    public function test_post_login_redirect_honors_intended_url_on_same_host(): void
    {
        $request = Request::create('http://help.helpefi.com/login', 'POST');
        $this->app->instance('request', $request);

        session()->put('url.intended', 'http://help.helpefi.com/settings/billing?section=features');

        $redirect = app(AuthService::class)->resolvePostLoginRedirect('/settings');

        $this->assertSame('http://help.helpefi.com/settings/billing?section=features', $redirect);
    }
}
