<?php

namespace App\Domains\Sla\Repositories;

use App\Domains\Sla\Models\SlaEscalationLog;
use App\Domains\Sla\Models\SlaEscalationRule;
use App\Domains\Sla\Models\TicketSlaTimer;
use Illuminate\Database\Eloquent\Collection;

class SlaEscalationRepository
{
    public function rulesForPolicy(int $policyId): Collection
    {
        return SlaEscalationRule::query()
            ->where('sla_policy_id', $policyId)
            ->where('is_active', true)
            ->orderBy('breach_type')
            ->orderBy('level')
            ->get();
    }

    public function allRulesGrouped(): Collection
    {
        return SlaEscalationRule::query()
            ->with('policy:id,name')
            ->orderBy('sla_policy_id')
            ->orderBy('breach_type')
            ->orderBy('level')
            ->get();
    }

    public function findRule(int $id): SlaEscalationRule
    {
        return SlaEscalationRule::query()->findOrFail($id);
    }

    public function upsertRule(array $data): SlaEscalationRule
    {
        return SlaEscalationRule::query()->updateOrCreate(
            [
                'sla_policy_id' => $data['sla_policy_id'],
                'level' => $data['level'],
                'breach_type' => $data['breach_type'],
            ],
            $data,
        );
    }

    public function deleteRule(SlaEscalationRule $rule): void
    {
        $rule->delete();
    }

    public function logExists(int $timerId, int $level, string $breachType): bool
    {
        return SlaEscalationLog::query()
            ->where('ticket_sla_timer_id', $timerId)
            ->where('level', $level)
            ->where('breach_type', $breachType)
            ->exists();
    }

    public function createLog(array $data): SlaEscalationLog
    {
        return SlaEscalationLog::query()->create($data);
    }

    public function timersEligibleForEscalation(): Collection
    {
        return TicketSlaTimer::query()
            ->with(['ticket.assignee', 'ticket.team.lead', 'ticket.department.head', 'policy'])
            ->whereHas('ticket', fn ($query) => $query->whereNull('merged_into_ticket_id'))
            ->get();
    }
}
