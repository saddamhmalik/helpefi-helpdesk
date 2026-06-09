<?php

namespace Tests\Feature;

use App\Domains\Security\Jobs\RecordAuditLogJob;
use App\Domains\Tickets\Models\TicketPriority;
use App\Domains\Tickets\Models\TicketStatus;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\TicketLookupSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AuditLogTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionSeeder::class);
    }

    public function test_admin_can_view_audit_logs_page(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get('/settings/audit-logs')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Settings/AuditLogs'));
    }

    public function test_agent_with_audit_permission_can_view_audit_logs(): void
    {
        $agent = User::factory()->create();
        $agent->assignRole('agent');
        $agent->givePermissionTo('audit.view');

        $this->actingAs($agent)
            ->get('/settings/audit-logs')
            ->assertOk();
    }

    public function test_agent_without_audit_permission_cannot_view_audit_logs(): void
    {
        $agent = User::factory()->create();
        $agent->assignRole('agent');

        $this->actingAs($agent)
            ->get('/settings/audit-logs')
            ->assertForbidden();
    }

    public function test_creating_ticket_writes_audit_log(): void
    {
        $this->seed(TicketLookupSeeder::class);

        $admin = User::factory()->admin()->create();
        $status = TicketStatus::query()->where('slug', 'open')->first();
        $priority = TicketPriority::query()->where('slug', 'normal')->first();

        $this->actingAs($admin)
            ->post('/tickets', [
                'subject' => 'Audit trail test',
                'description' => 'Testing audit logging',
                'ticket_status_id' => $status->id,
                'ticket_priority_id' => $priority->id,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('audit_logs', [
            'event' => 'ticket.created',
            'user_id' => $admin->id,
        ]);
    }

    public function test_audit_log_is_queued_instead_of_written_inline(): void
    {
        Queue::fake();

        $this->seed(TicketLookupSeeder::class);

        $admin = User::factory()->admin()->create();
        $status = TicketStatus::query()->where('slug', 'open')->first();
        $priority = TicketPriority::query()->where('slug', 'normal')->first();

        $this->actingAs($admin)
            ->post('/tickets', [
                'subject' => 'Queued audit test',
                'description' => 'Testing queued audit logging',
                'ticket_status_id' => $status->id,
                'ticket_priority_id' => $priority->id,
            ])
            ->assertRedirect();

        Queue::assertPushed(RecordAuditLogJob::class, function (RecordAuditLogJob $job) use ($admin) {
            return $job->event === 'ticket.created'
                && $job->userId === $admin->id;
        });

        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_audit_view_permission_exists_in_catalog(): void
    {
        $this->assertTrue(Permission::query()->where('name', 'audit.view')->exists());
    }
}
