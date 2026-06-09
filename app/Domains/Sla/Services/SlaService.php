<?php

namespace App\Domains\Sla\Services;

use App\Domains\Notifications\Services\NotificationService;
use App\Domains\Performance\Services\PerformanceService;
use App\Domains\Sla\Models\SlaPolicy;
use App\Domains\Sla\Models\SlaTarget;
use App\Domains\Sla\Models\TicketSlaTimer;
use App\Domains\Sla\Repositories\SlaRepository;
use App\Domains\Security\Support\AuditRecorder;
use App\Domains\Tickets\Models\Ticket;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

class SlaService
{
    public function __construct(
        private SlaRepository $sla,
        private SlaClockService $clock,
        private NotificationService $notifications,
        private AuditRecorder $audit,
        private PerformanceService $performance,
    ) {
    }

    public function policies(): Collection
    {
        return $this->sla->policies();
    }

    public function showPolicy(int $id): SlaPolicy
    {
        return $this->sla->findPolicy($id);
    }

    public function applyToTicket(Ticket $ticket): ?TicketSlaTimer
    {
        if ($this->sla->timerForTicket($ticket->id)) {
            return null;
        }

        $policy = $this->sla->policyForTicket($ticket);

        if (! $policy) {
            return null;
        }

        $target = $this->sla->targetForPolicyAndPriority($policy->id, $ticket->ticket_priority_id);

        if (! $target) {
            return null;
        }

        $start = $ticket->created_at ?? now();
        $businessHours = $policy->businessHours;

        return $this->sla->createTimer([
            'ticket_id' => $ticket->id,
            'sla_policy_id' => $policy->id,
            'first_response_due_at' => $this->clock->addBusinessMinutes($start->copy(), $target->first_response_minutes, $businessHours),
            'resolution_due_at' => $this->clock->addBusinessMinutes($start->copy(), $target->resolution_minutes, $businessHours),
        ]);
    }

    public function recordFirstResponse(Ticket $ticket): void
    {
        $timer = $this->sla->timerForTicket($ticket->id);

        if (! $timer || $timer->first_responded_at) {
            return;
        }

        $this->sla->updateTimer($timer, [
            'first_responded_at' => now(),
            'first_response_breached' => $timer->first_response_due_at && now()->gt($timer->first_response_due_at),
        ]);

        $timer->refresh();
        $assigneeId = $ticket->assigned_to;

        if ($assigneeId) {
            $this->performance->record(
                $assigneeId,
                $timer->first_response_breached ? 'sla_first_response_breach' : 'sla_first_response_met',
                $ticket->id,
            );
        }
    }

    public function recordResolution(Ticket $ticket): void
    {
        $timer = $this->sla->timerForTicket($ticket->id);

        if (! $timer || $timer->resolved_at) {
            return;
        }

        $this->sla->updateTimer($timer, [
            'resolved_at' => now(),
            'resolution_breached' => $timer->resolution_due_at && now()->gt($timer->resolution_due_at),
        ]);

        $timer->refresh();
        $assigneeId = $ticket->assigned_to;

        if ($assigneeId) {
            $this->performance->record(
                $assigneeId,
                $timer->resolution_breached ? 'sla_resolution_breach' : 'sla_resolution_met',
                $ticket->id,
            );
        }
    }

    public function timerForTicket(int $ticketId): ?TicketSlaTimer
    {
        return $this->sla->timerForTicket($ticketId);
    }

    public function checkBreaches(): int
    {
        $breaches = $this->sla->collectBreaches();

        foreach ($breaches as $breach) {
            $this->notifications->slaBreached($breach['ticket'], $breach['type']);

            if ($breach['ticket']->assigned_to) {
                $this->performance->record(
                    $breach['ticket']->assigned_to,
                    $breach['type'] === 'first_response' ? 'sla_first_response_breach' : 'sla_resolution_breach',
                    $breach['ticket']->id,
                );
            }
        }

        return count($breaches);
    }

    public function breachedCount(): int
    {
        return $this->sla->breachedCount();
    }

    public function updateTarget(int $targetId, array $data): SlaTarget
    {
        $target = SlaTarget::query()->findOrFail($targetId);
        $before = $target->only(array_keys($data));
        $target = $this->sla->updateTarget($target, $data);

        $this->audit->recordChanges('sla.target_updated', $target, $before, $target->only(array_keys($data)), [
            'priority_id' => $target->ticket_priority_id,
        ]);

        return $target;
    }

    public function isAgentUser(?User $user): bool
    {
        return $user && $user->hasAnyRole(['admin', 'agent']);
    }

    public function snapshotForTicket(Ticket $ticket): array
    {
        $ticket->loadMissing(['slaTimer.policy.businessHours', 'priority']);

        $timer = $ticket->slaTimer;
        $policy = $timer?->policy ?? $this->sla->policyForTicket($ticket);
        $target = $policy
            ? $this->sla->targetForPolicyAndPriority($policy->id, $ticket->ticket_priority_id)
            : null;

        $policySnapshot = $this->policySnapshot($policy, $target);

        if (! $timer) {
            return [
                'active' => false,
                'policy' => $policySnapshot,
            ];
        }

        return [
            'active' => true,
            'policy' => $policySnapshot,
            'first_response' => $this->milestoneSnapshot(
                $timer->first_response_due_at,
                $timer->first_responded_at,
                $timer->first_response_breached,
                $target?->first_response_minutes,
            ),
            'resolution' => $this->milestoneSnapshot(
                $timer->resolution_due_at,
                $timer->resolved_at,
                $timer->resolution_breached,
                $target?->resolution_minutes,
            ),
        ];
    }

