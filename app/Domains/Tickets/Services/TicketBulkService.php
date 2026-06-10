<?php

namespace App\Domains\Tickets\Services;

use App\Domains\Tickets\Repositories\TicketBulkRepository;
use Illuminate\Support\Carbon;
use InvalidArgumentException;

class TicketBulkService
{
    public function __construct(
        private TicketBulkRepository $bulk,
        private TicketService $tickets,
        private TicketSnoozeService $snooze,
    ) {
    }

    public function execute(array $ticketIds, string $action, array $payload, int $userId): array
    {
        $tickets = $this->bulk->findByIds($ticketIds);
        $updated = 0;
        $failed = [];

        foreach ($tickets as $ticket) {
            try {
                match ($action) {
                    'assign' => $this->tickets->update($ticket->id, [
                        'assigned_to' => $payload['assigned_to'] ?? null,
                    ], $userId),
                    'status' => $this->tickets->update($ticket->id, [
                        'ticket_status_id' => $payload['ticket_status_id'],
                    ], $userId),
                    'priority' => $this->tickets->update($ticket->id, [
                        'ticket_priority_id' => $payload['ticket_priority_id'],
                    ], $userId),
                    'close' => $this->tickets->update($ticket->id, [
                        'ticket_status_id' => $this->bulk->closedStatus()->id,
                    ], $userId),
                    'snooze' => $this->snooze->snooze(
                        $ticket->id,
                        $this->resolveSnoozeUntil($payload),
                        $userId,
                    ),
                    default => throw new InvalidArgumentException('Unsupported bulk action.'),
                };

                $updated++;
            } catch (\Throwable $exception) {
                $failed[] = [
                    'id' => $ticket->id,
                    'number' => $ticket->number,
                    'error' => $exception->getMessage(),
                ];
            }
        }

        return [
            'requested' => count($ticketIds),
            'matched' => $tickets->count(),
            'updated' => $updated,
            'failed' => $failed,
        ];
    }

    private function resolveSnoozeUntil(array $payload): Carbon
    {
        if (isset($payload['until'])) {
            return Carbon::parse($payload['until']);
        }

        if (isset($payload['minutes'])) {
            return now()->addMinutes((int) $payload['minutes']);
        }

        throw new InvalidArgumentException('Snooze requires minutes or until.');
    }
}
