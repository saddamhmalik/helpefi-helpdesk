<?php

namespace Tests\Feature;

use App\Domains\Contacts\Models\Contact;
use App\Domains\Tickets\Models\Ticket;
use App\Models\User;
use Database\Seeders\TicketLookupSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PortalCustomerTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_can_register_and_view_my_tickets(): void
    {
        $this->seed(TicketLookupSeeder::class);

        $this->post('/portal/register', [
            'name' => 'Portal User',
            'email' => 'portal@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertRedirect(route('portal.my-tickets'));

        $this->assertAuthenticated();
        $this->assertDatabaseHas('users', ['email' => 'portal@example.com']);

        $user = User::query()->where('email', 'portal@example.com')->first();
        $this->assertTrue($user->hasRole('customer'));

        $this->actingAs($user)
            ->get('/portal/my-tickets')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Portal/MyTickets'));
    }

    public function test_customer_login_lists_their_tickets(): void
    {
        $this->seed(TicketLookupSeeder::class);

        $contact = Contact::query()->create([
            'name' => 'Jane',
            'email' => 'jane@example.com',
        ]);

        $user = User::factory()->customer()->create([
            'email' => 'jane@example.com',
            'contact_id' => $contact->id,
        ]);

        $status = \App\Domains\Tickets\Models\TicketStatus::query()->first();
        $priority = \App\Domains\Tickets\Models\TicketPriority::query()->first();

        Ticket::query()->create([
            'number' => 'HD-00099',
            'subject' => 'My issue',
            'contact_id' => $contact->id,
            'ticket_status_id' => $status->id,
            'ticket_priority_id' => $priority->id,
        ]);

        $this->post('/portal/login', [
            'email' => 'jane@example.com',
            'password' => 'password',
        ])->assertRedirect(route('portal.my-tickets'));

        $this->actingAs($user)
            ->get('/portal/my-tickets')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Portal/MyTickets')
                ->has('tickets.data', 1));
    }

    public function test_agent_cannot_use_portal_login(): void
    {
        User::factory()->create(['email' => 'agent@example.com']);

        $this->post('/portal/login', [
            'email' => 'agent@example.com',
            'password' => 'password',
        ])->assertSessionHasErrors('email');
    }
}
