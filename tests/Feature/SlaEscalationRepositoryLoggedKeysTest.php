<?php

namespace Tests\Feature;

use App\Domains\Sla\Models\SlaEscalationLog;
use App\Domains\Sla\Models\SlaPolicy;
use App\Domains\Sla\Models\SlaEscalationRule;
use App\Domains\Sla\Models\TicketSlaTimer;
use App\Domains\Sla\Repositories\SlaEscalationRepository;
use App\Domains\Tickets\Models\Ticket;
use App\Domains\Tickets\Models\TicketPriority;
use App\Domains\Tickets\Models\TicketStatus;
use Database\Seeders\SlaSeeder;
use Database\Seeders\TicketLookupSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TenantTestCase;

class SlaEscalationRepositoryLoggedKeysTest extends TenantTestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([TicketLookupSeeder::class, SlaSeeder::class]);
    }

    public function test_logged_rule_keys_for_timers_groups_existing_logs(): void
    {
        $statusId = TicketStatus::query()->where('slug', 'open')->value('id');
        $priorityId = TicketPriority::query()->where('slug', 'normal')->value('id');
        $policyId = SlaPolicy::query()->where('is_default', true)->value('id');

        $firstTicket = Ticket::query()->create([
            'number' => 'HD-10001',
            'subject' => 'Timer one',
            'ticket_status_id' => $statusId,
            'ticket_priority_id' => $priorityId,
        ]);
        $secondTicket = Ticket::query()->create([
            'number' => 'HD-10002',
            'subject' => 'Timer two',
            'ticket_status_id' => $statusId,
            'ticket_priority_id' => $priorityId,
        ]);

        $firstTimer = TicketSlaTimer::query()->create(['ticket_id' => $firstTicket->id, 'sla_policy_id' => $policyId]);
        $secondTimer = TicketSlaTimer::query()->create(['ticket_id' => $secondTicket->id, 'sla_policy_id' => $policyId]);

        SlaEscalationLog::query()->create([
            'ticket_id' => $firstTicket->id,
            'ticket_sla_timer_id' => $firstTimer->id,
            'level' => 1,
            'breach_type' => SlaEscalationRule::BREACH_FIRST_RESPONSE,
            'triggered_at' => now(),
        ]);
        SlaEscalationLog::query()->create([
            'ticket_id' => $firstTicket->id,
            'ticket_sla_timer_id' => $firstTimer->id,
            'level' => 2,
            'breach_type' => SlaEscalationRule::BREACH_RESOLUTION,
            'triggered_at' => now(),
        ]);
        SlaEscalationLog::query()->create([
            'ticket_id' => $secondTicket->id,
            'ticket_sla_timer_id' => $secondTimer->id,
            'level' => 1,
            'breach_type' => SlaEscalationRule::BREACH_RESOLUTION,
            'triggered_at' => now(),
        ]);

        $repository = app(SlaEscalationRepository::class);
        $keys = $repository->loggedRuleKeysForTimers([$firstTimer->id, $secondTimer->id, 99999]);

        $this->assertArrayHasKey($firstTimer->id, $keys);
        $this->assertArrayHasKey($secondTimer->id, $keys);
        $this->assertArrayNotHasKey(99999, $keys);
        $this->assertArrayHasKey(
            SlaEscalationRepository::ruleKey(1, SlaEscalationRule::BREACH_FIRST_RESPONSE),
            $keys[$firstTimer->id],
        );
        $this->assertArrayHasKey(
            SlaEscalationRepository::ruleKey(2, SlaEscalationRule::BREACH_RESOLUTION),
            $keys[$firstTimer->id],
        );
        $this->assertArrayHasKey(
            SlaEscalationRepository::ruleKey(1, SlaEscalationRule::BREACH_RESOLUTION),
            $keys[$secondTimer->id],
        );
    }
}
