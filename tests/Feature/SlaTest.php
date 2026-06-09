<?php

namespace Tests\Feature;

use App\Domains\Contacts\Models\Contact;
use App\Domains\Tickets\Models\Ticket;
use App\Domains\Tickets\Models\TicketPriority;
use App\Domains\Tickets\Models\TicketStatus;
use App\Models\User;
use Database\Seeders\SlaSeeder;
use Database\Seeders\TicketLookupSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SlaTest extends TestCase
{
    use RefreshDatabase;

    public function test_ticket_creation_applies_sla_timer(): void
    {
        $this->seed([TicketLookupSeeder::class, SlaSeeder::class]);

        $user = User::factory()->create();
        $status = TicketStatus::query()->where('slug', 'open')->first();
        $priority = TicketPriority::query()->where('slug', 'normal')->first();

        $this->actingAs($user)
            ->post('/tickets', [
                'subject' => 'SLA test',
                'ticket_status_id' => $status->id,
                'ticket_priority_id' => $priority->id,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('ticket_sla_timers', [
            'ticket_id' => Ticket::query()->where('subject', 'SLA test')->value('id'),
        ]);
    }

    public function test_sla_breach_command_marks_overdue_timers(): void
    {
        $this->seed([TicketLookupSeeder::class, SlaSeeder::class]);

        $user = User::factory()->create();
        $status = TicketStatus::query()->where('slug', 'open')->first();
        $priority = TicketPriority::query()->where('slug', 'normal')->first();

        $this->actingAs($user)->post('/tickets', [
            'subject' => 'Overdue',
            'ticket_status_id' => $status->id,
            'ticket_priority_id' => $priority->id,
        ]);

        $ticket = Ticket::query()->where('subject', 'Overdue')->first();
        $ticket->slaTimer()->update([
            'first_response_due_at' => now()->subHour(),
        ]);

        $this->artisan('sla:check-breaches')->assertSuccessful();

        $this->assertTrue($ticket->slaTimer()->first()->first_response_breached);
    }

    public function test_admin_can_view_sla_settings(): void
    {
        $this->seed(SlaSeeder::class);
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get('/settings/sla')
            ->assertOk();
    }

    public function test_ticket_show_includes_sla_snapshot_with_pending_time(): void
    {
        $this->seed([TicketLookupSeeder::class, SlaSeeder::class]);

        $admin = User::factory()->admin()->create();
        $status = TicketStatus::query()->where('slug', 'open')->first();
        $priority = TicketPriority::query()->where('slug', 'normal')->first();

        $this->actingAs($admin)->post('/tickets', [
            'subject' => 'SLA panel test',
            'ticket_status_id' => $status->id,
            'ticket_priority_id' => $priority->id,
        ]);

        $ticket = Ticket::query()->where('subject', 'SLA panel test')->first();

        $this->actingAs($admin)
            ->get("/tickets/{$ticket->id}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Tickets/Show')
                ->where('sla.active', true)
                ->where('sla.policy.name', 'Default SLA')
                ->has('sla.policy.rules', 2)
                ->where('sla.first_response.status', 'pending')
                ->where('sla.resolution.status', 'pending')
                ->etc());
    }

    public function test_ticket_show_applies_missing_sla_timer(): void
    {
        $this->seed([TicketLookupSeeder::class, SlaSeeder::class]);

        $admin = User::factory()->admin()->create();
        $contact = Contact::query()->create([
            'name' => 'Legacy Customer',
            'email' => 'legacy@example.com',
        ]);
        $status = TicketStatus::query()->where('slug', 'open')->first();
        $priority = TicketPriority::query()->where('slug', 'normal')->first();

        $ticket = Ticket::query()->create([
            'number' => 'HD-99999',
            'subject' => 'Legacy ticket',
            'contact_id' => $contact->id,
            'ticket_status_id' => $status->id,
            'ticket_priority_id' => $priority->id,
        ]);

        $this->assertDatabaseMissing('ticket_sla_timers', ['ticket_id' => $ticket->id]);

        $this->actingAs($admin)
            ->get("/tickets/{$ticket->id}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('sla.active', true));

        $this->assertDatabaseHas('ticket_sla_timers', ['ticket_id' => $ticket->id]);
    }
}
