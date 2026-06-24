<?php

namespace App\Domains\Automation\Services;

use App\Domains\Automation\Models\AutomationRule;
use App\Domains\Automation\Repositories\AutomationRepository;
use App\Domains\Automation\Repositories\AutomationScheduledActionRepository;
use App\Domains\Billing\Contracts\FeatureEntitlementChecker;
use App\Domains\Security\Support\AuditRecorder;
use App\Domains\Tickets\Models\Ticket;
use App\Domains\Tickets\Repositories\TicketRepository;
use Illuminate\Database\Eloquent\Collection;
use InvalidArgumentException;

class AutomationService
{
    public function __construct(
        private AutomationRepository $rules,
        private AutomationScheduledActionRepository $scheduled,
        private AutomationActionExecutor $executor,
        private TicketRepository $tickets,
        private FeatureEntitlementChecker $entitlements,
        private AuditRecorder $audit,
    ) {
    }

    public function all(): Collection
    {
        return $this->rules->all();
    }

    public function create(array $data): AutomationRule
    {
        $this->entitlements->assertFeature('automation');
        $this->assertValidRule($data);

        $rule = $this->rules->create($data);

        $this->audit->record('automation.created', $rule, [
            'name' => $rule->name,
            'trigger' => $rule->trigger,
        ]);

        return $rule;
    }

    public function update(int $id, array $data): AutomationRule
    {
        $this->assertValidRule($data);

        $rule = $this->rules->update($this->rules->find($id), $data);

        $this->audit->record('automation.updated', $rule, [
            'name' => $rule->name,
            'trigger' => $rule->trigger,
        ]);

        return $rule;
    }

    public function delete(int $id): void
    {
        $rule = $this->rules->find($id);
        $this->rules->delete($rule);

        $this->audit->record('automation.deleted', $rule, [
            'name' => $rule->name,
        ]);
    }

    public function run(Ticket $ticket, string $trigger, array $context = []): void
    {
        $ticket = $this->tickets->find($ticket->id);

        foreach ($this->rules->activeForTrigger($trigger) as $rule) {
            if (! $this->matches($ticket, $rule->conditions ?? [], $context)) {
                continue;
            }

            $this->runActionSequence($ticket, $rule->actions ?? [], $rule->id, $context);
        }
    }

    public function runScheduled(int $scheduledId): void
    {
        $scheduled = $this->scheduled->claimDue($scheduledId);

        if (! $scheduled) {
            return;
        }

        $ticket = $this->tickets->find($scheduled->ticket_id);
        $this->runActionSequence($ticket, $scheduled->actions ?? [], $scheduled->automation_rule_id, $scheduled->context ?? []);
    }

    public function processDueScheduled(): int
    {
        $count = 0;

        foreach ($this->scheduled->due() as $scheduled) {
            $this->runScheduled($scheduled->id);
            $count++;
        }

        return $count;
    }

    public function meta(): array
    {
        return [
            'triggers' => [
                ['value' => AutomationRule::TRIGGER_TICKET_CREATED, 'label' => 'Ticket created'],
                ['value' => AutomationRule::TRIGGER_TICKET_UPDATED, 'label' => 'Ticket updated'],
                ['value' => AutomationRule::TRIGGER_CUSTOMER_MESSAGE, 'label' => 'Customer message received'],
                ['value' => AutomationRule::TRIGGER_APPROVAL_APPROVED, 'label' => 'Approval approved'],
                ['value' => AutomationRule::TRIGGER_APPROVAL_REJECTED, 'label' => 'Approval rejected'],
            ],
            'condition_fields' => [
                ['value' => 'ticket_priority_id', 'label' => 'Priority', 'operators' => ['equals', 'not_equals']],
                ['value' => 'ticket_status_id', 'label' => 'Status', 'operators' => ['equals', 'not_equals']],
                ['value' => 'channel_id', 'label' => 'Channel', 'operators' => ['equals', 'not_equals']],
                ['value' => 'assigned_to', 'label' => 'Assignee', 'operators' => ['equals', 'is_empty', 'is_not_empty']],
                ['value' => 'subject', 'label' => 'Subject', 'operators' => ['contains']],
                ['value' => 'description', 'label' => 'Description', 'operators' => ['contains']],
                ['value' => 'message_body', 'label' => 'Message body', 'operators' => ['contains']],
            ],
            'action_types' => [
                ['value' => 'set_status', 'label' => 'Set status'],
                ['value' => 'set_priority', 'label' => 'Set priority'],
                ['value' => 'assign_to', 'label' => 'Assign to agent'],
                ['value' => 'add_watcher', 'label' => 'Add watcher'],
                ['value' => 'add_internal_note', 'label' => 'Add internal note'],
                ['value' => 'add_tag', 'label' => 'Add tag'],
                ['value' => 'send_webhook', 'label' => 'Send webhook'],
                ['value' => 'delay', 'label' => 'Wait then continue'],
            ],
        ];
    }

