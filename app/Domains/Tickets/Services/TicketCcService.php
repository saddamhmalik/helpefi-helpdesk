<?php

namespace App\Domains\Tickets\Services;

use App\Domains\Tickets\Models\Ticket;
use App\Domains\Tickets\Repositories\TicketCcRepository;

class TicketCcService
{
    public const MAX_CC = 10;

    public function __construct(
        private TicketCcRepository $ccs,
    ) {
    }

    public function sync(Ticket $ticket, ?array $emails, ?int $userId): void
    {
        if ($emails === null) {
            return;
        }

        $this->ccs->syncForTicket(
            $ticket,
            $this->normalize($emails, $ticket->contact?->email),
            $userId,
        );
    }

    public function mergeFromTicket(Ticket $target, Ticket $source, ?int $userId): void
    {
        $this->ccs->mergeFromTicket($target, $source, $userId);
    }

    public function recipientsForTicket(Ticket $ticket): array
    {
        $ticket->loadMissing(['contact', 'ccs']);

        return $this->ccs->listEmailsForTicket($ticket, $ticket->contact?->email);
    }

    public function normalize(array $emails, ?string $requesterEmail = null): array
    {
        $requester = $requesterEmail ? strtolower(trim($requesterEmail)) : null;

        return collect($emails)
            ->map(fn ($email) => strtolower(trim((string) $email)))
            ->filter(fn ($email) => filter_var($email, FILTER_VALIDATE_EMAIL))
            ->when($requester, fn ($collection) => $collection->reject(fn ($email) => $email === $requester))
            ->unique()
            ->take(self::MAX_CC)
            ->values()
            ->all();
    }

    public function mergeFromInbound(Ticket $ticket, array $ccEmails, ?int $userId = null): void
    {
        if ($ccEmails === []) {
            return;
        }

        $existing = $this->ccs->listEmailsForTicket($ticket);
        $merged = collect($existing)
            ->merge($this->normalize($ccEmails, $ticket->contact?->email))
            ->unique()
            ->values()
            ->all();

        $this->ccs->syncForTicket($ticket, $merged, $userId);
    }
}
