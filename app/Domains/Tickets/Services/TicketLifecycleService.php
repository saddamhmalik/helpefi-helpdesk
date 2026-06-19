<?php

namespace App\Domains\Tickets\Services;

use App\Domains\Contacts\Models\Contact;
use App\Domains\Tickets\Models\TicketPriority;
use App\Domains\Tickets\Models\TicketStatus;
use App\Domains\Security\Models\AuditLog;
use App\Domains\Security\Repositories\AuditLogRepository;
use App\Domains\Tickets\Repositories\TicketRepository;
use App\Domains\Workforce\Models\Department;
use App\Domains\Workforce\Models\Team;
use App\Models\User;
use Illuminate\Support\Collection;

class TicketLifecycleService
{
    private const EXCLUDED_EVENTS = [
        'ticket.replied',
        'ticket.customer_message',
    ];

    public function __construct(
        private AuditLogRepository $auditLogs,
        private TicketRepository $tickets,
    ) {
    }

    public function timeline(int $ticketId): array
    {
        static $cache = [];

        if (isset($cache[$ticketId])) {
            return $cache[$ticketId];
        }

        $logs = $this->auditLogs->forTicket($ticketId, self::EXCLUDED_EVENTS);
        $lookups = $this->buildLookups($logs);

        return $cache[$ticketId] = $logs
            ->map(fn (AuditLog $log) => $this->formatEntry($log, $lookups))
            ->values()
            ->all();
    }

    private function buildLookups(Collection $logs): array
    {
        $statusIds = [];
        $priorityIds = [];
        $userIds = [];
        $departmentIds = [];
        $teamIds = [];
        $contactIds = [];

        foreach ($logs as $log) {
            $changes = $log->properties['changes'] ?? [];

            foreach ($changes as $field => $change) {
                match ($field) {
                    'ticket_status_id' => array_push($statusIds, $change['from'], $change['to']),
                    'ticket_priority_id' => array_push($priorityIds, $change['from'], $change['to']),
                    'assigned_to' => array_push($userIds, $change['from'], $change['to']),
                    'department_id' => array_push($departmentIds, $change['from'], $change['to']),
                    'team_id' => array_push($teamIds, $change['from'], $change['to']),
                    'contact_id' => array_push($contactIds, $change['from'], $change['to']),
                    default => null,
                };
            }

            if ($log->event === 'ticket.watcher_added' || $log->event === 'ticket.watcher_removed') {
                $userIds[] = $log->properties['watcher_id'] ?? null;
            }

            if ($log->user_id) {
                $userIds[] = $log->user_id;
            }
        }

        $statusIds = array_filter(array_unique($statusIds));
        $priorityIds = array_filter(array_unique($priorityIds));
        $userIds = array_filter(array_unique($userIds));
        $departmentIds = array_filter(array_unique($departmentIds));
        $teamIds = array_filter(array_unique($teamIds));
        $contactIds = array_filter(array_unique($contactIds));

        return [
            'statuses' => $statusIds !== []
                ? TicketStatus::query()->whereIn('id', $statusIds)->get(['id', 'name'])->keyBy('id')
                : collect(),
            'priorities' => $priorityIds !== []
                ? TicketPriority::query()->whereIn('id', $priorityIds)->get(['id', 'name'])->keyBy('id')
                : collect(),
            'users' => $userIds !== []
                ? User::query()->whereIn('id', $userIds)->get(['id', 'name'])->keyBy('id')
                : collect(),
            'departments' => $departmentIds !== []
                ? Department::query()->whereIn('id', $departmentIds)->get(['id', 'name'])->keyBy('id')
                : collect(),
            'teams' => $teamIds !== []
                ? Team::query()->whereIn('id', $teamIds)->get(['id', 'name'])->keyBy('id')
                : collect(),
            'contacts' => $contactIds !== []
                ? Contact::query()->whereIn('id', $contactIds)->get(['id', 'name', 'email'])->keyBy('id')
                : collect(),
        ];
    }

    private function formatEntry(AuditLog $log, array $lookups): array
    {
        return [
            'id' => $log->id,
            'event' => $log->event,
            'description' => $this->describe($log, $lookups),
            'actor' => $log->user?->name ?? $log->actor_email ?? 'System',
            'created_at' => $log->created_at?->toIso8601String(),
        ];
    }

