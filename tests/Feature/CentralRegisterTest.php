<?php

namespace Tests\Feature;

use App\Domains\Tenancy\Models\PendingRegistration;
use App\Domains\Tenancy\Services\RegistrationVerificationService;
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
        ])->assertRedirect(route('central.register'));

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

    public function test_register_returns_validation_errors_for_password_mismatch(): void
    {
        $this->from('http://'.config('tenancy.central_app_domain').'/register')
            ->post('http://'.config('tenancy.central_app_domain').'/register', [
                'organization_name' => 'Acme Support',
                'slug' => 'acme-invalid',
                'name' => 'Jane Admin',
                'email' => 'jane@acme.test',
                'password' => 'password123',
                'password_confirmation' => 'different-password',
            ], [
                'X-Inertia' => 'true',
                'X-Requested-With' => 'XMLHttpRequest',
            ])
            ->assertSessionHasErrors('password');

        $this->assertDatabaseMissing('pending_registrations', ['slug' => 'acme-invalid'], 'central');
    }

    public function test_register_returns_validation_error_when_slug_is_reserved(): void
    {
        app(RegistrationVerificationService::class)->register([
            'organization_name' => 'Existing Co',
            'slug' => 'taken-slug',
            'name' => 'Existing Admin',
            'email' => 'existing@test.com',
            'password' => 'password123',
        ]);

        $this->from('http://'.config('tenancy.central_app_domain').'/register')
            ->post('http://'.config('tenancy.central_app_domain').'/register', [
                'organization_name' => 'New Co',
                'slug' => 'taken-slug',
                'name' => 'New Admin',
                'email' => 'new@test.com',
                'password' => 'password123',
                'password_confirmation' => 'password123',
            ], [
                'X-Inertia' => 'true',
                'X-Requested-With' => 'XMLHttpRequest',
            ])
            ->assertSessionHasErrors('slug');
    }
}
