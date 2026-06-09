<?php

namespace App\Domains\Tickets\Repositories;

use App\Domains\Contacts\Models\Contact;
use App\Domains\Tickets\Models\Ticket;
use App\Domains\Tickets\Models\TicketCc;
use Illuminate\Support\Collection;

class TicketCcRepository
{
    public function syncForTicket(Ticket $ticket, array $emails, ?int $addedBy): void
    {
        $normalized = collect($emails)
            ->map(fn ($email) => strtolower(trim((string) $email)))
            ->filter(fn ($email) => filter_var($email, FILTER_VALIDATE_EMAIL))
            ->unique()
            ->values();

        $keep = $normalized->all();

        if ($keep === []) {
            $ticket->ccs()->delete();

            return;
        }

        $ticket->ccs()->whereNotIn('email', $keep)->delete();

        $contactsByEmail = Contact::query()
            ->whereIn('email', $keep)
            ->pluck('id', 'email');

        foreach ($keep as $email) {
            TicketCc::query()->updateOrCreate(
                [
                    'ticket_id' => $ticket->id,
                    'email' => $email,
                ],
                [
                    'contact_id' => $contactsByEmail->get($email),
                    'added_by' => $addedBy,
                ],
            );
        }
    }

    public function listEmailsForTicket(Ticket $ticket, ?string $excludeEmail = null): array
    {
        $exclude = $excludeEmail ? strtolower(trim($excludeEmail)) : null;

        return $ticket->ccs()
            ->pluck('email')
            ->map(fn ($email) => strtolower(trim($email)))
            ->when($exclude, fn (Collection $emails) => $emails->reject(fn ($email) => $email === $exclude))
            ->unique()
            ->values()
            ->all();
    }

    public function mergeFromTicket(Ticket $target, Ticket $source, ?int $addedBy): void
    {
        $emails = collect($this->listEmailsForTicket($target))
            ->merge($this->listEmailsForTicket($source))
            ->unique()
            ->values()
            ->all();

        $this->syncForTicket($target, $emails, $addedBy);
    }
}
