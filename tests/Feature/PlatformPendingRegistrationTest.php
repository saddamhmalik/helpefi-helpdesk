<?php

namespace Tests\Feature;

use App\Domains\Tenancy\Models\PendingRegistration;
use App\Domains\Tenancy\Services\RegistrationVerificationService;
use Database\Seeders\PlatformPermissionSeeder;
use Database\Seeders\PlatformUserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class PlatformPendingRegistrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([PlatformPermissionSeeder::class, PlatformUserSeeder::class]);
    }

    private function adminLogin(): void
    {
        $this->post('http://'.config('tenancy.central_app_domain').'/admin/login', [
            'email' => PlatformUserSeeder::DEFAULT_EMAIL,
            'password' => PlatformUserSeeder::DEFAULT_PASSWORD,
        ]);
    }

    private function createPending(string $slug, string $email, ?\DateTimeInterface $expiresAt = null): PendingRegistration
    {
        app(RegistrationVerificationService::class)->register([
            'organization_name' => 'Pending Co',
            'slug' => $slug,
            'name' => 'Pending Admin',
            'email' => $email,
            'password' => 'password123',
        ]);

        $pending = PendingRegistration::query()->where('slug', $slug)->firstOrFail();

        if ($expiresAt) {
            $pending->expires_at = $expiresAt;
            $pending->save();
        }

        return $pending;
    }

    public function test_platform_admin_can_view_pending_registrations(): void
    {
        $this->createPending('pending-view', 'pending-view@test.com');

        $this->adminLogin();

        $this->get('http://'.config('tenancy.central_app_domain').'/admin/pending-registrations')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Central/Admin/PendingRegistrations/Index')
                ->has('registrations.data', 1)
                ->where('stats.total', 1)
                ->where('stats.active', 1));
    }

    public function test_platform_admin_can_remove_pending_registration(): void
    {
        $pending = $this->createPending('pending-remove', 'remove@test.com');

        $this->adminLogin();

        $this->delete('http://'.config('tenancy.central_app_domain').'/admin/pending-registrations/'.$pending->id)
            ->assertRedirect();

        $this->assertDatabaseMissing('pending_registrations', ['id' => $pending->id], 'central');

        $this->post('http://'.config('tenancy.central_app_domain').'/register', [
            'organization_name' => 'Pending Co',
            'slug' => 'pending-remove',
            'name' => 'New Admin',
            'email' => 'new@test.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertSessionHasNoErrors();
    }

    public function test_platform_admin_can_purge_expired_pending_registrations(): void
    {
        $this->createPending('pending-expired', 'expired@test.com', now()->subHour());
        $this->createPending('pending-active', 'active@test.com', now()->addDay());

        $this->adminLogin();

        $this->post('http://'.config('tenancy.central_app_domain').'/admin/pending-registrations/purge-expired')
            ->assertRedirect();

        $this->assertDatabaseMissing('pending_registrations', ['slug' => 'pending-expired'], 'central');
        $this->assertDatabaseHas('pending_registrations', ['slug' => 'pending-active'], 'central');
    }

    public function test_scheduled_command_purges_expired_pending_registrations(): void
    {
        $this->createPending('cmd-expired', 'cmd-expired@test.com', now()->subHour());
        $this->createPending('cmd-active', 'cmd-active@test.com', now()->addDay());

        Artisan::call('registrations:purge-expired');

        $this->assertDatabaseMissing('pending_registrations', ['slug' => 'cmd-expired'], 'central');
        $this->assertDatabaseHas('pending_registrations', ['slug' => 'cmd-active'], 'central');
    }
}
