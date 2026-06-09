<?php

namespace Tests\Feature;

use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CentralRegisterTest extends TestCase
{
    use RefreshDatabase;

    public function test_inertia_register_redirects_to_tenant_workspace(): void
    {
        $response = $this->post('http://'.config('tenancy.central_app_domain').'/register', [
            'organization_name' => 'Acme Support',
            'slug' => 'acme-reg-test',
            'name' => 'Jane Admin',
            'email' => 'jane@acme.test',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'plan' => config('billing.default_plan', 'enterprise'),
        ], [
            'X-Inertia' => 'true',
            'X-Requested-With' => 'XMLHttpRequest',
        ]);

        $tenant = Tenant::query()->where('slug', 'acme-reg-test')->firstOrFail();
        $domain = $tenant->domains()->value('domain');

        $response
            ->assertStatus(409)
            ->assertHeader('X-Inertia-Location');

        $location = $response->headers->get('X-Inertia-Location');

        $this->assertStringStartsWith('http://'.$domain.'/welcome', $location);
        $this->assertStringContainsString('email=jane%40acme.test', $location);
        $this->assertStringContainsString('signature=', $location);
    }
}
