<?php

namespace App\Domains\Assignment\Services;

use App\Domains\Assignment\Models\AssignmentRule;
use App\Domains\Assignment\Repositories\AssignmentRuleRepository;
use App\Domains\Security\Support\AuditRecorder;
use App\Domains\Tickets\Models\Ticket;
use App\Domains\Workforce\Models\Team;
use Illuminate\Database\Eloquent\Collection;
use InvalidArgumentException;

class AssignmentService
{
    public function __construct(
        private AssignmentRuleRepository $rules,
        private AuditRecorder $audit,
    ) {
    }

    public function all(): Collection
    {
        return $this->rules->all();
    }

    public function meta(): array
    {
        return [
            'strategies' => [
                ['value' => AssignmentRule::STRATEGY_ROUND_ROBIN, 'label' => 'Round robin'],
                ['value' => AssignmentRule::STRATEGY_LOAD_BASED, 'label' => 'Load based'],
            ],
        ];
    }

    public function create(array $data): AssignmentRule
    {
        $data = $this->normalize($data);
        $rule = $this->rules->create($data);

        $this->audit->record('assignment_rule.created', $rule, [
            'name' => $rule->name,
            'strategy' => $rule->strategy,
        ]);

        return $rule;
    }

    public function update(int $id, array $data): AssignmentRule
    {
        $rule = $this->rules->find($id);
        $updated = $this->rules->update($rule, $this->normalize($data, $rule));

        $this->audit->record('assignment_rule.updated', $updated, [
            'name' => $updated->name,
            'strategy' => $updated->strategy,
        ]);

        return $updated;
    }

    public function delete(int $id): void
    {
        $rule = $this->rules->find($id);

        $this->rules->delete($rule);

        $this->audit->record('assignment_rule.deleted', $rule, [
            'name' => $rule->name,
        ]);
    }

    public function enrichUnassignedTicket(array $data, ?Ticket $ticket = null): array
    {
        if (! empty($data['assigned_to'])) {
            return $data;
        }

        if ($ticket && ! array_key_exists('assigned_to', $data) && $ticket->assigned_to) {
            return $data;
        }

        $context = array_merge(
            $ticket?->only(['team_id', 'department_id', 'channel_id', 'ticket_priority_id']) ?? [],
            $data,
        );

        foreach ($this->rules->activeOrdered() as $rule) {
            if (! $this->matches($rule, $context)) {
                continue;
            }

            $assigneeId = $this->pickAssignee($rule);

            if (! $assigneeId) {
                continue;
            }

            $data['assigned_to'] = $assigneeId;

            if (empty($data['team_id']) && $rule->team_id) {
                $data['team_id'] = $rule->team_id;
            }

            if (empty($data['department_id'])) {
                if ($rule->department_id) {
                    $data['department_id'] = $rule->department_id;
                } elseif ($rule->team_id) {
                    $data['department_id'] = Team::query()->whereKey($rule->team_id)->value('department_id');
                }
            }

            return $data;
        }

        return $data;
    }

    private function matches(AssignmentRule $rule, array $context): bool
    {
        $channelIds = $rule->channel_ids ?? [];

        if ($channelIds !== [] && ! in_array((int) ($context['channel_id'] ?? 0), array_map('intval', $channelIds), true)) {
            return false;
        }

        if ($rule->team_id && ! empty($context['team_id']) && (int) $context['team_id'] !== (int) $rule->team_id) {
            return false;
        }

        if ($rule->department_id && ! empty($context['department_id']) && (int) $context['department_id'] !== (int) $rule->department_id) {
            return false;
        }

        if ($rule->ticket_priority_id && (int) ($context['ticket_priority_id'] ?? 0) !== (int) $rule->ticket_priority_id) {
            return false;
        }

        return true;
    }

    private function pickAssignee(AssignmentRule $rule): ?int
    {
        $candidates = $this->rules->candidateAgentIds($rule);

        if ($candidates === []) {
            return null;
        }

        $assigneeId = match ($rule->strategy) {
            AssignmentRule::STRATEGY_LOAD_BASED => $this->pickByLoad($candidates),
            default => $this->pickRoundRobin($rule, $candidates),
        };

        if (! $assigneeId) {
            return null;
        }

        $this->rules->updateLastAssigned($rule, $assigneeId);

        return $assigneeId;
    }

    private function pickRoundRobin(AssignmentRule $rule, array $candidates): ?int
    {
        $lastId = $rule->last_assigned_user_id;
        $index = $lastId ? array_search($lastId, $candidates, true) : false;
        $nextIndex = $index === false ? 0 : ($index + 1) % count($candidates);

        return $candidates[$nextIndex] ?? null;
    }

    private function pickByLoad(array $candidates): ?int
    {
        $counts = $this->rules->openTicketCountsForAgents($candidates);
        $bestId = null;
        $bestCount = null;

        foreach ($candidates as $candidateId) {
            $count = $counts[$candidateId] ?? 0;

            if ($bestCount === null || $count < $bestCount || ($count === $bestCount && $candidateId < $bestId)) {
                $bestId = $candidateId;
                $bestCount = $count;
            }
        }

        return $bestId;
    }

    private function normalize(array $data, ?AssignmentRule $existing = null): array
    {
        if (empty($data['name'])) {
            throw new InvalidArgumentException('Rule name is required.');
        }

        $validStrategies = [
            AssignmentRule::STRATEGY_ROUND_ROBIN,
            AssignmentRule::STRATEGY_LOAD_BASED,
        ];

        if (! in_array($data['strategy'] ?? '', $validStrategies, true)) {
            throw new InvalidArgumentException('Invalid assignment strategy.');
        }

        if (! empty($data['team_id']) && ! empty($data['department_id'])) {
            $teamDepartmentId = Team::query()->whereKey($data['team_id'])->value('department_id');

            if ((int) $teamDepartmentId !== (int) $data['department_id']) {
                throw new InvalidArgumentException('Selected team does not belong to the department.');
            }
        }

        $channelIds = collect($data['channel_ids'] ?? [])
            ->filter(fn ($id) => filled($id))
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();

        $data['channel_ids'] = $channelIds === [] ? null : $channelIds;
        $data['team_id'] = filled($data['team_id'] ?? null) ? (int) $data['team_id'] : null;
        $data['department_id'] = filled($data['department_id'] ?? null) ? (int) $data['department_id'] : null;
        $data['ticket_priority_id'] = filled($data['ticket_priority_id'] ?? null) ? (int) $data['ticket_priority_id'] : null;
        $data['skill_ids'] = collect($data['skill_ids'] ?? [])
            ->filter(fn ($id) => filled($id))
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();
        $data['skill_ids'] = $data['skill_ids'] === [] ? null : $data['skill_ids'];

        if ($existing && ! array_key_exists('last_assigned_user_id', $data)) {
            unset($data['last_assigned_user_id']);
        }

        return $data;
    }
}
