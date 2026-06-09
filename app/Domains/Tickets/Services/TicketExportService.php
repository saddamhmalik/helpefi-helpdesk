<?php

namespace App\Domains\Tickets\Services;

use App\Domains\Tickets\Jobs\SendTicketExportJob;
use App\Domains\Tickets\Models\Ticket;
use App\Domains\Tickets\Repositories\TicketRepository;
use App\Domains\Tickets\Support\MessageBodySanitizer;
use Barryvdh\DomPDF\Facade\Pdf;

class TicketExportService
{
    public function __construct(
        private TicketRepository $tickets,
        private TicketLifecycleService $lifecycle,
    ) {
    }

    public function pdf(int $ticketId, bool $includeConversation = true): \Barryvdh\DomPDF\PDF
    {
        $ticket = $this->tickets->find($ticketId);
        $payload = $this->buildPayload($ticket, $includeConversation);

        return Pdf::loadView('tickets.export-pdf', $payload)
            ->setPaper('a4');
    }

    public function filename(Ticket $ticket): string
    {
        return str_replace(['/', '\\'], '-', $ticket->number).'.pdf';
    }

    public function queueEmail(int $ticketId, string $email, int $userId, bool $includeConversation = true): void
    {
        SendTicketExportJob::dispatch($ticketId, $email, $userId, $includeConversation);
    }

    public function buildPayload(Ticket $ticket, bool $includeConversation): array
    {
        $messages = $includeConversation
            ? $ticket->messages->map(fn ($message) => [
                'author' => $message->user?->name
                    ?? $message->contact?->name
                    ?? $message->contact?->email
                    ?? 'Unknown',
                'body' => MessageBodySanitizer::toPlainText($message->body),
                'is_internal' => $message->is_internal,
                'created_at' => $message->created_at?->format('M j, Y g:i A'),
            ])->all()
            : [];

        return [
            'ticket' => $ticket,
            'messages' => $messages,
            'lifecycle' => $this->lifecycle->timeline($ticket->id),
            'includeConversation' => $includeConversation,
            'exportedAt' => now()->format('M j, Y g:i A T'),
        ];
    }
}
