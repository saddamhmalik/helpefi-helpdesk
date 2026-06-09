<?php

namespace Tests\Feature;

use App\Domains\Tickets\Models\Ticket;
use App\Domains\Tickets\Models\TicketMessage;
use App\Domains\Tickets\Models\TicketPriority;
use App\Domains\Tickets\Models\TicketStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkspaceTest extends TestCase
{
    use RefreshDatabase;

    private function seedTicketMeta(): array
    {
        $status = TicketStatus::query()->create(['name' => 'Open', 'slug' => 'open', 'color' => '#000', 'sort_order' => 1, 'is_closed' => false]);
        $priority = TicketPriority::query()->create(['name' => 'Normal', 'slug' => 'normal', 'sort_order' => 1]);

        return [$status, $priority];
    }

    private function createTicket(array $overrides = []): Ticket
    {
        [$status, $priority] = $this->seedTicketMeta();

        return Ticket::query()->create(array_merge([
            'number' => 'HD-00001',
            'subject' => 'Workspace test',
            'ticket_status_id' => $status->id,
            'ticket_priority_id' => $priority->id,
        ], $overrides));
    }

    public function test_agent_can_access_workspace(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/workspace')
            ->assertOk();
    }

    public function test_customer_cannot_access_workspace(): void
    {
        $customer = User::factory()->customer()->create();

        $this->actingAs($customer)
            ->get('/workspace')
            ->assertRedirect(route('portal.my-tickets'));
    }

    public function test_agent_can_save_composer_draft(): void
    {
        $user = User::factory()->create();
        $ticket = $this->createTicket();

        $this->actingAs($user)
            ->putJson("/workspace/tickets/{$ticket->id}/draft", [
                'body' => 'Draft reply text',
                'is_internal' => true,
            ])
            ->assertOk()
            ->assertJsonPath('body', 'Draft reply text')
            ->assertJsonPath('is_internal', true);

        $this->assertDatabaseHas('ticket_composer_drafts', [
            'user_id' => $user->id,
            'ticket_id' => $ticket->id,
            'body' => 'Draft reply text',
            'is_internal' => true,
        ]);
    }

    public function test_agent_can_reply_from_workspace(): void
    {
        $user = User::factory()->create();
        $ticket = $this->createTicket();

        $this->actingAs($user)
            ->postJson("/workspace/tickets/{$ticket->id}/reply", [
                'body' => 'Workspace reply',
                'is_internal' => false,
            ])
            ->assertOk()
            ->assertJsonPath('message.body', 'Workspace reply');

        $this->assertDatabaseHas('ticket_messages', [
            'ticket_id' => $ticket->id,
            'user_id' => $user->id,
            'body' => 'Workspace reply',
        ]);

        $this->assertDatabaseMissing('ticket_composer_drafts', [
            'user_id' => $user->id,
            'ticket_id' => $ticket->id,
        ]);
    }

    public function test_poll_returns_new_messages_since_timestamp(): void
    {
        $user = User::factory()->create();
        $ticket = $this->createTicket();

        $older = TicketMessage::query()->create([
            'ticket_id' => $ticket->id,
            'user_id' => $user->id,
            'body' => 'Older message',
            'is_internal' => false,
        ]);
        TicketMessage::query()->whereKey($older->id)->update([
            'created_at' => now()->subMinutes(5),
            'updated_at' => now()->subMinutes(5),
        ]);

        $since = now()->subMinute()->toIso8601String();

        TicketMessage::query()->create([
            'ticket_id' => $ticket->id,
            'user_id' => $user->id,
            'body' => 'New message',
            'is_internal' => false,
        ]);

        $response = $this->actingAs($user)
            ->getJson('/workspace/tickets/'.$ticket->id.'/poll?since='.rawurlencode($since))
            ->assertOk();

        $this->assertCount(1, $response->json('new_messages'));
        $this->assertSame('New message', $response->json('new_messages.0.body'));
    }

    public function test_quick_update_changes_ticket_fields(): void
    {
        $user = User::factory()->create();
        $ticket = $this->createTicket();
        $closed = TicketStatus::query()->create(['name' => 'Closed', 'slug' => 'closed', 'color' => '#000', 'sort_order' => 2, 'is_closed' => true]);

        $this->actingAs($user)
            ->patchJson("/workspace/tickets/{$ticket->id}", [
                'ticket_status_id' => $closed->id,
                'assigned_to' => $user->id,
            ])
            ->assertOk()
            ->assertJsonPath('ticket_status_id', $closed->id)
            ->assertJsonPath('assigned_to', $user->id);
    }
}
