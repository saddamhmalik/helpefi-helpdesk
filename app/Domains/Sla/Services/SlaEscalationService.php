<?php

namespace App\Domains\Sla\Services;

use App\Domains\Billing\Contracts\FeatureEntitlementChecker;
use App\Domains\Notifications\Services\NotificationService;
use App\Domains\Sla\Models\SlaEscalationRule;
use App\Domains\Sla\Models\TicketSlaTimer;
use App\Domains\Sla\Repositories\SlaEscalationRepository;
use App\Domains\Tickets\Services\TicketActionService;
use Illuminate\Database\Eloquent\Collection;

class SlaEscalationService
{
    public function __construct(
        private SlaEscalationRepository $escalations,
        private FeatureEntitlementChecker $entitlements,
        private TicketActionService $actions,
        private NotificationService $notifications,
    ) {
    }

    public function rulesForPolicies(): Collection
    {
        return $this->escalations->allRulesGrouped();
    }

    public function storeValidationRules(): array
    {
        return [
            'sla_policy_id' => ['required', 'exists:sla_policies,id'],
            'level' => ['required', 'integer', 'in:1,2'],
            'breach_type' => ['required', 'in:first_response,resolution'],
            'delay_minutes_after_breach' => ['required', 'integer', 'min:0'],
            'actions' => ['required', 'array', 'min:1'],
            'actions.*.type' => ['required', 'string'],
            'actions.*.value' => ['nullable'],
            'is_active' => ['boolean'],
        ];
    }

    public function listRules(): array
    {
        return $this->rulesForPolicies()
            ->map(fn (SlaEscalationRule $rule) => $this->serializeRule($rule))
            ->values()
            ->all();
    }

    public function serializeRule(SlaEscalationRule $rule): array
    {
        return [
            'id' => $rule->id,
            'sla_policy_id' => $rule->sla_policy_id,
            'policy_name' => $rule->policy?->name,
            'level' => $rule->level,
            'breach_type' => $rule->breach_type,
            'delay_minutes_after_breach' => $rule->delay_minutes_after_breach,
            'actions' => $rule->actions ?? [],
            'is_active' => (bool) $rule->is_active,
        ];
    }

    public function meta(): array
    {
        return [
            'breach_types' => [
                ['value' => SlaEscalationRule::BREACH_FIRST_RESPONSE, 'label' => 'First response breach'],
                ['value' => SlaEscalationRule::BREACH_RESOLUTION, 'label' => 'Resolution breach'],
            ],
            'levels' => [
                ['value' => 1, 'label' => 'Level 1 escalation'],
                ['value' => 2, 'label' => 'Level 2 escalation'],
            ],
            'action_types' => [
                ['value' => 'notify_team_lead', 'label' => 'Notify team lead'],
                ['value' => 'notify_department_head', 'label' => 'Notify department head'],
                ['value' => 'assign_to_team_lead', 'label' => 'Assign to team lead'],
                ['value' => 'assign_to_department_head', 'label' => 'Assign to department head'],
                ['value' => 'set_priority', 'label' => 'Set priority'],
                ['value' => 'add_internal_note', 'label' => 'Add internal note'],
                ['value' => 'add_watcher', 'label' => 'Add watcher'],
            ],
        ];
    }

    public function saveRule(array $data): SlaEscalationRule
    {
        $this->entitlements->assertFeature('sla');

        return $this->escalations->upsertRule([
            'sla_policy_id' => $data['sla_policy_id'],
            'level' => $data['level'],
            'breach_type' => $data['breach_type'],
            'delay_minutes_after_breach' => $data['delay_minutes_after_breach'] ?? 0,
            'actions' => $data['actions'] ?? [],
            'is_active' => $data['is_active'] ?? true,
        ]);
    }

    public function deleteRule(int $id): void
    {
        $this->entitlements->assertFeature('sla');

        $this->escalations->deleteRule($this->escalations->findRule($id));
    }

    public function processEscalations(): int
    {
        $timers = $this->escalations->timersEligibleForEscalation();
        $loggedRuleKeys = $this->escalations->loggedRuleKeysForTimers(
            $timers->pluck('id')->map(fn ($id) => (int) $id)->all(),
        );
        $rulesByPolicy = $this->escalations->activeRulesGroupedByPolicy(
            $timers->pluck('sla_policy_id')->unique()->all(),
        );
        $processed = 0;

        foreach ($timers as $timer) {
            $processed += $this->processTimer(
                $timer,
                $loggedRuleKeys[$timer->id] ?? [],
                $rulesByPolicy->get($timer->sla_policy_id, collect()),
            );
        }

        return $processed;
    }

    public function processTimer(TicketSlaTimer $timer, ?array $loggedRuleKeys = null, ?Collection $rules = null): int
    {
        $processed = 0;
        $rules = $rules ?? $this->escalations->rulesForPolicy($timer->sla_policy_id);

        foreach ($rules as $rule) {
            if ($this->shouldTrigger($timer, $rule, $loggedRuleKeys) && $this->trigger($timer, $rule)) {
                $processed++;
            }
        }

        return $processed;
    }

    private function shouldTrigger(TicketSlaTimer $timer, SlaEscalationRule $rule, ?array $loggedRuleKeys = null): bool
    {
        $ruleKey = SlaEscalationRepository::ruleKey($rule->level, $rule->breach_type);

        if ($loggedRuleKeys !== null) {
            if (isset($loggedRuleKeys[$ruleKey])) {
                return false;
            }
        } elseif ($this->escalations->logExists($timer->id, $rule->level, $rule->breach_type)) {
            return false;
        }

        return match ($rule->breach_type) {
            SlaEscalationRule::BREACH_FIRST_RESPONSE => $this->firstResponseBreached($timer, $rule),
            SlaEscalationRule::BREACH_RESOLUTION => $this->resolutionBreached($timer, $rule),
            default => false,
        };
    }

    private function firstResponseBreached(TicketSlaTimer $timer, SlaEscalationRule $rule): bool
    {
        if ($timer->first_responded_at || ! $timer->first_response_due_at) {
            return false;
        }

        if (! $timer->first_response_breached && now()->lt($timer->first_response_due_at)) {
            return false;
        }

        $breachAt = $timer->first_response_due_at->copy()->addMinutes($rule->delay_minutes_after_breach);

        return now()->gte($breachAt);
    }

    private function resolutionBreached(TicketSlaTimer $timer, SlaEscalationRule $rule): bool
    {
        if ($timer->resolved_at || ! $timer->resolution_due_at) {
            return false;
        }

        if (! $timer->resolution_breached && now()->lt($timer->resolution_due_at)) {
            return false;
        }

        $breachAt = $timer->resolution_due_at->copy()->addMinutes($rule->delay_minutes_after_breach);

        return now()->gte($breachAt);
    }

    private function trigger(TicketSlaTimer $timer, SlaEscalationRule $rule): bool
    {
        $ticket = $timer->ticket;

        if (! $ticket) {
            return false;
        }

        $ticket->loadMissing(['team.lead', 'department.head']);
        $actionsTaken = [];

        foreach ($rule->actions ?? [] as $action) {
            $this->actions->executeOne($ticket, $action);
            $actionsTaken[] = $action;
        }

        $this->escalations->createLog([
            'ticket_id' => $ticket->id,
            'ticket_sla_timer_id' => $timer->id,
            'level' => $rule->level,
            'breach_type' => $rule->breach_type,
            'actions_taken' => $actionsTaken,
            'triggered_at' => now(),
        ]);

        $this->notifications->slaEscalated($ticket, $rule->level, $rule->breach_type);

        return true;
    }
}
