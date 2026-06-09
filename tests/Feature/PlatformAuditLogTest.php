<?php

namespace Tests\Feature;

use App\Domains\Platform\Models\PlatformAuditLog;
use Database\Seeders\PlatformPermissionSeeder;
use Database\Seeders\PlatformUserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlatformAuditLogTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([PlatformPermissionSeeder::class, PlatformUserSeeder::class]);
    }

    private function centralUrl(string $path): string
    {
        return 'http://'.config('tenancy.central_app_domain').$path;
    }

    private function adminLogin(): void
    {
        $this->post($this->centralUrl('/admin/login'), [
            'email' => PlatformUserSeeder::DEFAULT_EMAIL,
            'password' => PlatformUserSeeder::DEFAULT_PASSWORD,
        ]);
    }

    public function test_platform_admin_can_view_audit_logs_page(): void
    {
        $this->adminLogin();

        $this->get($this->centralUrl('/admin/audit-logs'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Central/Admin/AuditLogs/Index'));
    }

    public function test_platform_login_creates_audit_log(): void
    {
        $this->adminLogin();

        $this->assertDatabaseHas('platform_audit_logs', [
            'event' => 'platform.auth.login',
            'actor_email' => PlatformUserSeeder::DEFAULT_EMAIL,
        ], 'central');
    }

    public function test_platform_admin_can_export_audit_logs_csv(): void
    {
        PlatformAuditLog::query()->create([
            'event' => 'platform.settings.updated',
            'actor_email' => PlatformUserSeeder::DEFAULT_EMAIL,
            'created_at' => now(),
        ]);

        $this->adminLogin();

        $response = $this->get($this->centralUrl('/admin/audit-logs/export'));

        $response->assertOk();
        $response->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $this->assertStringContainsString('platform.settings.updated', $response->streamedContent());
    }
}
