<?php

namespace Tests\Feature;

use App\Domains\Contacts\Models\Contact;
use App\Domains\Csat\Models\CsatSetting;
use App\Domains\Tickets\Models\Ticket;
use App\Domains\Tickets\Models\TicketPriority;
use App\Domains\Tickets\Models\TicketStatus;
use App\Models\User;
use Database\Seeders\CsatSeeder;
use Database\Seeders\TicketLookupSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CsatTest extends TestCase
{
    use RefreshDatabase;

    private function closedTicketFor(Contact $contact): Ticket
    {
        $this->seed(TicketLookupSeeder::class);

        $closed = TicketStatus::query()->where('slug', 'closed')->first();
        $priority = TicketPriority::query()->where('slug', 'normal')->first();

        return Ticket::query()->create([
            'number' => 'HD-CSAT01',
            'subject' => 'Resolved issue',
            'contact_id' => $contact->id,
            'ticket_status_id' => $closed->id,
            'ticket_priority_id' => $priority->id,
        ]);
    }

    public function test_customer_can_submit_csat_on_closed_ticket(): void
    {
        $this->seed(CsatSeeder::class);

        $contact = Contact::query()->create(['name' => 'Jane', 'email' => 'jane@example.com']);
        $user = User::factory()->customer()->create([
            'email' => 'jane@example.com',
            'contact_id' => $contact->id,
        ]);
        $ticket = $this->closedTicketFor($contact);

        $this->actingAs($user)
            ->post("/portal/my-tickets/{$ticket->id}/csat", [
                'rating' => 5,
                'comment' => 'Great support',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseHas('csat_responses', [
            'ticket_id' => $ticket->id,
            'contact_id' => $contact->id,
            'rating' => 5,
            'comment' => 'Great support',
        ]);
    }

    public function test_csat_rejected_on_open_ticket(): void
    {
        $this->seed([TicketLookupSeeder::class, CsatSeeder::class]);

        $contact = Contact::query()->create(['name' => 'Jane', 'email' => 'jane@example.com']);
        $user = User::factory()->customer()->create([
            'email' => 'jane@example.com',
            'contact_id' => $contact->id,
        ]);

        $open = TicketStatus::query()->where('slug', 'open')->first();
        $priority = TicketPriority::query()->where('slug', 'normal')->first();

        $ticket = Ticket::query()->create([
            'number' => 'HD-CSAT02',
            'subject' => 'Open issue',
            'contact_id' => $contact->id,
            'ticket_status_id' => $open->id,
            'ticket_priority_id' => $priority->id,
        ]);

        $this->actingAs($user)
            ->post("/portal/my-tickets/{$ticket->id}/csat", ['rating' => 4])
            ->assertSessionHasErrors('rating');
    }

    public function test_guest_can_submit_csat_via_track(): void
    {
        $this->seed(CsatSeeder::class);

        $contact = Contact::query()->create(['name' => 'Guest', 'email' => 'guest@example.com']);
        $ticket = $this->closedTicketFor($contact);

        $this->post('/portal/csat', [
            'number' => $ticket->number,
            'email' => 'guest@example.com',
            'rating' => 3,
            'comment' => 'Okay',
        ])
            ->assertRedirect(route('portal.track', [
                'number' => $ticket->number,
                'email' => 'guest@example.com',
            ]))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('csat_responses', [
            'ticket_id' => $ticket->id,
            'rating' => 3,
        ]);
    }

    public function test_admin_can_update_csat_settings(): void
    {
        $this->seed(CsatSeeder::class);
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->put('/settings/csat', [
                'enabled' => false,
                'comment_required' => true,
                'email_enabled' => false,
            ])
            ->assertRedirect();

        $this->assertFalse(CsatSetting::query()->first()->enabled);
        $this->assertTrue(CsatSetting::query()->first()->comment_required);
    }

    public function test_agent_can_run_csat_report(): void
    {
        $this->seed(CsatSeeder::class);

        $contact = Contact::query()->create(['name' => 'Jane', 'email' => 'jane@example.com']);
        $ticket = $this->closedTicketFor($contact);

        app(\App\Domains\Csat\Services\CsatService::class)->submit($ticket, $contact, 5, 'Excellent');

        $agent = User::factory()->create();

        $this->actingAs($agent)
            ->get('/reports?type=csat&run=1')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Reports/Index')
                ->where('result.format', 'csat')
                ->where('result.summary.total_responses', 1));
    }

    public function test_duplicate_csat_submission_is_rejected(): void
    {
        $this->seed(CsatSeeder::class);

        $contact = Contact::query()->create(['name' => 'Jane', 'email' => 'jane@example.com']);
        $user = User::factory()->customer()->create([
            'email' => 'jane@example.com',
            'contact_id' => $contact->id,
        ]);
        $ticket = $this->closedTicketFor($contact);

        app(\App\Domains\Csat\Services\CsatService::class)->submit($ticket, $contact, 4, null);

        $this->actingAs($user)
            ->post("/portal/my-tickets/{$ticket->id}/csat", ['rating' => 5])
            ->assertSessionHasErrors('rating');
    }
}
