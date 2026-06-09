<?php

namespace App\Domains\Tickets\Services;

use App\Domains\Tickets\Repositories\TicketRepository;
use App\Support\CsvStream;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TicketListExportService
{
    public function __construct(private TicketRepository $tickets)
    {
    }

    public function csv(array $filters, int $userId): StreamedResponse
    {
        return CsvStream::download(CsvStream::timestampedFilename('tickets'), function ($handle) use ($filters, $userId) {
            fputcsv($handle, [
                'Number',
                'Subject',
                'Contact',
                'Contact email',
                'Status',
                'Priority',
                'Assignee',
                'Channel',
                'Department',
                'Team',
                'Created',
                'Updated',
                'Closed',
            ]);

            $this->tickets->exportFiltered($filters, $userId, function ($ticket) use ($handle) {
                fputcsv($handle, [
                    $ticket->number,
                    $ticket->subject,
                    $ticket->contact?->name,
                    $ticket->contact?->email,
                    $ticket->status?->name,
                    $ticket->priority?->name,
                    $ticket->assignee?->name,
                    $ticket->channel?->name,
                    $ticket->department?->name,
                    $ticket->team?->name,
                    $ticket->created_at?->toDateTimeString(),
                    $ticket->updated_at?->toDateTimeString(),
                    $ticket->closed_at?->toDateTimeString(),
                ]);
            });
        });
    }
}
