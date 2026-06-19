<?php

namespace Tests\Feature;

use App\Domains\Automation\Models\AutomationRule;
use App\Domains\Contacts\Models\Contact;
use App\Domains\Notifications\Notifications\CustomerReplyNotification;
use App\Domains\Notifications\Notifications\TicketAssignedNotification;
use App\Domains\Tickets\Models\Ticket;
use App\Domains\Tickets\Models\TicketPriority;
use App\Domains\Tickets\Models\TicketStatus;
use App\Models\User;
use Database\Seeders\EmailTemplateSeeder;
use Database\Seeders\NotificationSeeder;
use Database\Seeders\TicketLookupSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class NotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_ticket_assignment_notifies_assignee(): void
    {
        Notification::fake();
        $this->seed([TicketLookupSeeder::class, NotificationSeeder::class]);

        $assignee = User::factory()->create();
        $agent = User::factory()->create();
        $status = TicketStatus::query()->where('slug', 'open')->first();
        $priority = TicketPriority::query()->where('slug', 'normal')->first();

        $this->actingAs($agent)
            ->post('/tickets', [
                'subject' => 'Need VPN access',
                'description' => 'Please help',
                'ticket_status_id' => $status->id,
                'ticket_priority_id' => $priority->id,
                'assigned_to' => $assignee->id,
            ])
            ->assertRedirect();

        Notification::assertSentTo($assignee, TicketAssignedNotification::class);
    }

    public function test_customer_reply_notifies_assignee(): void
    {
        Notification::fake();
        $this->seed([TicketLookupSeeder::class, NotificationSeeder::class]);

        $assignee = User::factory()->create();
        $status = TicketStatus::query()->where('slug', 'open')->first();
        $priority = TicketPriority::query()->where('slug', 'normal')->first();
        $contact = Contact::query()->create(['name' => 'Jane', 'email' => 'jane@example.com']);

        $ticket = Ticket::query()->create([
            'number' => 'HD-01001',
            'subject' => 'Billing issue',
            'contact_id' => $contact->id,
            'assigned_to' => $assignee->id,
            'ticket_status_id' => $status->id,
            'ticket_priority_id' => $priority->id,
        ]);

        app(\App\Domains\Tickets\Services\TicketService::class)->addContactMessage(
            $ticket->id,
            $contact->id,
            'Any update on this?',
            1,
        );

        Notification::assertSentTo($assignee, CustomerReplyNotification::class);
    }

    public function test_automation_assignment_notifies_assignee(): void
    {
        Notification::fake();
        $this->seed([TicketLookupSeeder::class, NotificationSeeder::class]);

        $assignee = User::factory()->create();
        $status = TicketStatus::query()->where('slug', 'open')->first();
        $priority = TicketPriority::query()->where('slug', 'normal')->first();

        AutomationRule::query()->create([
            'name' => 'Urgent assignment',
            'trigger' => AutomationRule::TRIGGER_TICKET_CREATED,
            'conditions' => [
                ['field' => 'subject', 'operator' => 'contains', 'value' => 'urgent'],
            ],
            'actions' => [
                ['type' => 'assign_to', 'value' => $assignee->id],
            ],
            'is_active' => true,
        ]);

        $this->actingAs(User::factory()->create())
            ->post('/tickets', [
                'subject' => 'URGENT billing issue',
                'description' => 'Need help',
                'ticket_status_id' => $status->id,
                'ticket_priority_id' => $priority->id,
            ])
            ->assertRedirect();

        Notification::assertSentTo($assignee, TicketAssignedNotification::class);
    }

    public function test_admin_can_view_notification_settings(): void
    {
        $this->seed([NotificationSeeder::class, EmailTemplateSeeder::class]);
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get('/settings/notifications')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Settings/Notifications')
                ->has('emailTemplates', 5)
            );
    }

    public function test_agent_can_view_notifications_index(): void
    {
        $agent = User::factory()->create();

        $this->actingAs($agent)
            ->get('/notifications')
            ->assertOk();
    }

    public function test_api_returns_notification_summary(): void
    {
        $agent = User::factory()->create();
        $login = $this->postJson('/api/v1/auth/login', [
            'email' => $agent->email,
            'password' => 'password',
        ]);

        $this->withToken($login->json('token'))
            ->getJson('/api/v1/notifications/summary')
            ->assertOk()
            ->assertJsonStructure(['unread_count', 'recent']);
    }
}
