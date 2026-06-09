<?php

namespace Tests\Feature;

use App\Domains\Channels\Models\Channel;
use App\Domains\Contacts\Models\Contact;
use App\Domains\Tickets\Models\Ticket;
use App\Domains\Tickets\Models\TicketMessage;
use App\Domains\Tickets\Models\TicketPriority;
use App\Domains\Tickets\Models\TicketStatus;
use App\Domains\Tickets\Services\TicketReadService;
use App\Models\User;
use Database\Seeders\TicketLookupSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TicketReadTest extends TestCase
{
    use RefreshDatabase;

    private function createTicketWithMessages(): array
    {
        $this->seed(TicketLookupSeeder::class);

        $status = TicketStatus::query()->where('slug', 'open')->firstOrFail();
        $priority = TicketPriority::query()->where('slug', 'normal')->firstOrFail();
        $channel = Channel::query()->create([
            'name' => 'Chat',
            'slug' => 'chat-test',
            'type' => Channel::TYPE_CHAT,
            'is_active' => true,
            'settings' => [],
        ]);
        $contact = Contact::query()->create([
            'name' => 'Visitor',
            'email' => 'visitor@example.com',
        ]);

        $ticket = Ticket::query()->create([
            'number' => 'HD-00999',
            'subject' => 'Unread badge test',
            'ticket_status_id' => $status->id,
            'ticket_priority_id' => $priority->id,
            'channel_id' => $channel->id,
            'contact_id' => $contact->id,
        ]);

        $visitorMessage = TicketMessage::query()->create([
            'ticket_id' => $ticket->id,
            'contact_id' => $contact->id,
            'body' => 'Hello',
            'is_internal' => false,
            'channel_id' => $channel->id,
        ]);

        $agent = User::factory()->create();

        $agentMessage = TicketMessage::query()->create([
            'ticket_id' => $ticket->id,
            'user_id' => $agent->id,
            'body' => 'Reply',
            'is_internal' => false,
            'channel_id' => $channel->id,
        ]);

        return compact('ticket', 'visitorMessage', 'agentMessage', 'agent', 'contact');
    }

    public function test_unread_count_tracks_customer_messages_only(): void
    {
        ['ticket' => $ticket, 'visitorMessage' => $visitorMessage, 'agent' => $agent] = $this->createTicketWithMessages();

        $service = app(TicketReadService::class);

        $this->assertSame(1, $service->countForTicket($agent->id, $ticket->id));

        $service->markAsRead($agent->id, $ticket->id, $visitorMessage->id);

        $this->assertSame(0, $service->countForTicket($agent->id, $ticket->id));
    }

    public function test_workspace_queue_includes_unread_counts(): void
    {
        ['ticket' => $ticket] = $this->createTicketWithMessages();
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)
            ->get('/workspace')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Workspace/Index')
                ->has('queue.data.0.unread_count'));

        $queueTicket = collect($response->original->getData()['page']['props']['queue']['data'] ?? [])
            ->firstWhere('id', $ticket->id);

        $this->assertNotNull($queueTicket);
        $this->assertSame(1, $queueTicket['unread_count']);
    }

    public function test_mark_read_endpoint_clears_unread_count(): void
    {
        ['ticket' => $ticket] = $this->createTicketWithMessages();
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->post("/workspace/tickets/{$ticket->id}/read")
            ->assertOk()
            ->assertJsonPath('unread_count', 0);

        $this->assertSame(0, app(TicketReadService::class)->countForTicket($admin->id, $ticket->id));
    }

    public function test_tickets_index_includes_unread_counts(): void
    {
        ['ticket' => $ticket] = $this->createTicketWithMessages();
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)
            ->get('/tickets')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Tickets/Index')
                ->has('tickets.data.0.unread_count'));

        $listed = collect($response->original->getData()['page']['props']['tickets']['data'] ?? [])
            ->firstWhere('id', $ticket->id);

        $this->assertNotNull($listed);
        $this->assertSame(1, $listed['unread_count']);
    }
}
