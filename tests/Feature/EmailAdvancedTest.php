<?php

namespace Tests\Feature;

use App\Domains\Channels\Models\EmailInbox;
use App\Domains\Settings\Models\HelpdeskSetting;
use App\Domains\Tickets\Models\TicketPriority;
use App\Domains\Tickets\Models\TicketStatus;
use App\Domains\Workforce\Models\Department;
use App\Domains\Workforce\Models\Team;
use App\Models\User;
use Database\Seeders\EmailSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\TicketLookupSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmailAdvancedTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([PermissionSeeder::class, TicketLookupSeeder::class, EmailSeeder::class]);
    }

    public function test_admin_can_update_email_advanced_policies(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->put('/settings/email/advanced', [
                'email_allow_agent_initiated' => true,
                'email_use_agent_name_in_from' => true,
                'email_detect_auto_replies' => true,
                'email_use_original_sender_for_forwarded' => true,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('helpdesk_settings', [
            'email_allow_agent_initiated' => true,
            'email_use_agent_name_in_from' => true,
        ]);
    }

    public function test_inbox_routes_new_ticket_to_team(): void
    {
        $department = Department::query()->create(['name' => 'Support', 'slug' => 'support', 'is_active' => true]);
        $team = Team::query()->create(['department_id' => $department->id, 'name' => 'Tier 1', 'slug' => 'tier-1', 'is_active' => true]);
        $inbox = EmailInbox::query()->where('address', 'support@helpdesk.test')->first();
        $inbox->update(['team_id' => $team->id, 'department_id' => $department->id, 'inbound_token' => 'route-token']);

        $this->postJson('/api/v1/channels/inbound/email', [
            'from_email' => 'customer@example.com',
            'from_name' => 'Customer',
            'subject' => 'Need help',
            'body' => 'Hello',
            'to_email' => 'support@helpdesk.test',
        ], ['X-Channel-Token' => 'route-token'])
            ->assertOk()
            ->assertJsonPath('action', 'created');

        $this->assertDatabaseHas('tickets', [
            'team_id' => $team->id,
            'department_id' => $department->id,
            'subject' => 'Need help',
        ]);
    }

    public function test_automated_reply_is_ignored_when_detection_enabled(): void
    {
        HelpdeskSetting::query()->first()->update(['email_detect_auto_replies' => true]);
        $inbox = EmailInbox::query()->where('address', 'support@helpdesk.test')->first();

        $this->postJson('/api/v1/channels/inbound/email', [
            'from_email' => 'agent@example.com',
            'subject' => 'Out of office',
            'body' => 'I am away',
            'to_email' => 'support@helpdesk.test',
            'headers' => ['Auto-Submitted' => 'auto-replied'],
        ], ['X-Channel-Token' => $inbox->inbound_token])
            ->assertOk()
            ->assertJsonPath('action', 'ignored');
    }
}