    private function runActionSequence(Ticket $ticket, array $actions, ?int $ruleId, array $context): void
    {
        $ticket = $this->tickets->find($ticket->id);

        foreach ($actions as $index => $action) {
            if (($action['type'] ?? '') === 'delay') {
                $remaining = array_slice($actions, $index + 1);

                if ($remaining !== []) {
                    $minutes = max(1, (int) ($action['minutes'] ?? $action['value'] ?? 1));
                    $this->scheduled->schedule(
                        $ticket->id,
                        $ruleId,
                        $remaining,
                        now()->addMinutes($minutes),
                        $context,
                    );
                }

                return;
            }

            $ticket = $this->executor->execute($ticket, $action, $context);
        }
    }

    private function matches(Ticket $ticket, array $conditions, array $context): bool
    {
        foreach ($conditions as $condition) {
            if (! $this->matchesCondition($ticket, $condition, $context)) {
                return false;
            }
        }

        return true;
    }

    private function matchesCondition(Ticket $ticket, array $condition, array $context): bool
    {
        $field = $condition['field'] ?? '';
        $operator = $condition['operator'] ?? 'equals';
        $value = $condition['value'] ?? null;

        $actual = match ($field) {
            'ticket_priority_id' => $ticket->ticket_priority_id,
            'ticket_status_id' => $ticket->ticket_status_id,
            'channel_id' => $ticket->channel_id,
            'assigned_to' => $ticket->assigned_to,
            'subject' => $ticket->subject,
            'description' => $ticket->description,
            'message_body' => $context['message_body'] ?? null,
            default => null,
        };

        return match ($operator) {
            'equals' => (string) $actual === (string) $value,
            'not_equals' => (string) $actual !== (string) $value,
            'contains' => is_string($actual) && is_string($value)
                && str_contains(strtolower($actual), strtolower($value)),
            'is_empty' => blank($actual),
            'is_not_empty' => filled($actual),
            default => false,
        };
    }

    private function assertValidRule(array $data): void
    {
        $validTriggers = [
            AutomationRule::TRIGGER_TICKET_CREATED,
            AutomationRule::TRIGGER_TICKET_UPDATED,
            AutomationRule::TRIGGER_CUSTOMER_MESSAGE,
            AutomationRule::TRIGGER_APPROVAL_APPROVED,
            AutomationRule::TRIGGER_APPROVAL_REJECTED,
        ];

        if (! in_array($data['trigger'] ?? '', $validTriggers, true)) {
            throw new InvalidArgumentException('Invalid automation trigger.');
        }

        if (empty($data['actions']) || ! is_array($data['actions'])) {
            throw new InvalidArgumentException('Automation rule requires at least one action.');
        }

        $validActions = [
            'set_status',
            'set_priority',
            'assign_to',
            'add_watcher',
            'add_internal_note',
            'add_tag',
            'send_webhook',
            'delay',
        ];

        foreach ($data['actions'] as $action) {
            $type = $action['type'] ?? '';

            if (! in_array($type, $validActions, true)) {
                throw new InvalidArgumentException('Invalid automation action.');
            }

            if ($type === 'delay' && max(1, (int) ($action['minutes'] ?? $action['value'] ?? 0)) < 1) {
                throw new InvalidArgumentException('Delay action requires minutes.');
            }

            if ($type === 'send_webhook' && empty($action['value'])) {
                throw new InvalidArgumentException('Webhook action requires a webhook.');
            }

            if ($type === 'add_tag' && blank($action['value'] ?? null)) {
                throw new InvalidArgumentException('Tag action requires a tag name.');
            }
        }
    }
}
