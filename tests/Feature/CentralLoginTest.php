<?php

namespace Tests\Feature;

use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TenantTestCase;

class CentralLoginTest extends TenantTestCase
{
    use RefreshDatabase;

    public function test_central_login_page_loads(): void
    {
        tenancy()->end();

        $this->get('http://'.config('tenancy.central_app_domain').'/login')
            ->assertOk();
    }

    public function test_central_home_page_loads_without_tenant_context(): void
    {
        tenancy()->end();

        $this->get('http://'.config('tenancy.central_app_domain').'/')
            ->assertOk();
    }

    public function test_central_login_redirects_to_tenant_workspace(): void
    {
        $tenant = Tenant::query()->where('slug', 'test')->firstOrFail();
        $domain = $tenant->domains()->value('domain');

        $this->post('http://'.config('tenancy.central_app_domain').'/login', [
            'slug' => 'test',
            'email' => 'admin@helpdesk.test',
        ])
            ->assertRedirect('http://'.$domain.'/login?email=admin%40helpdesk.test');
    }

    public function test_central_login_rejects_unknown_workspace(): void
    {
        $this->from('http://'.config('tenancy.central_app_domain').'/login')
            ->post('http://'.config('tenancy.central_app_domain').'/login', [
                'slug' => 'missing-workspace',
            ])
            ->assertRedirect('http://'.config('tenancy.central_app_domain').'/login')
            ->assertSessionHasErrors('slug');
    }
}
