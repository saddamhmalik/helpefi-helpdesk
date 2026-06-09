<?php

namespace Tests\Feature;

use App\Domains\Automation\Models\AutomationRule;
use App\Domains\Channels\Models\Channel;
use App\Domains\Tickets\Models\Ticket;
use App\Domains\Tickets\Models\TicketPriority;
use App\Domains\Tickets\Models\TicketStatus;
use App\Models\User;
use Database\Seeders\ChannelSeeder;
use Database\Seeders\EmailSeeder;
use Database\Seeders\TicketLookupSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AutomationTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_automation_settings(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get('/settings/automation')
            ->assertOk();
    }

    public function test_agent_cannot_view_automation_settings(): void
    {
        $agent = User::factory()->create();

        $this->actingAs($agent)
            ->get('/settings/automation')
            ->assertForbidden();
    }

    public function test_rule_assigns_ticket_when_subject_contains_urgent(): void
    {
        $this->seed(TicketLookupSeeder::class);

        $agent = User::factory()->create();
        $status = TicketStatus::query()->where('slug', 'open')->first();
        $priority = TicketPriority::query()->where('slug', 'normal')->first();

        AutomationRule::query()->create([
            'name' => 'Urgent assignment',
            'trigger' => AutomationRule::TRIGGER_TICKET_CREATED,
            'conditions' => [
                ['field' => 'subject', 'operator' => 'contains', 'value' => 'urgent'],
            ],
            'actions' => [
                ['type' => 'assign_to', 'value' => $agent->id],
            ],
            'is_active' => true,
        ]);

        $this->actingAs(User::factory()->create())
            ->post('/tickets', [
                'subject' => 'URGENT billing issue',
                'ticket_status_id' => $status->id,
                'ticket_priority_id' => $priority->id,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('tickets', [
            'subject' => 'URGENT billing issue',
            'assigned_to' => $agent->id,
        ]);
    }

    public function test_rule_adds_internal_note_on_customer_email(): void
    {
        $this->seed([TicketLookupSeeder::class, ChannelSeeder::class, EmailSeeder::class]);

        $status = TicketStatus::query()->where('slug', 'open')->first();
        $priority = TicketPriority::query()->where('slug', 'normal')->first();
        $emailChannelId = Channel::query()->where('slug', 'email')->value('id');

        $ticket = Ticket::query()->create([
            'number' => 'HD-00099',
            'subject' => 'Help needed',
            'ticket_status_id' => $status->id,
            'ticket_priority_id' => $priority->id,
            'channel_id' => $emailChannelId,
        ]);

        AutomationRule::query()->create([
            'name' => 'Email follow-up note',
            'trigger' => AutomationRule::TRIGGER_CUSTOMER_MESSAGE,
            'conditions' => [
                ['field' => 'channel_id', 'operator' => 'equals', 'value' => $emailChannelId],
            ],
            'actions' => [
                ['type' => 'add_internal_note', 'value' => 'Customer replied via email.'],
            ],
            'is_active' => true,
        ]);

        $this->postJson('/api/v1/channels/inbound/email', [
            'from_email' => 'customer@example.com',
            'subject' => 'Re: [HD-00099] Help needed',
            'body' => 'Following up',
            'message_id' => 'auto-test-1',
        ], [
            'X-Channel-Token' => 'dev-inbound-token',
        ])->assertOk();

        $this->assertDatabaseHas('ticket_messages', [
            'ticket_id' => $ticket->id,
            'body' => 'Customer replied via email.',
            'is_internal' => true,
        ]);
    }

    public function test_admin_can_create_automation_rule_via_api(): void
    {
        $admin = User::factory()->admin()->create(['password' => bcrypt('password')]);

        $login = $this->postJson('/api/v1/auth/login', [
            'email' => $admin->email,
            'password' => 'password',
        ]);

        $this->withToken($login->json('token'))
            ->postJson('/api/v1/automation/rules', [
                'name' => 'API rule',
                'trigger' => AutomationRule::TRIGGER_TICKET_CREATED,
                'conditions' => [],
                'actions' => [
                    ['type' => 'add_internal_note', 'value' => 'Created'],
                ],
                'is_active' => true,
            ])
            ->assertCreated()
            ->assertJsonPath('name', 'API rule');
    }
}
