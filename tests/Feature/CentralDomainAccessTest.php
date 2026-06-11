<?php

namespace Tests\Feature;

use Tests\TestCase;

class CentralDomainAccessTest extends TestCase
{
    public function test_www_central_redirects_to_apex(): void
    {
        $central = config('tenancy.central_app_domain');

        $this->get('http://www.'.$central.'/pricing')
            ->assertRedirect('http://'.$central.'/pricing');
    }

}
