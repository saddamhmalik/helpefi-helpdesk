<?php

namespace Tests\Feature;

use App\Domains\Assets\Models\Asset;
use App\Domains\Assets\Models\AssetType;
use App\Models\User;
use Database\Seeders\TicketLookupSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TenantTestCase;

class AssetTypeTest extends TenantTestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(TicketLookupSeeder::class);
    }

    public function test_agent_can_view_asset_types_page(): void
    {
        $agent = User::factory()->create();

        $this->actingAs($agent)
            ->tenantGet('/assets/types')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Assets/Types')->has('types'));
    }

    public function test_agent_can_create_asset_type(): void
    {
        $agent = User::factory()->create();

        $this->actingAs($agent)
            ->tenantPost('/assets/types', ['name' => 'Printer'])
            ->assertRedirect();

        $this->assertDatabaseHas('asset_types', [
            'name' => 'Printer',
            'slug' => 'printer',
        ]);
    }

    public function test_asset_type_with_assets_cannot_be_deleted(): void
    {
        $type = AssetType::query()->firstOrCreate(['slug' => 'laptop'], ['name' => 'Laptop']);
        Asset::query()->create([
            'asset_type_id' => $type->id,
            'asset_tag' => 'AST-88001',
            'name' => 'Assigned laptop',
            'status' => Asset::STATUS_IN_USE,
        ]);

        $agent = User::factory()->create();

        $this->actingAs($agent)
            ->tenantDelete("/assets/types/{$type->id}")
            ->assertSessionHasErrors('name');

        $this->assertDatabaseHas('asset_types', ['id' => $type->id]);
    }
}
