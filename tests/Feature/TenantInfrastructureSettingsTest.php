<?php

namespace Tests\Feature;

use App\Domains\Billing\Models\Subscription;
use App\Domains\Tenancy\Jobs\VerifyTenantInfrastructureJob;
use App\Domains\Tenancy\Models\TenantInfrastructure;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TenantTestCase;

class TenantInfrastructureSettingsTest extends TenantTestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['tenant_infrastructure.enabled' => true]);
        Queue::fake();
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
                'plan' => 'professional',
                'status' => Subscription::STATUS_ACTIVE,
                'renews_at' => now()->addMonth(),
                'active_addons' => ['byo_database', 'byo_storage'],
            ], $overrides),
        );
    }

    public function test_admin_can_view_infrastructure_settings_with_addon(): void
    {
        $this->setSubscription();

        $this->actingAs($this->admin())
            ->tenantGet('/settings/infrastructure')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Settings/Infrastructure')
                ->has('infrastructure')
                ->where('infrastructure.database_eligible', true)
                ->where('infrastructure.storage_eligible', true));
    }

    public function test_admin_without_addon_sees_ineligible_sections(): void
    {
        $this->setSubscription(['active_addons' => []]);

        $this->actingAs($this->admin())
            ->tenantGet('/settings/infrastructure')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('infrastructure.database_eligible', false)
                ->where('infrastructure.storage_eligible', false));
    }

    public function test_storage_addon_only_hides_database_configuration_for_tenant(): void
    {
        $this->setSubscription([
            'plan' => 'enterprise',
            'active_addons' => ['byo_storage'],
        ]);

        Tenant::query()->where('id', tenant('id'))->update(['byo_allowed' => true]);

        $this->actingAs($this->admin())
            ->tenantGet('/settings/infrastructure')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('infrastructure.database_eligible', false)
                ->where('infrastructure.storage_eligible', true));
    }

    public function test_storage_addon_only_rejects_external_database_save(): void
    {
        $this->setSubscription([
            'plan' => 'enterprise',
            'active_addons' => ['byo_storage'],
        ]);

        Tenant::query()->where('id', tenant('id'))->update(['byo_allowed' => true]);

        $this->actingAs($this->admin())
            ->tenantPut('/settings/infrastructure', [
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
            ])
            ->assertRedirect();

        $this->assertNull(
            TenantInfrastructure::query()->where('tenant_id', tenant('id'))->value('database_config'),
        );
    }

    public function test_admin_can_save_external_database_configuration(): void
    {
        $this->setSubscription(['active_addons' => ['byo_database']]);

        $this->actingAs($this->admin())
            ->tenantPut('/settings/infrastructure', [
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
            ])
            ->assertRedirect();

        $record = TenantInfrastructure::query()->where('tenant_id', tenant('id'))->first();

        $this->assertNotNull($record);
        $this->assertSame(TenantInfrastructure::MIGRATION_QUEUED, $record->database_migration_status);
        $this->assertSame('db.example.com', $record->database_config['host']);
    }

    public function test_test_database_endpoint_requires_database_addon(): void
    {
        $this->setSubscription(['active_addons' => ['byo_storage']]);

        $this->actingAs($this->admin())
            ->tenantPostJson('/settings/infrastructure/test-database', [
                'database_config' => [
                    'host' => 'db.example.com',
                    'port' => 3306,
                    'database' => 'helpdesk',
                    'username' => 'app',
                    'password' => 'secret',
                ],
            ])
            ->assertStatus(422);
    }

    public function test_test_database_endpoint_returns_ok_when_connection_succeeds(): void
    {
        $this->setSubscription(['active_addons' => ['byo_database']]);

        $this->mock(\App\Domains\Tenancy\Services\ExternalTenantDatabaseService::class, function ($mock) {
            $mock->shouldReceive('testConnection')->andReturn(null);
            $mock->shouldReceive('testReadOnlyConnection')->andReturn(null);
        });

        $this->actingAs($this->admin())
            ->tenantPostJson('/settings/infrastructure/test-database', [
                'database_config' => [
                    'host' => 'db.example.com',
                    'port' => 3306,
                    'database' => 'helpdesk',
                    'username' => 'app',
                    'password' => 'secret',
                ],
            ])
            ->assertOk()
            ->assertJson(['ok' => true]);
    }

    public function test_storage_addon_clears_orphaned_database_migration_on_view(): void
    {
        $this->setSubscription([
            'plan' => 'enterprise',
            'active_addons' => ['byo_storage'],
        ]);

        TenantInfrastructure::query()->create([
            'tenant_id' => tenant('id'),
            'database_mode' => TenantInfrastructure::MODE_MANAGED,
            'database_config' => [
                'host' => 'db.example.com',
                'port' => 3306,
                'database' => 'helpdesk',
                'username' => 'app',
                'password' => 'secret',
            ],
            'database_migration_status' => TenantInfrastructure::MIGRATION_RUNNING,
            'storage_mode' => TenantInfrastructure::MODE_MANAGED,
            'status' => TenantInfrastructure::STATUS_PENDING,
            'status_message' => 'Database migration in progress.',
        ]);

        $this->actingAs($this->admin())
            ->tenantGet('/settings/infrastructure')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('infrastructure.database_migration_status', null)
                ->where('infrastructure.status_message', null));

        $record = TenantInfrastructure::query()->where('tenant_id', tenant('id'))->firstOrFail();

        $this->assertNull($record->database_migration_status);
        $this->assertNull($record->database_config);
        $this->assertNull($record->status_message);
    }

    public function test_verify_dispatches_job_from_workspace_settings(): void
    {
        $this->setSubscription();

        TenantInfrastructure::query()->create([
            'tenant_id' => tenant('id'),
            'database_mode' => TenantInfrastructure::MODE_MANAGED,
            'storage_mode' => TenantInfrastructure::MODE_MANAGED,
            'status' => TenantInfrastructure::STATUS_PENDING,
        ]);

        $this->actingAs($this->admin())
            ->tenantPost('/settings/infrastructure/verify')
            ->assertRedirect();

        Queue::assertPushed(VerifyTenantInfrastructureJob::class, fn (VerifyTenantInfrastructureJob $job) => $job->tenantId === tenant('id'));
    }
}
