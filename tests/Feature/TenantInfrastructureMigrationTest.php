<?php

namespace Tests\Feature;

use App\Domains\Billing\Models\Subscription;
use App\Domains\Tenancy\Jobs\MigrateManagedToExternalDatabaseJob;
use App\Domains\Tenancy\Jobs\MigrateManagedToExternalStorageJob;
use App\Domains\Tenancy\Models\TenantInfrastructure;
use App\Domains\Tenancy\Services\TenantInfrastructureMetricsService;
use App\Domains\Tenancy\Services\TenantInfrastructureService;
use App\Models\Tenant;
use Database\Seeders\PlatformPermissionSeeder;
use Database\Seeders\PlatformUserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class TenantInfrastructureMigrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([PlatformPermissionSeeder::class, PlatformUserSeeder::class]);
        config(['tenant_infrastructure.enabled' => true]);
    }

    public function test_managed_to_external_database_queues_migration_without_switching_mode(): void
    {
        Queue::fake();

        $tenant = $this->eligibleTenant('migrate-db', 'migrate-db');

        $tenant->run(function () {
            \Illuminate\Support\Facades\Schema::connection(config('database.default'))->create('_byo_migration_probe', function ($table) {
                $table->id();
            });
        });

        app(TenantInfrastructureService::class)->update($tenant, [
            'database_mode' => TenantInfrastructure::MODE_EXTERNAL,
            'storage_mode' => TenantInfrastructure::MODE_MANAGED,
            'confirm_external_database' => true,
            'database_config' => [
                'host' => 'db.example.com',
                'port' => 3306,
                'database' => 'helpdesk',
                'username' => 'app',
                'password' => 'secret',
            ],
        ]);

        $record = TenantInfrastructure::query()->where('tenant_id', $tenant->id)->first();

        $this->assertSame(TenantInfrastructure::MODE_MANAGED, $record->database_mode);
        $this->assertSame(TenantInfrastructure::MIGRATION_QUEUED, $record->database_migration_status);
        Queue::assertPushed(MigrateManagedToExternalDatabaseJob::class);

        $tenant->delete();
    }

    public function test_managed_to_external_storage_queues_migration_without_switching_mode(): void
    {
        Queue::fake();

        $tenant = $this->eligibleTenant('migrate-storage', 'migrate-storage');

        TenantInfrastructure::query()->create([
            'tenant_id' => $tenant->id,
            'database_mode' => TenantInfrastructure::MODE_MANAGED,
            'storage_mode' => TenantInfrastructure::MODE_MANAGED,
            'storage_config' => [
                'driver' => 's3',
                'bucket' => 'bucket',
                'region' => 'us-east-1',
                'access_key_id' => 'key',
                'secret_access_key' => 'secret',
                'prefix' => 'helpefi/migrate-storage',
            ],
            'status' => TenantInfrastructure::STATUS_PENDING,
        ]);

        app(\App\Domains\Tenancy\Services\TenantInfrastructureMigrationService::class)->queueStorageMigration($tenant);

        $record = TenantInfrastructure::query()->where('tenant_id', $tenant->id)->first();

        $this->assertSame(TenantInfrastructure::MODE_MANAGED, $record->storage_mode);
        $this->assertSame(TenantInfrastructure::MIGRATION_QUEUED, $record->storage_migration_status);
        Queue::assertPushed(MigrateManagedToExternalStorageJob::class);

        $tenant->delete();
    }

    public function test_verify_failure_increments_metrics(): void
    {
        Cache::flush();

        $tenant = $this->eligibleTenant('verify-metrics', 'verify-metrics');

        TenantInfrastructure::create([
            'tenant_id' => $tenant->id,
            'database_mode' => TenantInfrastructure::MODE_EXTERNAL,
            'storage_mode' => TenantInfrastructure::MODE_MANAGED,
            'database_config' => [
                'host' => 'invalid.example.com',
                'port' => 3306,
                'database' => 'helpdesk',
                'username' => 'app',
                'password' => 'secret',
            ],
            'status' => TenantInfrastructure::STATUS_PENDING,
        ]);

        try {
            app(TenantInfrastructureService::class)->verify($tenant);
        } catch (\Illuminate\Validation\ValidationException) {
        }

        $this->assertSame(1, app(TenantInfrastructureMetricsService::class)->totals()['verify_failures_total']);

        $tenant->delete();
    }

    public function test_health_failure_sends_alert_mail(): void
    {
        Mail::fake();
        config(['tenant_infrastructure.alert_emails' => 'ops@example.com']);

        $tenant = Tenant::create([
            'id' => 'health-mail',
            'name' => 'Health Mail',
            'slug' => 'health-mail',
        ]);

        $infrastructure = TenantInfrastructure::create([
            'tenant_id' => $tenant->id,
            'database_mode' => TenantInfrastructure::MODE_EXTERNAL,
            'storage_mode' => TenantInfrastructure::MODE_MANAGED,
            'database_config' => [
                'host' => '127.0.0.1',
                'port' => 3306,
                'database' => 'missing',
                'username' => 'bad',
                'password' => 'bad',
            ],
            'status' => TenantInfrastructure::STATUS_VERIFIED,
            'last_verified_at' => now(),
            'health_failure_count' => 2,
        ]);

        $database = $this->createMock(\App\Domains\Tenancy\Services\ExternalTenantDatabaseService::class);
        $database->method('testConnection')->willReturn('Database connection failed.');
        $this->app->instance(\App\Domains\Tenancy\Services\ExternalTenantDatabaseService::class, $database);

        app(\App\Domains\Tenancy\Services\TenantInfrastructureHealthService::class)->check($infrastructure->fresh());

        Mail::assertSent(\App\Domains\Platform\Mail\TenantInfrastructureFailureMail::class);

        $tenant->delete();
    }

    private function eligibleTenant(string $id, string $slug): Tenant
    {
        $tenant = Tenant::create([
            'id' => $id,
            'name' => ucfirst(str_replace('-', ' ', $slug)),
            'slug' => $slug,
            'byo_allowed' => true,
        ]);

        Subscription::query()->updateOrCreate(
            ['tenant_id' => $tenant->id],
            [
                'plan' => 'enterprise',
                'status' => Subscription::STATUS_ACTIVE,
                'billing_interval' => 'month',
                'renews_at' => now()->addMonth(),
                'trial_ends_at' => null,
            ],
        );

        return $tenant->fresh(['subscription']);
    }
}
