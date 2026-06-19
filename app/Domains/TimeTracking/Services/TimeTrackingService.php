<?php

namespace App\Domains\TimeTracking\Services;

use App\Domains\Tickets\Services\TicketService;
use App\Domains\Security\Support\AuditRecorder;
use App\Domains\TimeTracking\Models\TicketTimeEntry;
use App\Domains\TimeTracking\Repositories\TicketTimeEntryRepository;
use Illuminate\Auth\Access\AuthorizationException;
use InvalidArgumentException;

class TimeTrackingService
{
    public function __construct(
        private TicketTimeEntryRepository $entries,
        private TicketService $tickets,
        private AuditRecorder $audit,
    ) {
    }

    public function snapshotForTicket(int $ticketId): array
    {
        $snapshot = $this->entries->snapshotForTicket($ticketId);

        return [
            'total_minutes' => $snapshot['total_minutes'],
            'entries' => $snapshot['entries'],
        ];
    }

    public function log(int $ticketId, int $userId, int $minutes, ?string $note = null, ?string $loggedAt = null): TicketTimeEntry
    {
        $ticket = $this->tickets->show($ticketId);

        if ($minutes < 1) {
            throw new InvalidArgumentException('Minutes must be at least 1.');
        }

        if ($minutes > 1440) {
            throw new InvalidArgumentException('Minutes cannot exceed 1440.');
        }

        $entry = $this->entries->create([
            'ticket_id' => $ticketId,
            'user_id' => $userId,
            'minutes' => $minutes,
            'note' => $this->normalizeNote($note),
            'logged_at' => $loggedAt ? now()->parse($loggedAt) : now(),
        ]);

        $this->audit->record('ticket.time_logged', $ticket, [
            'entry_id' => $entry->id,
            'minutes' => $minutes,
            'note' => $this->normalizeNote($note),
        ], $userId);

        return $entry;
    }

    public function delete(int $ticketId, int $entryId, int $userId, bool $isAdmin): void
    {
        $entry = $this->entries->findForTicket($ticketId, $entryId);
        $ticket = $this->tickets->show($ticketId);

        if (! $isAdmin && $entry->user_id !== $userId) {
            throw new AuthorizationException('You can only delete your own time entries.');
        }

        $this->audit->record('ticket.time_deleted', $ticket, [
            'entry_id' => $entry->id,
            'minutes' => $entry->minutes,
            'note' => $entry->note,
        ], $userId);

        $this->entries->delete($entry);
    }

    public function report(array $filters): array
    {
        $agents = $this->entries->agentRollup($filters);
        $teams = $this->entries->teamRollup($filters);

        return [
            'type' => 'time_tracking',
            'format' => 'time_tracking',
            'summary' => [
                'total_minutes' => $agents->sum('total_minutes'),
                'entry_count' => $agents->sum('entry_count'),
            ],
            'agents' => $agents->all(),
            'teams' => $teams->all(),
        ];
    }

    private function normalizeNote(?string $note): ?string
    {
        $note = trim((string) $note);

        return $note === '' ? null : $note;
    }
}
