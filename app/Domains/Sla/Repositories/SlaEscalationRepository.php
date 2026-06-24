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

    public function loggedRuleKeysForTimers(array $timerIds): array
    {
        if ($timerIds === []) {
            return [];
        }

        return SlaEscalationLog::query()
            ->whereIn('ticket_sla_timer_id', $timerIds)
            ->get(['ticket_sla_timer_id', 'level', 'breach_type'])
            ->groupBy('ticket_sla_timer_id')
            ->map(fn ($logs) => $logs
                ->map(fn ($log) => self::ruleKey((int) $log->level, (string) $log->breach_type))
                ->flip()
                ->all())
            ->all();
    }

    public static function ruleKey(int $level, string $breachType): string
    {
        return "{$level}:{$breachType}";
    }

    public function createLog(array $data): SlaEscalationLog
    {
        return SlaEscalationLog::query()->create($data);
    }

    public function activeRulesGroupedByPolicy(array $policyIds): \Illuminate\Support\Collection
    {
        if ($policyIds === []) {
            return collect();
        }

        return SlaEscalationRule::query()
            ->whereIn('sla_policy_id', $policyIds)
            ->where('is_active', true)
            ->orderBy('breach_type')
            ->orderBy('level')
            ->get()
            ->groupBy('sla_policy_id');
    }

    public function timersEligibleForEscalation(): Collection
    {
        $now = now();

        return TicketSlaTimer::query()
            ->with(['ticket.assignee', 'ticket.team.lead', 'ticket.department.head', 'policy'])
            ->whereHas('ticket', fn ($query) => $query
                ->whereNull('merged_into_ticket_id')
                ->whereNull('closed_at'))
            ->where(function ($query) use ($now) {
                $query->where(function ($query) use ($now) {
                    $query->whereNull('first_responded_at')
                        ->whereNotNull('first_response_due_at')
                        ->where(function ($query) use ($now) {
                            $query->where('first_response_breached', true)
                                ->orWhere('first_response_due_at', '<=', $now);
                        });
                })->orWhere(function ($query) use ($now) {
                    $query->whereNull('resolved_at')
                        ->whereNotNull('resolution_due_at')
                        ->where(function ($query) use ($now) {
                            $query->where('resolution_breached', true)
                                ->orWhere('resolution_due_at', '<=', $now);
                        });
                });
            })
            ->get();
    }
}
