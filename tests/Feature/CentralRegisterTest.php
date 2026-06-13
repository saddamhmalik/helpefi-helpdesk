<?php

namespace Tests\Feature;

use App\Domains\Tenancy\Models\PendingRegistration;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CentralRegisterTest extends TestCase
{
    use RefreshDatabase;

    public function test_register_creates_pending_registration_without_tenant(): void
    {
        $this->post('http://'.config('tenancy.central_app_domain').'/register', [
            'organization_name' => 'Acme Support',
            'slug' => 'acme-reg-test',
            'name' => 'Jane Admin',
            'email' => 'jane@acme.test',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ], [
            'X-Inertia' => 'true',
            'X-Requested-With' => 'XMLHttpRequest',
        ])->assertStatus(200);

        $this->assertFalse(Tenant::query()->where('slug', 'acme-reg-test')->exists());

        $pending = PendingRegistration::query()->where('admin_email', 'jane@acme.test')->firstOrFail();

        $this->assertSame('acme-reg-test', $pending->slug);
        $this->assertNull($pending->verified_at);
        $this->assertTrue(Hash::check('password123', $pending->password));
    }

    public function test_verification_link_provisions_tenant_and_redirects_to_welcome(): void
    {
        $this->post('http://'.config('tenancy.central_app_domain').'/register', [
            'organization_name' => 'Acme Support',
            'slug' => 'acme-verify-test',
            'name' => 'Jane Admin',
            'email' => 'verify@acme.test',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $pending = PendingRegistration::query()->where('admin_email', 'verify@acme.test')->firstOrFail();

        $response = $this->get('http://'.config('tenancy.central_app_domain').'/register/verify/'.$pending->token);

        $tenant = Tenant::query()->where('slug', 'acme-verify-test')->firstOrFail();
        $domain = $tenant->domains()->value('domain');

        $response->assertRedirect();
        $this->assertStringStartsWith('http://'.$domain.'/welcome?token=', $response->headers->get('Location'));
        $this->assertFalse(PendingRegistration::query()->where('id', $pending->id)->exists());
    }

    public function test_invalid_verification_token_redirects_back_to_register(): void
    {
        $this->get('http://'.config('tenancy.central_app_domain').'/register/verify/invalid-token')
            ->assertRedirect(route('central.register'));

        $this->assertSame(0, Tenant::query()->count());
    }
}
