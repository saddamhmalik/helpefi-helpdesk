<?php

namespace App\Domains\Tickets\Services;

use App\Domains\Contacts\Models\Contact;
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
        $logs = $this->auditLogs->forTicket($ticketId, self::EXCLUDED_EVENTS);
        $lookups = $this->buildLookups($logs);

        return $logs
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
            'statuses' => $this->tickets->statuses()->keyBy('id'),
            'priorities' => $this->tickets->priorities()->keyBy('id'),
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
            default => config("audit.events.{$log->event}", str_replace(['ticket.', '_'], ['', ' '], $log->event)),
        };
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
