<?php

namespace Tests\Feature;

use App\Domains\Settings\Models\HelpdeskSetting;
use App\Domains\Tickets\Models\Ticket;
use App\Domains\Tickets\Models\TicketPriority;
use App\Domains\Tickets\Models\TicketStatus;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TicketSettingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionSeeder::class);
    }

    public function test_admin_can_update_ticket_prefix_and_contact_fields(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->put('/settings/tickets', [
                'ticket_number_prefix' => 'SUP',
                'contact_fields' => [
                    [
                        'name' => 'account_id',
                        'label' => 'Account ID',
                        'type' => 'text',
                        'required' => true,
                        'options' => [],
                    ],
                ],
                'ticket_fields' => [
                    [
                        'name' => 'severity',
                        'label' => 'Severity',
                        'type' => 'select',
                        'required' => false,
                        'options' => ['Low', 'High'],
                    ],
                ],
                'user_fields' => [
                    [
                        'name' => 'employee_id',
                        'label' => 'Employee ID',
                        'type' => 'text',
                        'required' => false,
                        'options' => [],
                    ],
                ],
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('helpdesk_settings', [
            'ticket_number_prefix' => 'SUP-',
        ]);

        $this->actingAs($admin)
            ->get('/settings/tickets')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Settings/Tickets')
                ->where('settings.ticket_number_prefix', 'SUP-')
                ->where('settings.contact_fields.0.name', 'account_id')
                ->where('settings.ticket_fields.0.name', 'severity')
                ->where('settings.user_fields.0.name', 'employee_id'));
    }

    public function test_new_tickets_use_configured_prefix(): void
    {
        HelpdeskSetting::query()->create([
            'ticket_number_prefix' => 'ACME-',
            'contact_fields' => [],
        ]);

        $status = TicketStatus::query()->create(['name' => 'Open', 'slug' => 'open', 'color' => '#000', 'sort_order' => 1, 'is_closed' => false]);
        $priority = TicketPriority::query()->create(['name' => 'Normal', 'slug' => 'normal', 'sort_order' => 1]);
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->post('/tickets', [
                'subject' => 'Prefix test',
                'ticket_status_id' => $status->id,
                'ticket_priority_id' => $priority->id,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('tickets', [
            'subject' => 'Prefix test',
            'number' => 'ACME-00001',
        ]);
    }

    public function test_contact_custom_fields_are_saved(): void
    {
        HelpdeskSetting::query()->create([
            'ticket_number_prefix' => 'HD-',
            'contact_fields' => [
                [
                    'name' => 'department',
                    'label' => 'Department',
                    'type' => 'select',
                    'required' => true,
                    'options' => ['Sales', 'Support'],
                ],
            ],
        ]);

        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->post('/contacts', [
                'name' => 'Field Test',
                'email' => 'fields@example.com',
                'custom_fields' => ['department' => 'Sales'],
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('contacts', [
            'email' => 'fields@example.com',
        ]);

        $contact = \App\Domains\Contacts\Models\Contact::query()->where('email', 'fields@example.com')->first();
        $this->assertSame(['department' => 'Sales'], $contact->custom_fields);
    }

    public function test_ticket_custom_fields_are_saved(): void
    {
        HelpdeskSetting::query()->create([
            'ticket_number_prefix' => 'HD-',
            'contact_fields' => [],
            'ticket_fields' => [
                [
                    'name' => 'severity',
                    'label' => 'Severity',
                    'type' => 'text',
                    'required' => true,
                    'options' => [],
                ],
            ],
            'user_fields' => [],
        ]);

        [$status, $priority] = $this->seedTicketMeta();
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->post('/tickets', [
                'subject' => 'Field test',
                'ticket_status_id' => $status->id,
                'ticket_priority_id' => $priority->id,
                'custom_fields' => ['severity' => 'Critical'],
            ])
            ->assertRedirect();

        $ticket = Ticket::query()->where('subject', 'Field test')->first();
        $this->assertSame(['severity' => 'Critical'], $ticket->custom_fields);
    }

    public function test_user_custom_fields_are_saved(): void
    {
        HelpdeskSetting::query()->create([
            'ticket_number_prefix' => 'HD-',
            'contact_fields' => [],
            'ticket_fields' => [],
            'user_fields' => [
                [
                    'name' => 'employee_id',
                    'label' => 'Employee ID',
                    'type' => 'text',
                    'required' => true,
                    'options' => [],
                ],
            ],
        ]);

        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->post('/settings/members', [
                'name' => 'Field Agent',
                'email' => 'fieldagent@example.com',
                'role' => 'agent',
                'custom_fields' => ['employee_id' => 'E-100'],
            ])
            ->assertRedirect();

        $member = User::query()->where('email', 'fieldagent@example.com')->first();
        $this->assertSame(['employee_id' => 'E-100'], $member->custom_fields);
    }

    public function test_ticket_list_hides_non_agent_assignee(): void
    {
        [$status, $priority] = $this->seedTicketMeta();

        $customer = User::factory()->customer()->create();
        $admin = User::factory()->admin()->create();

        $ticket = Ticket::query()->create([
            'number' => 'HD-00099',
            'subject' => 'Bad assignee',
            'ticket_status_id' => $status->id,
            'ticket_priority_id' => $priority->id,
            'assigned_to' => $customer->id,
        ]);

        $response = $this->actingAs($admin)->get('/tickets');
        $tickets = collect($response->original->getData()['page']['props']['tickets']['data']);
        $listed = $tickets->firstWhere('id', $ticket->id);

        $this->assertNull($listed['assignee']);
    }

    private function seedTicketMeta(): array
    {
        $status = TicketStatus::query()->create(['name' => 'Open', 'slug' => 'open', 'color' => '#000', 'sort_order' => 1, 'is_closed' => false]);
        $priority = TicketPriority::query()->create(['name' => 'Normal', 'slug' => 'normal', 'sort_order' => 1]);

        return [$status, $priority];
    }
}
