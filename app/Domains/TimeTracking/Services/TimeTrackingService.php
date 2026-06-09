<?php

namespace App\Domains\TimeTracking\Services;

use App\Domains\Tickets\Services\TicketService;
use App\Domains\TimeTracking\Models\TicketTimeEntry;
use App\Domains\TimeTracking\Repositories\TicketTimeEntryRepository;
use Illuminate\Auth\Access\AuthorizationException;
use InvalidArgumentException;

class TimeTrackingService
{
    public function __construct(
        private TicketTimeEntryRepository $entries,
        private TicketService $tickets,
    ) {
    }

    public function snapshotForTicket(int $ticketId): array
    {
        $entries = $this->entries->forTicket($ticketId);

        return [
            'total_minutes' => $this->entries->totalMinutesForTicket($ticketId),
            'entries' => $entries->map(fn (TicketTimeEntry $entry) => [
                'id' => $entry->id,
                'minutes' => $entry->minutes,
                'note' => $entry->note,
                'logged_at' => $entry->logged_at?->toIso8601String(),
                'user' => $entry->user ? [
                    'id' => $entry->user->id,
                    'name' => $entry->user->name,
                ] : null,
            ])->values()->all(),
        ];
    }

    public function log(int $ticketId, int $userId, int $minutes, ?string $note = null, ?string $loggedAt = null): TicketTimeEntry
    {
        $this->tickets->show($ticketId);

        if ($minutes < 1) {
            throw new InvalidArgumentException('Minutes must be at least 1.');
        }

        if ($minutes > 1440) {
            throw new InvalidArgumentException('Minutes cannot exceed 1440.');
        }

        return $this->entries->create([
            'ticket_id' => $ticketId,
            'user_id' => $userId,
            'minutes' => $minutes,
            'note' => $this->normalizeNote($note),
            'logged_at' => $loggedAt ? now()->parse($loggedAt) : now(),
        ]);
    }

    public function delete(int $ticketId, int $entryId, int $userId, bool $isAdmin): void
    {
        $entry = $this->entries->findForTicket($ticketId, $entryId);

        if (! $isAdmin && $entry->user_id !== $userId) {
            throw new AuthorizationException('You can only delete your own time entries.');
        }

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
