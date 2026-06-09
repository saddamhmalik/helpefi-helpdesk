<?php

namespace Tests\Feature;

use App\Domains\Contacts\Models\Contact;
use App\Domains\Contacts\Models\Organization;
use App\Domains\Security\Models\AuditLog;
use App\Domains\Tickets\Models\Ticket;
use App\Domains\Tickets\Models\TicketPriority;
use App\Domains\Tickets\Models\TicketStatus;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\TicketLookupSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TenantTestCase;

class TenantExportTest extends TenantTestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionSeeder::class);
        $this->seed(TicketLookupSeeder::class);
    }

    public function test_agent_can_export_tickets_csv(): void
    {
        $agent = User::factory()->create();
        $agent->assignRole('agent');

        $status = TicketStatus::query()->where('slug', 'open')->first();
        $priority = TicketPriority::query()->where('slug', 'normal')->first();

        Ticket::query()->create([
            'number' => 'HD-EXPORT-1',
            'subject' => 'Export me',
            'ticket_status_id' => $status->id,
            'ticket_priority_id' => $priority->id,
        ]);

        $response = $this->actingAs($agent)
            ->tenantGet('/tickets/export/csv');

        $response->assertOk();
        $response->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $this->assertStringContainsString('HD-EXPORT-1', $response->streamedContent());
        $this->assertStringContainsString('Export me', $response->streamedContent());
    }

    public function test_agent_can_export_customers_csv(): void
    {
        $agent = User::factory()->create();
        $agent->assignRole('agent');

        Contact::query()->create([
            'name' => 'Export Customer',
            'email' => 'export@customer.test',
        ]);

        $response = $this->actingAs($agent)
            ->tenantGet('/contacts/export');

        $response->assertOk();
        $response->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $this->assertStringContainsString('Export Customer', $response->streamedContent());
        $this->assertStringContainsString('export@customer.test', $response->streamedContent());
    }

    public function test_admin_can_export_team_members_csv(): void
    {
        $admin = User::query()->where('email', 'admin@helpdesk.test')->first();

        $response = $this->actingAs($admin)
            ->tenantGet('/settings/members/export');

        $response->assertOk();
        $response->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $this->assertStringContainsString('admin@helpdesk.test', $response->streamedContent());
    }

    public function test_agent_cannot_export_team_members_csv(): void
    {
        $agent = User::factory()->create();
        $agent->assignRole('agent');

        $this->actingAs($agent)
            ->tenantGet('/settings/members/export')
            ->assertForbidden();
    }

    public function test_user_with_audit_permission_can_export_audit_logs_csv(): void
    {
        Permission::findOrCreate('audit.view', 'web');

        $agent = User::factory()->create();
        $agent->assignRole('agent');
        $agent->givePermissionTo('audit.view');

        AuditLog::query()->create([
            'user_id' => $agent->id,
            'event' => 'ticket.exported',
            'created_at' => now(),
        ]);

        $response = $this->actingAs($agent)
            ->tenantGet('/settings/audit-logs/export');

        $response->assertOk();
        $response->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $this->assertStringContainsString('ticket.exported', $response->streamedContent());
    }

    public function test_agent_can_export_organizations_csv(): void
    {
        $agent = User::factory()->create();
        $agent->assignRole('agent');

        Organization::query()->create([
            'name' => 'Export Org',
        ]);

        $response = $this->actingAs($agent)
            ->tenantGet('/organizations/export');

        $response->assertOk();
        $response->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $this->assertStringContainsString('Export Org', $response->streamedContent());
    }
}
