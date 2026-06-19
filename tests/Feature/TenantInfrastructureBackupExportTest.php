<?php

namespace Tests\Feature;

use App\Domains\Billing\Models\Subscription;
use App\Domains\Tenancy\Jobs\ExportTenantDatabaseBackupJob;
use App\Domains\Tenancy\Models\TenantInfrastructure;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TenantTestCase;

class TenantInfrastructureBackupExportTest extends TenantTestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['tenant_infrastructure.enabled' => true]);
    }

    private function admin(): User
    {
        return User::query()->where('email', 'admin@helpdesk.test')->firstOrFail();
    }

    private function setSubscription(array $overrides = []): void
    {
        Subscription::query()->updateOrCreate(
            ['tenant_id' => tenant('id')],
            array_merge([
                'plan' => 'enterprise',
                'status' => 'active',
                'renews_at' => now()->addMonth(),
                'active_addons' => ['byo_database', 'byo_storage'],
            ], $overrides),
        );
    }

    public function test_export_backup_queues_job_when_external_database_and_storage_configured(): void
    {
        Queue::fake();

        $this->setSubscription([
            'plan' => 'enterprise',
            'active_addons' => ['byo_database', 'byo_storage'],
        ]);

        TenantInfrastructure::query()->create([
            'tenant_id' => tenant('id'),
            'database_mode' => TenantInfrastructure::MODE_EXTERNAL,
            'database_config' => [
                'host' => 'db.example.com',
                'port' => 3306,
                'database' => 'helpdesk',
                'username' => 'app',
                'password' => 'secret',
            ],
            'storage_mode' => TenantInfrastructure::MODE_EXTERNAL,
            'storage_config' => [
                'driver' => 'r2',
                'bucket' => 'workspace',
                'region' => 'auto',
                'endpoint' => 'https://example.r2.cloudflarestorage.com',
                'access_key_id' => 'key',
                'secret_access_key' => 'secret',
                'prefix' => 'test',
            ],
            'status' => TenantInfrastructure::STATUS_VERIFIED,
        ]);

        $this->actingAs($this->admin())
            ->tenantPost('/settings/infrastructure/export-backup')
            ->assertRedirect()
            ->assertSessionHas('success');

        $record = TenantInfrastructure::query()->where('tenant_id', tenant('id'))->first();

        $this->assertSame(TenantInfrastructure::MIGRATION_QUEUED, $record->backup_export_status);

        Queue::assertPushed(ExportTenantDatabaseBackupJob::class, function (ExportTenantDatabaseBackupJob $job) {
            return $job->tenantId === tenant('id');
        });
    }

    public function test_export_backup_rejects_duplicate_queue(): void
    {
        Queue::fake();

        $this->setSubscription([
            'plan' => 'enterprise',
            'active_addons' => ['byo_database', 'byo_storage'],
        ]);

        TenantInfrastructure::query()->create([
            'tenant_id' => tenant('id'),
            'database_mode' => TenantInfrastructure::MODE_EXTERNAL,
            'database_config' => ['host' => 'db.example.com', 'database' => 'helpdesk', 'username' => 'app', 'password' => 'secret'],
            'storage_mode' => TenantInfrastructure::MODE_EXTERNAL,
            'storage_config' => ['driver' => 's3', 'bucket' => 'bucket', 'access_key_id' => 'key', 'secret_access_key' => 'secret'],
            'backup_export_status' => TenantInfrastructure::MIGRATION_RUNNING,
            'status' => TenantInfrastructure::STATUS_VERIFIED,
        ]);

        $this->actingAs($this->admin())
            ->tenantPost('/settings/infrastructure/export-backup')
            ->assertRedirect()
            ->assertSessionHasErrors('infrastructure');

        Queue::assertNothingPushed();
    }
}
