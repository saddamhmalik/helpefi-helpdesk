<?php

namespace Tests\Unit;

use App\Domains\Tenancy\Services\TenantDomainService;
use Tests\TestCase;

class TenantDomainServiceTest extends TestCase
{
    public function test_url_for_host_includes_non_default_port_from_app_url(): void
    {
        config(['app.url' => 'http://helpdesk.test:8081']);

        $service = app(TenantDomainService::class);
        $method = new \ReflectionMethod($service, 'urlForHost');
        $method->setAccessible(true);

        $this->assertSame(
            'http://acme.helpdesk.test:8081',
            $method->invoke($service, 'acme.helpdesk.test'),
        );
    }

    public function test_url_for_host_omits_default_http_port(): void
    {
        config(['app.url' => 'http://helpdesk.test']);

        $service = app(TenantDomainService::class);
        $method = new \ReflectionMethod($service, 'urlForHost');
        $method->setAccessible(true);

        $this->assertSame(
            'http://acme.helpdesk.test',
            $method->invoke($service, 'acme.helpdesk.test'),
        );
    }
}
