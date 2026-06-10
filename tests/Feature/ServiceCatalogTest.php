<?php

namespace Tests\Feature;

use App\Domains\ServiceCatalog\Models\ServiceCatalogItem;
use App\Domains\ServiceCatalog\Models\ServiceCategory;
use App\Domains\Tickets\Models\TicketPriority;
use App\Models\User;
use Database\Seeders\ServiceCatalogSeeder;
use Database\Seeders\TicketLookupSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ServiceCatalogTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_service_catalog_settings(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get('/settings/service-catalog')
            ->assertOk();
    }

    public function test_agent_cannot_view_service_catalog_settings(): void
    {
        $agent = User::factory()->create();

        $this->actingAs($agent)
            ->get('/settings/service-catalog')
            ->assertForbidden();
    }

    public function test_portal_lists_public_services(): void
    {
        $this->seed([TicketLookupSeeder::class, ServiceCatalogSeeder::class]);

        $this->get('/portal/services')
            ->assertOk();
    }

    public function test_portal_shows_public_service_request_page(): void
    {
        $this->seed([TicketLookupSeeder::class, ServiceCatalogSeeder::class]);

        $this->get('/portal/services/password-reset')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Portal/ServiceRequest')
                ->where('service.slug', 'password-reset'));
    }

    public function test_service_request_creates_typed_ticket(): void
    {
        $this->seed(TicketLookupSeeder::class);

        $priorityId = TicketPriority::query()->where('slug', 'normal')->value('id');
        $category = ServiceCategory::query()->create([
            'name' => 'Facilities',
            'slug' => 'facilities',
            'is_active' => true,
        ]);

        $item = ServiceCatalogItem::query()->create([
            'service_category_id' => $category->id,
            'name' => 'Desk move',
            'slug' => 'desk-move',
            'description' => 'Move desks between floors.',
            'ticket_type' => ServiceCatalogItem::TYPE_SERVICE_REQUEST,
            'ticket_priority_id' => $priorityId,
            'fields' => [
                ['name' => 'floor', 'label' => 'Target floor', 'type' => 'text', 'required' => true, 'options' => []],
            ],
            'is_public' => true,
            'is_active' => true,
        ]);

        $this->post('/portal/services/desk-move', [
            'name' => 'Alex Agent',
            'email' => 'alex@example.com',
            'fields' => ['floor' => '3'],
            'details' => 'Need this done Friday.',
        ])->assertRedirect();

        $this->assertDatabaseHas('tickets', [
            'subject' => 'Desk move',
            'type' => ServiceCatalogItem::TYPE_SERVICE_REQUEST,
            'service_catalog_item_id' => $item->id,
        ]);

        $this->assertDatabaseHas('contacts', ['email' => 'alex@example.com']);
    }

    public function test_api_lists_public_service_catalog(): void
    {
        $this->seed([TicketLookupSeeder::class, ServiceCatalogSeeder::class]);

        $this->getJson('/api/v1/portal/services')
            ->assertOk()
            ->assertJsonFragment(['slug' => 'it-support']);
    }
}
