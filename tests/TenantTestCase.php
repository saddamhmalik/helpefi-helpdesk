<?php

namespace Tests;

use Tests\Concerns\InitializesTenancy;

abstract class TenantTestCase extends TestCase
{
    use InitializesTenancy;

    protected function setUp(): void
    {
        parent::setUp();

        $this->provisionTenancy('test');
        tenancy()->initialize($this->tenant);
    }

    protected function tearDown(): void
    {
        tenancy()->end();

        if (isset($this->tenant)) {
            $this->tenant->delete();
        }

        parent::tearDown();
    }

    protected function tenantDomain(): string
    {
        return $this->tenant->domains()->value('domain');
    }

    protected function tenantUrl(string $uri): string
    {
        return 'http://'.$this->tenantDomain().$uri;
    }

    protected function tenantGet(string $uri, array $headers = [])
    {
        return $this->get($this->tenantUrl($uri), $headers);
    }

    protected function tenantPut(string $uri, array $data = [], array $headers = [])
    {
        return $this->put($this->tenantUrl($uri), $data, $headers);
    }

    protected function tenantPost(string $uri, array $data = [], array $headers = [])
    {
        return $this->post($this->tenantUrl($uri), $data, $headers);
    }

    protected function tenantPostJson(string $uri, array $data = [], array $headers = [])
    {
        return $this->postJson($this->tenantUrl($uri), $data, $headers);
    }

    protected function tenantGetJson(string $uri, array $headers = [])
    {
        return $this->getJson($this->tenantUrl($uri), $headers);
    }
}
