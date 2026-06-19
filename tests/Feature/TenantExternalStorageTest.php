<?php

namespace Tests\Feature;

use App\Domains\Billing\Models\Subscription;
use App\Domains\Tenancy\Bootstrappers\TenantExternalStorageBootstrapper;
use App\Domains\Tenancy\Models\TenantInfrastructure;
use App\Domains\Tenancy\Services\ExternalTenantStorageService;
use App\Domains\Tenancy\Services\TenantInfrastructureService;
use App\Domains\Tenancy\Services\TenantStorageResolver;
use App\Domains\Tenancy\Support\TenantStorageDisks;
use App\Models\Tenant;
use Database\Seeders\PlatformPermissionSeeder;
use Database\Seeders\PlatformUserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class TenantExternalStorageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([PlatformPermissionSeeder::class, PlatformUserSeeder::class]);
    }

    public function test_external_storage_bootstrapper_registers_tenant_files_disk(): void
    {
        config(['tenant_infrastructure.enabled' => true]);

        $tenant = Tenant::create([
            'id' => 'storage-bootstrap',
            'name' => 'Storage Bootstrap',
            'slug' => 'storage-bootstrap',
        ]);

        TenantInfrastructure::create([
            'tenant_id' => $tenant->id,
            'database_mode' => TenantInfrastructure::MODE_MANAGED,
            'storage_mode' => TenantInfrastructure::MODE_EXTERNAL,
            'storage_config' => [
                'driver' => 's3',
                'bucket' => 'customer-bucket',
                'region' => 'us-east-1',
                'access_key_id' => 'key',
                'secret_access_key' => 'secret',
                'prefix' => 'helpefi/storage-bootstrap',
            ],
            'status' => TenantInfrastructure::STATUS_VERIFIED,
        ]);

        tenancy()->initialize($tenant);
        app(TenantExternalStorageBootstrapper::class)->bootstrap($tenant);

        $diskConfig = config('filesystems.disks.'.TenantStorageDisks::EXTERNAL);
        $this->assertSame('customer-bucket', $diskConfig['bucket']);
        $this->assertSame('helpefi/storage-bootstrap', $diskConfig['root']);

        tenancy()->end();
        $tenant->delete();
    }

    public function test_storage_resolver_uses_external_disk_when_configured(): void
    {
        config(['tenant_infrastructure.enabled' => true]);

        $tenant = Tenant::create([
            'id' => 'storage-resolver',
            'name' => 'Storage Resolver',
            'slug' => 'storage-resolver',
        ]);

        TenantInfrastructure::create([
            'tenant_id' => $tenant->id,
            'database_mode' => TenantInfrastructure::MODE_MANAGED,
            'storage_mode' => TenantInfrastructure::MODE_EXTERNAL,
            'storage_config' => [
                'driver' => 's3',
                'bucket' => 'customer-bucket',
                'region' => 'us-east-1',
                'access_key_id' => 'key',
                'secret_access_key' => 'secret',
            ],
            'status' => TenantInfrastructure::STATUS_VERIFIED,
        ]);

        tenancy()->initialize($tenant);

        Config::set('filesystems.disks.'.TenantStorageDisks::EXTERNAL, [
            'driver' => 'local',
            'root' => storage_path('framework/testing/disks/tenant_files'),
        ]);

        $resolver = app(TenantStorageResolver::class);
        $this->assertTrue($resolver->usesExternalStorage());
        $this->assertSame(TenantStorageDisks::EXTERNAL, $resolver->diskName());

        $resolver->disk()->put('ticket-attachments/example.txt', 'payload');
        $this->assertTrue($resolver->disk()->exists('ticket-attachments/example.txt'));

        tenancy()->end();
        $tenant->delete();
    }

    public function test_r2_region_aliases_are_normalized(): void
    {
        $service = app(ExternalTenantStorageService::class);

        $this->assertSame('apac', $service->resolveR2Region('Asia-Pacific'));
        $this->assertSame('auto', $service->resolveR2Region(''));
        $this->assertSame('weur', $service->resolveR2Region('Western Europe'));
    }

    public function test_r2_disk_config_requires_path_style_endpoint(): void
    {
        $tenant = Tenant::create([
            'id' => 'storage-r2',
            'name' => 'Storage R2',
            'slug' => 'storage-r2',
        ]);

        $infrastructure = TenantInfrastructure::make([
            'tenant_id' => $tenant->id,
            'storage_mode' => TenantInfrastructure::MODE_EXTERNAL,
            'storage_config' => [
                'driver' => 'r2',
                'bucket' => 'bucket',
                'endpoint' => 'https://example.r2.cloudflarestorage.com',
                'access_key_id' => 'key',
                'secret_access_key' => 'secret',
                'prefix' => 'helpefi/storage-r2',
            ],
        ]);

        $config = app(ExternalTenantStorageService::class)->buildDiskConfig($infrastructure);

        $this->assertTrue($config['use_path_style_endpoint']);
        $this->assertSame('auto', $config['region']);
        $this->assertSame('https://example.r2.cloudflarestorage.com', $config['endpoint']);

        $tenant->delete();
    }

    public function test_legacy_attachment_disk_resolution_defaults_to_public(): void
    {
        Storage::fake(TenantStorageDisks::MANAGED);

        $resolver = app(TenantStorageResolver::class);
        $disk = $resolver->diskFor(null);

        $disk->put('ticket-attachments/legacy.pdf', 'pdf');
        Storage::disk(TenantStorageDisks::MANAGED)->assertExists('ticket-attachments/legacy.pdf');
    }

    public function test_switching_to_external_storage_requires_confirmation(): void
    {
        config(['tenant_infrastructure.enabled' => true]);

        $tenant = $this->eligibleTenant('storage-confirm', 'storage-confirm');

        $this->expectException(\Illuminate\Validation\ValidationException::class);

        app(TenantInfrastructureService::class)->update($tenant, [
            'database_mode' => TenantInfrastructure::MODE_MANAGED,
            'storage_mode' => TenantInfrastructure::MODE_EXTERNAL,
            'storage_config' => [
                'driver' => 's3',
                'bucket' => 'bucket',
                'region' => 'us-east-1',
                'access_key_id' => 'key',
                'secret_access_key' => 'secret',
            ],
        ]);

        $tenant->delete();
    }

    public function test_s3_storage_config_requires_region(): void
    {
        config(['tenant_infrastructure.enabled' => true]);

        $tenant = $this->eligibleTenant('storage-region', 'storage-region');

        $this->expectException(\Illuminate\Validation\ValidationException::class);

        app(TenantInfrastructureService::class)->update($tenant, [
            'database_mode' => TenantInfrastructure::MODE_MANAGED,
            'storage_mode' => TenantInfrastructure::MODE_EXTERNAL,
            'confirm_external_storage' => true,
            'storage_config' => [
                'driver' => 's3',
                'bucket' => 'bucket',
                'access_key_id' => 'key',
                'secret_access_key' => 'secret',
            ],
        ]);

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
