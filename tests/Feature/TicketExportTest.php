<?php

namespace Tests\Feature;

use App\Domains\Security\Models\AuditLog;
use App\Domains\Security\Repositories\AuditLogRepository;
use App\Domains\Tickets\Jobs\SendTicketExportJob;
use App\Domains\Tickets\Models\Ticket;
use App\Domains\Tickets\Models\TicketPriority;
use App\Domains\Tickets\Models\TicketStatus;
use App\Domains\Tickets\Services\TicketLifecycleService;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class TicketExportTest extends TestCase
{
    use RefreshDatabase;

    private function seedTicket(User $user): Ticket
    {
        $statusOpen = TicketStatus::query()->create(['name' => 'Open', 'slug' => 'open', 'color' => '#000', 'sort_order' => 1, 'is_closed' => false]);
        $statusClosed = TicketStatus::query()->create(['name' => 'Closed', 'slug' => 'closed', 'color' => '#000', 'sort_order' => 2, 'is_closed' => true]);
        $priorityNormal = TicketPriority::query()->create(['name' => 'Normal', 'slug' => 'normal', 'sort_order' => 1]);
        $priorityHigh = TicketPriority::query()->create(['name' => 'High', 'slug' => 'high', 'sort_order' => 2]);

        $ticket = Ticket::query()->create([
            'number' => 'HD-00001',
            'subject' => 'Export test',
            'description' => 'Ticket body',
            'ticket_status_id' => $statusOpen->id,
            'ticket_priority_id' => $priorityNormal->id,
        ]);

        AuditLog::query()->create([
            'user_id' => $user->id,
            'event' => 'ticket.created',
            'subject_type' => Ticket::class,
            'subject_id' => $ticket->id,
            'properties' => ['number' => $ticket->number],
            'created_at' => now()->subHour(),
        ]);

        AuditLog::query()->create([
            'user_id' => $user->id,
            'event' => 'ticket.updated',
            'subject_type' => Ticket::class,
            'subject_id' => $ticket->id,
            'properties' => [
                'number' => $ticket->number,
                'changes' => [
                    'ticket_priority_id' => ['from' => $priorityNormal->id, 'to' => $priorityHigh->id],
                ],
            ],
            'created_at' => now()->subMinutes(30),
        ]);

        AuditLog::query()->create([
            'user_id' => $user->id,
            'event' => 'ticket.replied',
            'subject_type' => Ticket::class,
            'subject_id' => $ticket->id,
            'properties' => ['number' => $ticket->number],
            'created_at' => now()->subMinutes(10),
        ]);

        AuditLog::query()->create([
            'user_id' => $user->id,
            'event' => 'ticket.updated',
            'subject_type' => Ticket::class,
            'subject_id' => $ticket->id,
            'properties' => [
                'number' => $ticket->number,
                'changes' => [
                    'ticket_status_id' => ['from' => $statusOpen->id, 'to' => $statusClosed->id],
                ],
            ],
            'created_at' => now()->subMinutes(5),
        ]);

        return $ticket;
    }

    public function test_lifecycle_excludes_replies_and_describes_changes(): void
    {
        $user = User::factory()->create();
        $ticket = $this->seedTicket($user);

        $timeline = app(TicketLifecycleService::class)->timeline($ticket->id);

        $this->assertCount(3, $timeline);
        $this->assertSame('Ticket created', $timeline[0]['description']);
        $this->assertStringContainsString('Priority changed from Normal to High', $timeline[1]['description']);
        $this->assertStringContainsString('Status changed from Open to Closed', $timeline[2]['description']);
        $this->assertSame($user->name, $timeline[0]['actor']);
    }

    public function test_user_can_download_ticket_pdf(): void
    {
        $user = User::factory()->create();
        $ticket = $this->seedTicket($user);

        $response = $this->actingAs($user)
            ->get("/tickets/{$ticket->id}/export/pdf");

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
        $this->assertStringContainsString('HD-00001', $response->headers->get('content-disposition'));
    }

    public function test_user_can_queue_ticket_export_email(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        $ticket = $this->seedTicket($user);

        $this->actingAs($user)
            ->post("/tickets/{$ticket->id}/export/email", [
                'email' => 'recipient@example.com',
                'include_conversation' => true,
            ])
            ->assertRedirect();

        Queue::assertPushed(SendTicketExportJob::class, function (SendTicketExportJob $job) use ($ticket) {
            return $job->ticketId === $ticket->id
                && $job->email === 'recipient@example.com'
                && $job->includeConversation === true;
        });
    }

    public function test_ticket_show_includes_lifecycle(): void
    {
        $user = User::factory()->create();
        $ticket = $this->seedTicket($user);

        $this->actingAs($user)
            ->get("/tickets/{$ticket->id}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Tickets/Show')
                ->has('lifecycle', 3)
                ->where('lifecycle.0.description', 'Ticket created'));
    }

    public function test_audit_repository_excludes_events_for_ticket(): void
    {
        $user = User::factory()->create();
        $ticket = $this->seedTicket($user);

        $logs = app(AuditLogRepository::class)->forTicket($ticket->id, ['ticket.replied']);

        $this->assertCount(3, $logs);
        $this->assertTrue($logs->every(fn ($log) => $log->event !== 'ticket.replied'));
    }
}
