<?php

namespace Tests\Feature;

use App\Models\PlatformUser;
use Database\Seeders\PlatformPermissionSeeder;
use Database\Seeders\PlatformUserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlatformObservabilityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([PlatformPermissionSeeder::class, PlatformUserSeeder::class]);
    }

    public function test_platform_observability_access_is_permission_gated(): void
    {
        $this->post('http://'.config('tenancy.central_app_domain').'/admin/login', [
            'email' => PlatformUserSeeder::DEFAULT_EMAIL,
            'password' => PlatformUserSeeder::DEFAULT_PASSWORD,
        ]);

        $this->get('http://'.config('tenancy.central_app_domain').'/admin/observability')
            ->assertRedirect('http://'.config('tenancy.central_app_domain').'/telescope');

        $this->get('http://'.config('tenancy.central_app_domain').'/telescope')
            ->assertOk();

        $this->post('http://'.config('tenancy.central_app_domain').'/admin/logout');

        $support = PlatformUser::query()->create([
            'name' => 'Support User',
            'email' => 'support@helpdesk.test',
            'password' => 'password123',
            'is_active' => true,
        ]);
        $support->syncRoles(['support']);

        $this->post('http://'.config('tenancy.central_app_domain').'/admin/login', [
            'email' => 'support@helpdesk.test',
            'password' => 'password123',
        ]);

        $this->get('http://'.config('tenancy.central_app_domain').'/admin/observability')->assertForbidden();
    }
}
