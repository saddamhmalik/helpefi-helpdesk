<?php

namespace Tests\Feature;

use App\Domains\Billing\Models\Subscription;
use App\Domains\Tenancy\Models\TenantInfrastructure;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TenantTestCase;

class TenantInfrastructureAutoBackupTest extends TenantTestCase
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

    private function setSubscription(): void
    {
        Subscription::query()->updateOrCreate(
            ['tenant_id' => tenant('id')],
            [
                'plan' => 'enterprise',
                'status' => 'active',
                'renews_at' => now()->addMonth(),
                'active_addons' => ['byo_database', 'byo_storage'],
            ],
        );
    }

    private function externalInfrastructure(): TenantInfrastructure
    {
        return TenantInfrastructure::query()->create([
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
    }

    public function test_admin_can_update_auto_backup_schedule(): void
    {
        $this->setSubscription();
        $this->externalInfrastructure();

        $this->actingAs($this->admin())
            ->tenantPut('/settings/infrastructure/auto-backup', [
                'enabled' => true,
                'frequency' => 'weekly',
                'weekday' => 1,
                'time' => '03:30',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $record = TenantInfrastructure::query()->where('tenant_id', tenant('id'))->first();

        $this->assertTrue($record->auto_backup_enabled);
        $this->assertSame('weekly', $record->auto_backup_frequency);
        $this->assertSame(1, $record->auto_backup_weekday);
        $this->assertSame('03:30', $record->auto_backup_time);
    }

    public function test_auto_backup_requires_external_database(): void
    {
        $this->setSubscription();

        TenantInfrastructure::query()->create([
            'tenant_id' => tenant('id'),
            'database_mode' => TenantInfrastructure::MODE_MANAGED,
            'storage_mode' => TenantInfrastructure::MODE_EXTERNAL,
            'storage_config' => [
                'driver' => 's3',
                'bucket' => 'bucket',
                'access_key_id' => 'key',
                'secret_access_key' => 'secret',
            ],
            'status' => TenantInfrastructure::STATUS_VERIFIED,
        ]);

        $this->actingAs($this->admin())
            ->tenantPut('/settings/infrastructure/auto-backup', [
                'enabled' => true,
                'frequency' => 'daily',
                'weekday' => 1,
                'time' => '02:00',
            ])
            ->assertRedirect()
            ->assertSessionHasErrors('auto_backup');
    }
}
