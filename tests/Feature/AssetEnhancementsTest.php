<?php

namespace Tests\Feature;

use App\Domains\Assets\Models\Asset;
use App\Domains\Assets\Models\AssetType;
use App\Domains\Assets\Support\NetworkDiscoveryScanner;
use App\Domains\Contacts\Models\Contact;
use App\Models\User;
use Database\Seeders\TicketLookupSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TenantTestCase;

class AssetEnhancementsTest extends TenantTestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(TicketLookupSeeder::class);
    }

    public function test_assets_index_includes_stats(): void
    {
        $agent = User::factory()->create();

        $this->actingAs($agent)
            ->tenantGet('/assets')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Assets/Index')
                ->has('stats.total')
                ->has('stats.warranty_expiring'));
    }

    public function test_asset_export_returns_csv(): void
    {
        $type = AssetType::query()->firstOrCreate(['slug' => 'laptop'], ['name' => 'Laptop']);
        Asset::query()->create([
            'asset_type_id' => $type->id,
            'asset_tag' => 'AST-00010',
            'name' => 'Export laptop',
            'status' => Asset::STATUS_IN_USE,
        ]);

        $agent = User::factory()->create();

        $this->actingAs($agent)
            ->tenantGet('/assets/export')
            ->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8');
    }

    public function test_asset_import_creates_rows_from_csv(): void
    {
        AssetType::query()->firstOrCreate(['slug' => 'laptop'], ['name' => 'Laptop']);

        $csv = "Name,Type,Serial number\nImported Laptop,Laptop,SN-IMPORT-1\n";
        $file = UploadedFile::fake()->createWithContent('assets.csv', $csv);
        $agent = User::factory()->create();

        $this->actingAs($agent)
            ->tenantPost('/assets/import', ['file' => $file])
            ->assertRedirect('/assets');

        $this->assertDatabaseHas('assets', [
            'name' => 'Imported Laptop',
            'serial_number' => 'SN-IMPORT-1',
        ]);
    }

    public function test_assignment_log_is_recorded_on_update(): void
    {
        $type = AssetType::query()->firstOrCreate(['slug' => 'laptop'], ['name' => 'Laptop']);
        $contact = Contact::query()->create([
            'name' => 'Asset Contact',
            'email' => 'asset-contact@example.com',
        ]);
        $asset = Asset::query()->create([
            'asset_type_id' => $type->id,
            'asset_tag' => 'AST-00020',
            'name' => 'Assignable laptop',
            'status' => Asset::STATUS_IN_STOCK,
        ]);

        $agent = User::factory()->create();

        $this->actingAs($agent)
            ->tenantPut("/assets/{$asset->id}", [
                'asset_type_id' => $type->id,
                'name' => 'Assignable laptop',
                'status' => Asset::STATUS_IN_USE,
                'contact_id' => $contact->id,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('asset_assignment_logs', [
            'asset_id' => $asset->id,
            'contact_id' => $contact->id,
            'action' => 'assigned',
        ]);
    }

    public function test_discovery_scan_can_be_started(): void
    {
        $agent = User::factory()->create();

        $this->actingAs($agent)
            ->tenantPost('/assets/discovery/scans', ['subnet' => '127.0.0.1'])
            ->assertRedirect();

        $this->assertDatabaseHas('asset_discovery_scans', [
            'subnet' => '127.0.0.1',
        ]);
    }

    public function test_network_scanner_expands_single_ip(): void
    {
        $scanner = app(NetworkDiscoveryScanner::class);

        $this->assertSame(['192.168.0.10'], $scanner->expandSubnet('192.168.0.10'));
    }

    public function test_network_scanner_rejects_non_private_subnet(): void
    {
        $scanner = app(NetworkDiscoveryScanner::class);

        $this->expectException(\InvalidArgumentException::class);
        $scanner->expandSubnet('92.168.31.0/24');
    }

    public function test_device_name_resolver_falls_back_to_vendor(): void
    {
        $resolver = app(\App\Domains\Assets\Support\DeviceNameResolver::class);

        $result = $resolver->resolve('192.168.31.1', '8c:83:94:86:da:62');

        $this->assertSame('Xiaomi', $result['vendor']);
        $this->assertSame('Xiaomi (192.168.31.1)', $result['display_name']);
    }
}
