<?php

namespace Tests\Unit;

use App\Domains\Tenancy\Support\CentralDomain;
use Tests\TestCase;

class CentralDomainTest extends TestCase
{
    public function test_is_central_host_matches_apex_and_www_from_central_app_domain(): void
    {
        config([
            'tenancy.central_app_domain' => 'helpefi.com',
            'tenancy.central_domains' => ['127.0.0.1', 'localhost'],
        ]);

        $this->assertTrue(CentralDomain::isCentralHost('helpefi.com'));
        $this->assertTrue(CentralDomain::isCentralHost('www.helpefi.com'));
        $this->assertFalse(CentralDomain::isCentralHost('help.helpefi.com'));
        $this->assertFalse(CentralDomain::isCentralHost('help.codikal.com'));
    }
}