    private function describe(AuditLog $log, array $lookups): string
    {
        $properties = $log->properties ?? [];

        return match ($log->event) {
            'ticket.created' => 'Ticket created',
            'ticket.updated' => $this->describeChanges($properties['changes'] ?? [], $lookups),
            'ticket.watcher_added' => sprintf(
                '%s added as watcher',
                $this->resolveUser($properties['watcher_id'] ?? null, $lookups['users']),
            ),
            'ticket.watcher_removed' => sprintf(
                '%s removed as watcher',
                $this->resolveUser($properties['watcher_id'] ?? null, $lookups['users']),
            ),
            'ticket.merged' => sprintf(
                'Merged ticket %s into this ticket',
                $properties['source_number'] ?? ('#'.($properties['source_id'] ?? 'unknown')),
            ),
            'ticket.split' => sprintf(
                'Split from ticket %s',
                $properties['from_ticket_number'] ?? ('#'.($properties['from_ticket_id'] ?? 'unknown')),
            ),
            'ticket.attachment_added' => sprintf(
                'Attachment added: %s',
                $properties['filename'] ?? 'file',
            ),
            'ticket.reopened_via_email' => sprintf(
                'Ticket reopened via inbound email from %s',
                $properties['from_email'] ?? 'customer',
            ),
            'ticket.snoozed' => sprintf(
                'Ticket snoozed until %s',
                $this->formatTimestamp($properties['snoozed_until'] ?? null),
            ),
            'ticket.unsnoozed' => 'Ticket unsnoozed',
            'ticket.time_logged' => sprintf(
                'Logged %d minutes%s',
                (int) ($properties['minutes'] ?? 0),
                isset($properties['note']) && $properties['note'] !== null && $properties['note'] !== ''
                    ? ': '.$properties['note']
                    : '',
            ),
            'ticket.time_deleted' => sprintf(
                'Removed time entry (%d minutes)',
                (int) ($properties['minutes'] ?? 0),
            ),
            'side_conversation.created' => sprintf(
                'Side conversation started with %s: %s',
                $properties['recipient_email'] ?? 'recipient',
                $properties['subject'] ?? 'Untitled',
            ),
            'side_conversation.replied' => sprintf(
                'Side conversation reply sent: %s',
                $properties['subject'] ?? 'Untitled',
            ),
            'side_conversation.closed' => sprintf(
                'Side conversation closed: %s',
                $properties['subject'] ?? 'Untitled',
            ),
            'side_conversation.inbound' => sprintf(
                'Side conversation reply received from %s: %s',
                $properties['from_email'] ?? 'recipient',
                $properties['subject'] ?? 'Untitled',
            ),
            'service_desk.approval_requested' => sprintf(
                'Approval requested%s',
                isset($properties['approver_names']) && $properties['approver_names'] !== []
                    ? ' from '.implode(', ', $properties['approver_names'])
                    : '',
            ),
            'service_desk.approval_step_approved' => sprintf(
                '%s approved; waiting on %s',
                $properties['approver_name'] ?? 'Approver',
                $properties['next_approver_name'] ?? 'next approver',
            ),
            'service_desk.approval_approved' => sprintf(
                'Approval granted by %s',
                $properties['approver_name'] ?? 'approver',
            ),
            'service_desk.approval_rejected' => sprintf(
                'Approval rejected by %s',
                $properties['approver_name'] ?? 'approver',
            ),
            'service_desk.major_incident_declared' => 'Major incident declared',
            'service_desk.major_incident_updated' => $this->describeRecordChanges(
                $properties['changes'] ?? [],
                [
                    'status' => 'Status',
                    'war_room_notes' => 'War room notes',
                    'summary' => 'Summary',
                    'timeline' => 'Timeline',
                    'lessons_learned' => 'Lessons learned',
                    'action_items' => 'Action items',
                    'coordinator_user_ids' => 'Coordinators',
                ],
            ),
            'service_desk.major_incident_resolved' => 'Major incident resolved',
            'service_desk.major_incident_review_completed' => 'Post-incident review completed',
            'service_desk.change_record_updated' => $this->describeRecordChanges(
                $properties['changes'] ?? [],
                [
                    'risk' => 'Risk',
                    'impact' => 'Impact',
                    'rollback_plan' => 'Rollback plan',
                    'planned_start' => 'Planned start',
                    'planned_end' => 'Planned end',
                    'cab_user_ids' => 'CAB members',
                    'cab_notes' => 'CAB notes',
                    'implementation_notes' => 'Implementation notes',
                ],
            ),
            'service_desk.problem_record_updated' => $this->describeRecordChanges(
                $properties['changes'] ?? [],
                [
                    'root_cause' => 'Root cause',
                    'workaround' => 'Workaround',
                    'is_known_error' => 'Known error flag',
                ],
            ),
            'service_desk.problem_incident_linked' => sprintf(
                'Linked incident %s',
                $properties['incident_number'] ?? '#'.($properties['incident_ticket_id'] ?? 'unknown'),
            ),
            'service_desk.incident_linked_to_problem' => sprintf(
                'Linked to problem %s',
                $properties['problem_number'] ?? '#'.($properties['problem_ticket_id'] ?? 'unknown'),
            ),
            'service_desk.problem_incident_unlinked' => sprintf(
                'Unlinked incident %s',
                $properties['incident_number'] ?? '#'.($properties['incident_ticket_id'] ?? 'unknown'),
            ),
            'service_desk.incident_unlinked_from_problem' => sprintf(
                'Unlinked from problem %s',
                $properties['problem_number'] ?? '#'.($properties['problem_ticket_id'] ?? 'unknown'),
            ),
            default => config("audit.events.{$log->event}", str_replace(['ticket.', '_'], ['', ' '], $log->event)),
        };
    }

