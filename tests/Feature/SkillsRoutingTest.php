<?php

namespace Tests\Feature;

use App\Domains\Assignment\Models\AssignmentRule;
use App\Domains\Contacts\Models\Contact;
use App\Domains\Contacts\Models\Organization;
use App\Domains\Sla\Models\SlaPolicy;
use App\Domains\Sla\Models\SlaTarget;
use App\Domains\Sla\Services\SlaService;
use App\Domains\Tickets\Models\Ticket;
use App\Domains\Tickets\Models\TicketPriority;
use App\Domains\Tickets\Models\TicketStatus;
use App\Domains\Workforce\Models\Skill;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\SlaSeeder;
use Database\Seeders\TicketLookupSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SkillsRoutingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([PermissionSeeder::class, TicketLookupSeeder::class, SlaSeeder::class]);
    }

    public function test_skills_rule_assigns_only_qualified_agent(): void
    {
        $billing = Skill::query()->create(['name' => 'Billing', 'slug' => 'billing']);

        $qualified = User::factory()->create();
        $qualified->assignRole('agent');
        $qualified->skills()->attach($billing->id);

        $other = User::factory()->create();
        $other->assignRole('agent');

        $urgentPriorityId = TicketPriority::query()->where('slug', 'urgent')->value('id');

        AssignmentRule::query()->create([
            'name' => 'Urgent billing',
            'strategy' => AssignmentRule::STRATEGY_ROUND_ROBIN,
            'is_active' => true,
            'sort_order' => 0,
            'ticket_priority_id' => $urgentPriorityId,
            'skill_ids' => [$billing->id],
        ]);

        $status = TicketStatus::query()->where('slug', 'open')->first();
        $creator = User::factory()->create();
        $creator->assignRole('agent');

        $this->actingAs($creator)
            ->post('/tickets', [
                'subject' => 'Invoice dispute',
                'ticket_status_id' => $status->id,
                'ticket_priority_id' => $urgentPriorityId,
            ])
            ->assertRedirect();

        $ticket = Ticket::query()->latest('id')->first();

        $this->assertSame($qualified->id, $ticket->assigned_to);
    }

    public function test_customer_tier_policy_applies_to_ticket(): void
    {
        $default = SlaPolicy::query()->where('is_default', true)->first();
        $urgentPriorityId = TicketPriority::query()->where('slug', 'urgent')->value('id');

        $premiumPolicy = SlaPolicy::query()->create([
            'name' => 'Premium SLA',
            'is_default' => false,
            'business_hours_id' => $default->business_hours_id,
            'customer_tier' => 'premium',
        ]);

        SlaTarget::query()->create([
            'sla_policy_id' => $premiumPolicy->id,
            'ticket_priority_id' => $urgentPriorityId,
            'first_response_minutes' => 5,
            'resolution_minutes' => 30,
        ]);

        $organization = Organization::query()->create([
            'name' => 'Premium Co',
            'customer_tier' => 'premium',
        ]);

        $contact = Contact::query()->create([
            'name' => 'VIP User',
            'email' => 'vip@premium.com',
            'organization_id' => $organization->id,
        ]);

        $ticket = Ticket::query()->create([
            'number' => 'HD-00300',
            'subject' => 'Premium support',
            'contact_id' => $contact->id,
            'ticket_status_id' => TicketStatus::query()->where('slug', 'open')->value('id'),
            'ticket_priority_id' => $urgentPriorityId,
        ]);

        app(SlaService::class)->applyToTicket($ticket);

        $ticket->refresh()->load('slaTimer');

        $this->assertNotNull($ticket->slaTimer);
        $this->assertSame($premiumPolicy->id, $ticket->slaTimer->sla_policy_id);
    }

    public function test_admin_can_manage_skills(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->post('/settings/skills', ['name' => 'Technical'])
            ->assertRedirect();

        $this->assertDatabaseHas('skills', ['name' => 'Technical', 'slug' => 'technical']);
    }
}