    private function policySnapshot(?SlaPolicy $policy, ?SlaTarget $target): ?array
    {
        if (! $policy) {
            return null;
        }

        $businessHours = $policy->businessHours;

        return [
            'name' => $policy->name,
            'scope' => $this->policyScopeLabel($policy),
            'business_hours' => $businessHours ? [
                'name' => $businessHours->name,
                'timezone' => $businessHours->timezone,
            ] : null,
            'rules' => $target ? [
                [
                    'key' => 'first_response',
                    'label' => 'First response',
                    'minutes' => $target->first_response_minutes,
                    'description' => 'An agent must send the first public reply within '.$this->formatMinutes($target->first_response_minutes).' during business hours.',
                ],
                [
                    'key' => 'resolution',
                    'label' => 'Resolution',
                    'minutes' => $target->resolution_minutes,
                    'description' => 'The ticket must be marked resolved within '.$this->formatMinutes($target->resolution_minutes).' of creation during business hours.',
                ],
            ] : [],
        ];
    }

    private function milestoneSnapshot(
        ?\Illuminate\Support\Carbon $dueAt,
        ?\Illuminate\Support\Carbon $completedAt,
        bool $breached,
        ?int $targetMinutes,
    ): array {
        if ($completedAt) {
            return [
                'status' => $breached ? 'breached' : 'met',
                'due_at' => $dueAt?->toIso8601String(),
                'completed_at' => $completedAt->toIso8601String(),
                'remaining_seconds' => null,
                'progress_percent' => 100,
            ];
        }

        if (! $dueAt) {
            return [
                'status' => 'none',
                'due_at' => null,
                'completed_at' => null,
                'remaining_seconds' => null,
                'progress_percent' => 0,
            ];
        }

        $remainingSeconds = $dueAt->getTimestamp() - now()->getTimestamp();
        $isOverdue = $remainingSeconds < 0;
        $totalSeconds = max(1, ($targetMinutes ?? 0) * 60);
        $elapsedSeconds = max(0, $totalSeconds - max(0, $remainingSeconds));
        $progressPercent = min(100, (int) round(($elapsedSeconds / $totalSeconds) * 100));

        return [
            'status' => $breached || $isOverdue ? 'breached' : 'pending',
            'due_at' => $dueAt->toIso8601String(),
            'completed_at' => null,
            'remaining_seconds' => $remainingSeconds,
            'progress_percent' => $isOverdue ? 100 : $progressPercent,
        ];
    }

    public function meta(): array
    {
        return [
            'customer_tiers' => config('customer_tiers', []),
        ];
    }

    public function createScopedPolicy(string $name, ?int $teamId, ?string $customerTier): SlaPolicy
    {
        $default = $this->sla->defaultPolicy();

        if (! $default) {
            throw new \InvalidArgumentException('Default SLA policy is required before creating scoped policies.');
        }

        if ($teamId && $customerTier) {
            throw new \InvalidArgumentException('Choose either a team or customer tier scope, not both.');
        }

        if (! $teamId && ! $customerTier) {
            throw new \InvalidArgumentException('Team or customer tier scope is required.');
        }

        $policy = $this->sla->createPolicy([
            'name' => $name,
            'is_default' => false,
            'business_hours_id' => $default->business_hours_id,
            'team_id' => $teamId,
            'customer_tier' => $customerTier,
        ]);

        $this->sla->copyTargetsFromPolicy($default, $policy);

        $this->audit->record('sla.policy_created', $policy, [
            'name' => $policy->name,
            'team_id' => $teamId,
            'customer_tier' => $customerTier,
        ]);

        return $this->sla->findPolicy($policy->id);
    }

    public function deletePolicy(int $id): void
    {
        $policy = $this->sla->findPolicy($id);

        if ($policy->is_default) {
            throw new \InvalidArgumentException('The default SLA policy cannot be deleted.');
        }

        $this->sla->deletePolicy($policy);

        $this->audit->record('sla.policy_deleted', $policy, [
            'name' => $policy->name,
        ]);
    }

    private function policyScopeLabel(SlaPolicy $policy): string
    {
        if ($policy->is_default) {
            return 'Default';
        }

        if ($policy->team_id) {
            return 'Team: '.($policy->team?->name ?? 'Unknown');
        }

        if ($policy->customer_tier) {
            $label = collect(config('customer_tiers', []))->firstWhere('value', $policy->customer_tier)['label'] ?? $policy->customer_tier;

            return 'Tier: '.$label;
        }

        return 'Global';
    }

    private function formatMinutes(int $minutes): string
    {
        if ($minutes % 1440 === 0) {
            $days = (int) ($minutes / 1440);

            return $days === 1 ? '1 day' : "{$days} days";
        }

        if ($minutes % 60 === 0) {
            $hours = (int) ($minutes / 60);

            return $hours === 1 ? '1 hour' : "{$hours} hours";
        }

        return $minutes === 1 ? '1 minute' : "{$minutes} minutes";
    }
}