    private function describeRecordChanges(array $changes, array $labels): string
    {
        if ($changes === []) {
            return 'Record updated';
        }

        $lines = [];

        foreach ($changes as $field => $change) {
            $label = $labels[$field] ?? ucfirst(str_replace('_', ' ', $field));
            $from = $this->stringifyValue($change['from'] ?? null);
            $to = $this->stringifyValue($change['to'] ?? null);

            if ($field === 'is_known_error') {
                $lines[] = sprintf(
                    '%s changed from %s to %s',
                    $label,
                    $this->formatBoolean($change['from'] ?? null),
                    $this->formatBoolean($change['to'] ?? null),
                );

                continue;
            }

            $lines[] = sprintf('%s updated', $label);
        }

        return implode('; ', $lines);
    }

    private function stringifyValue(mixed $value): string
    {
        if ($value === null || $value === '') {
            return 'None';
        }

        if (is_bool($value)) {
            return $value ? 'Yes' : 'No';
        }

        if (is_array($value)) {
            return $value === [] ? 'None' : implode(', ', $value);
        }

        return (string) $value;
    }

    private function formatTimestamp(?string $value): string
    {
        if (! $value) {
            return 'later';
        }

        return \Illuminate\Support\Carbon::parse($value)->toDayDateTimeString();
    }

    private function formatBoolean(mixed $value): string
    {
        return filter_var($value, FILTER_VALIDATE_BOOLEAN) ? 'Yes' : 'No';
    }

    private function describeChanges(array $changes, array $lookups): string
    {
        if ($changes === []) {
            return 'Ticket updated';
        }

        $lines = [];

        foreach ($changes as $field => $change) {
            $lines[] = match ($field) {
                'ticket_status_id' => sprintf(
                    'Status changed from %s to %s',
                    $this->resolveStatus($change['from'], $lookups['statuses']),
                    $this->resolveStatus($change['to'], $lookups['statuses']),
                ),
                'ticket_priority_id' => sprintf(
                    'Priority changed from %s to %s',
                    $this->resolvePriority($change['from'], $lookups['priorities']),
                    $this->resolvePriority($change['to'], $lookups['priorities']),
                ),
                'assigned_to' => $this->describeAssignment($change, $lookups['users']),
                'department_id' => sprintf(
                    'Department changed from %s to %s',
                    $this->resolveDepartment($change['from'], $lookups['departments']),
                    $this->resolveDepartment($change['to'], $lookups['departments']),
                ),
                'team_id' => sprintf(
                    'Team changed from %s to %s',
                    $this->resolveTeam($change['from'], $lookups['teams']),
                    $this->resolveTeam($change['to'], $lookups['teams']),
                ),
                'contact_id' => sprintf(
                    'Contact changed from %s to %s',
                    $this->resolveContact($change['from'], $lookups['contacts']),
                    $this->resolveContact($change['to'], $lookups['contacts']),
                ),
                'subject' => 'Subject updated',
                'description' => 'Description updated',
                'type' => sprintf(
                    'Type changed from %s to %s',
                    ucfirst((string) ($change['from'] ?? 'unknown')),
                    ucfirst((string) ($change['to'] ?? 'unknown')),
                ),
                default => ucfirst(str_replace('_', ' ', $field)).' updated',
            };
        }

        return implode('; ', $lines);
    }

    private function describeAssignment(array $change, Collection $users): string
    {
        $from = $change['from'] ?? null;
        $to = $change['to'] ?? null;

        if (! $from && $to) {
            return sprintf('Assigned to %s', $this->resolveUser($to, $users));
        }

        if ($from && ! $to) {
            return sprintf('Unassigned from %s', $this->resolveUser($from, $users));
        }

        return sprintf(
            'Assignee changed from %s to %s',
            $this->resolveUser($from, $users),
            $this->resolveUser($to, $users),
        );
    }

    private function resolveStatus(mixed $id, Collection $statuses): string
    {
        if (! $id) {
            return 'None';
        }

        return $statuses->get($id)?->name ?? 'Unknown';
    }

    private function resolvePriority(mixed $id, Collection $priorities): string
    {
        if (! $id) {
            return 'None';
        }

        return $priorities->get($id)?->name ?? 'Unknown';
    }

    private function resolveUser(mixed $id, Collection $users): string
    {
        if (! $id) {
            return 'Unassigned';
        }

        return $users->get($id)?->name ?? 'Unknown';
    }

    private function resolveDepartment(mixed $id, Collection $departments): string
    {
        if (! $id) {
            return 'None';
        }

        return $departments->get($id)?->name ?? 'Unknown';
    }

    private function resolveTeam(mixed $id, Collection $teams): string
    {
        if (! $id) {
            return 'None';
        }

        return $teams->get($id)?->name ?? 'Unknown';
    }

    private function resolveContact(mixed $id, Collection $contacts): string
    {
        if (! $id) {
            return 'None';
        }

        $contact = $contacts->get($id);

        return $contact?->name ?: ($contact?->email ?? 'Unknown');
    }
}
