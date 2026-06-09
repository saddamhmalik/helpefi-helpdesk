<?php

namespace Tests\Feature;

use App\Domains\Assets\Models\Asset;
use App\Domains\Assets\Models\AssetType;
use App\Domains\Tickets\Models\Ticket;
use App\Domains\Tickets\Models\TicketPriority;
use App\Domains\Tickets\Models\TicketStatus;
use App\Models\User;
use Database\Seeders\AssetSeeder;
use Database\Seeders\TicketLookupSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AssetTest extends TestCase
{
    use RefreshDatabase;

    public function test_agent_can_view_assets_index(): void
    {
        $agent = User::factory()->create();

        $this->actingAs($agent)
            ->get('/assets')
            ->assertOk();
    }

    public function test_agent_can_create_asset(): void
    {
        $this->seed(TicketLookupSeeder::class);

        $type = AssetType::query()->create(['name' => 'Laptop', 'slug' => 'laptop']);
        $agent = User::factory()->create();

        $this->actingAs($agent)
            ->post('/assets', [
                'asset_type_id' => $type->id,
                'name' => 'Test Laptop',
                'status' => Asset::STATUS_IN_STOCK,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('assets', [
            'name' => 'Test Laptop',
            'status' => Asset::STATUS_IN_STOCK,
        ]);
    }

    public function test_asset_can_be_linked_to_ticket(): void
    {
        $this->seed(TicketLookupSeeder::class);

        $type = AssetType::query()->create(['name' => 'Laptop', 'slug' => 'laptop']);
        $asset = Asset::query()->create([
            'asset_type_id' => $type->id,
            'asset_tag' => 'AST-00001',
            'name' => 'Linked laptop',
            'status' => Asset::STATUS_IN_USE,
        ]);

        $status = TicketStatus::query()->where('slug', 'open')->first();
        $priority = TicketPriority::query()->where('slug', 'normal')->first();
        $ticket = Ticket::query()->create([
            'number' => 'HD-00200',
            'subject' => 'Broken screen',
            'ticket_status_id' => $status->id,
            'ticket_priority_id' => $priority->id,
        ]);

        $agent = User::factory()->create();

        $this->actingAs($agent)
            ->post("/tickets/{$ticket->id}/assets", ['asset_id' => $asset->id])
            ->assertRedirect();

        $this->assertDatabaseHas('asset_ticket', [
            'asset_id' => $asset->id,
            'ticket_id' => $ticket->id,
        ]);
    }

    public function test_api_lists_assets(): void
    {
        $this->seed([TicketLookupSeeder::class, AssetSeeder::class]);

        $admin = User::factory()->admin()->create();
        $login = $this->postJson('/api/v1/auth/login', [
            'email' => $admin->email,
            'password' => 'password',
        ]);

        $this->withToken($login->json('token'))
            ->getJson('/api/v1/assets')
            ->assertOk()
            ->assertJsonFragment(['asset_tag' => 'AST-00001']);
    }
}
