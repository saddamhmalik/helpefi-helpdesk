<?php

namespace Tests\Feature;

use App\Domains\Platform\Jobs\CreatePlatformBackupJob;
use App\Domains\Platform\Models\PlatformBackup;
use App\Domains\Platform\Repositories\PlatformBackupRepository;
use App\Domains\Platform\Support\DatabaseBackupExporter;
use App\Domains\Platform\Support\PlatformAuditRecorder;
use App\Domains\Tenancy\Services\TenantProvisioningService;
use App\Models\PlatformUser;
use Database\Seeders\PlatformPermissionSeeder;
use Database\Seeders\PlatformUserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class PlatformBackupTest extends TestCase
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

    public function test_platform_admin_can_view_backups_page(): void
    {
        $this->adminLogin();

        $this->get($this->centralUrl('/admin/backups'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Central/Admin/Backups/Index')
                ->has('schedule')
                ->has('schedule_options'));
    }

    public function test_platform_admin_can_update_backup_schedule(): void
    {
        $this->adminLogin();

        $this->put($this->centralUrl('/admin/backups/schedule'), [
            'enabled' => true,
            'frequency' => 'weekly',
            'weekday' => 3,
            'time' => '03:30',
        ])->assertRedirect();

        $this->assertDatabaseHas('central_settings', [
            'backup_schedule_enabled' => true,
            'backup_schedule_frequency' => 'weekly',
            'backup_schedule_weekday' => 3,
            'backup_schedule_time' => '03:30',
        ], 'central');
    }

    public function test_backup_schedule_service_detects_due_window(): void
    {
        $service = app(\App\Domains\Platform\Services\PlatformBackupScheduleService::class);

        $service->update([
            'enabled' => true,
            'frequency' => 'daily',
            'weekday' => 1,
            'time' => now()->format('H:i'),
        ]);

        $this->assertTrue($service->isDue());

        $service->update([
            'enabled' => false,
            'frequency' => 'daily',
            'weekday' => 1,
            'time' => now()->format('H:i'),
        ]);

        $this->assertFalse($service->isDue());
    }

    public function test_platform_admin_can_create_central_backup(): void
    {
        Queue::fake();

        $this->adminLogin();

        $this->post($this->centralUrl('/admin/backups'), [
            'scope' => 'central',
        ])->assertRedirect();

        $this->assertDatabaseHas('platform_backups', [
            'scope' => PlatformBackup::SCOPE_CENTRAL,
            'status' => PlatformBackup::STATUS_PENDING,
        ], 'central');
    }

    public function test_central_backup_job_completes_for_sqlite(): void
    {
        $this->adminLogin();

        $this->post($this->centralUrl('/admin/backups'), [
            'scope' => 'central',
        ])->assertRedirect();

        $backup = PlatformBackup::query()->where('scope', PlatformBackup::SCOPE_CENTRAL)->first();

        $this->assertNotNull($backup);
        $this->assertSame(PlatformBackup::STATUS_COMPLETED, $backup->status);
        $this->assertNotNull($backup->path);
        $this->assertGreaterThanOrEqual(0, $backup->size_bytes);
    }

    public function test_backup_job_completes_after_tenant_queue_context(): void
    {
        $tenant = app(TenantProvisioningService::class)->provision(
            organizationName: 'Queue Co',
            slug: 'queue-co',
            adminName: 'Queue Admin',
            adminEmail: 'queue@test.com',
            adminPassword: 'password123',
        );

        tenancy()->initialize($tenant);

        $actor = PlatformUser::query()->where('email', PlatformUserSeeder::DEFAULT_EMAIL)->firstOrFail();
        $backup = PlatformBackup::query()->create([
            'scope' => PlatformBackup::SCOPE_TENANT,
            'tenant_id' => $tenant->id,
            'status' => PlatformBackup::STATUS_PENDING,
            'storage_disk' => config('backup.disk', 'local'),
            'created_by' => $actor->id,
        ]);

        $job = new CreatePlatformBackupJob($backup->id);
        $job->handle(
            app(PlatformBackupRepository::class),
            app(DatabaseBackupExporter::class),
            app(PlatformAuditRecorder::class),
        );

        $backup->refresh();

        $this->assertSame(PlatformBackup::STATUS_COMPLETED, $backup->status);
        $this->assertNotNull($backup->path);

        tenancy()->end();
    }

    public function test_platform_admin_can_queue_workspace_backup(): void
    {
        $tenant = app(TenantProvisioningService::class)->provision(
            organizationName: 'Backup Co',
            slug: 'backup-co',
            adminName: 'Backup Admin',
            adminEmail: 'backup@test.com',
            adminPassword: 'password123',
        );

        Queue::fake();

        $this->adminLogin();

        $this->post($this->centralUrl('/admin/backups'), [
            'scope' => 'tenant',
            'tenant_id' => $tenant->id,
        ])->assertRedirect();

        $this->assertDatabaseHas('platform_backups', [
            'scope' => PlatformBackup::SCOPE_TENANT,
            'tenant_id' => $tenant->id,
            'status' => PlatformBackup::STATUS_PENDING,
        ], 'central');
    }
}
