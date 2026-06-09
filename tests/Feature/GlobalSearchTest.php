<?php

namespace Tests\Feature;

use App\Domains\Contacts\Models\Contact;
use App\Domains\Contacts\Models\Organization;
use App\Domains\Tickets\Models\Ticket;
use App\Domains\Tickets\Models\TicketPriority;
use App\Domains\Tickets\Models\TicketStatus;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GlobalSearchTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionSeeder::class);
    }

    public function test_agent_can_search_tickets_contacts_and_organizations(): void
    {
        $status = TicketStatus::query()->create(['name' => 'Open', 'slug' => 'open', 'color' => '#000', 'sort_order' => 1, 'is_closed' => false]);
        $priority = TicketPriority::query()->create(['name' => 'Normal', 'slug' => 'normal', 'sort_order' => 1]);
        $contact = Contact::query()->create(['name' => 'Acme Buyer', 'email' => 'buyer@acme.test']);
        $organization = Organization::query()->create(['name' => 'Acme Corp']);

        $ticket = Ticket::query()->create([
            'number' => 'HD-90001',
            'subject' => 'Acme billing issue',
            'ticket_status_id' => $status->id,
            'ticket_priority_id' => $priority->id,
            'contact_id' => $contact->id,
        ]);

        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->getJson('/global-search?q=Acme')
            ->assertOk()
            ->assertJsonPath('query', 'Acme');

        $groups = collect($response->json('groups'))->keyBy('type');

        $this->assertSame($ticket->id, $groups['tickets']['items'][0]['id']);
        $this->assertSame($contact->id, $groups['contacts']['items'][0]['id']);
        $this->assertSame($organization->id, $groups['organizations']['items'][0]['id']);
    }

    public function test_short_queries_return_empty_groups(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->getJson('/global-search?q=a')
            ->assertOk()
            ->assertJsonPath('groups', []);
    }
}
