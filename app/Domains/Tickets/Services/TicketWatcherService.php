<?php

namespace App\Domains\Tickets\Services;

use App\Domains\Security\Support\AuditRecorder;
use App\Domains\Tickets\Models\Ticket;
use App\Domains\Tickets\Models\TicketAttachment;
use App\Domains\Tickets\Repositories\TicketRepository;
use Illuminate\Http\UploadedFile;

class TicketWatcherService
{
    public function __construct(
        private TicketRepository $tickets,
        private TicketAttachmentService $attachments,
        private AuditRecorder $audit,
    ) {
    }

    public function addWatcher(int $id, int $userId): Ticket
    {
        $ticket = $this->tickets->findForWrite($id);
        $this->tickets->addWatcher($ticket, $userId);

        $this->audit->record('ticket.watcher_added', $ticket, [
            'number' => $ticket->number,
            'watcher_id' => $userId,
        ]);

        return $ticket->fresh(['watchers:id,name,email']);
    }

    public function removeWatcher(int $id, int $userId): Ticket
    {
        $ticket = $this->tickets->findForWrite($id);
        $this->tickets->removeWatcher($ticket, $userId);

        $this->audit->record('ticket.watcher_removed', $ticket, [
            'number' => $ticket->number,
            'watcher_id' => $userId,
        ]);

        return $ticket->fresh(['watchers:id,name,email']);
    }

    public function addAttachment(int $id, int $userId, UploadedFile $file): TicketAttachment
    {
        $ticket = $this->tickets->findForWrite($id);
        $attachment = $this->attachments->addToTicket($ticket, $userId, $file);

        $this->audit->record('ticket.attachment_added', $ticket, [
            'number' => $ticket->number,
            'filename' => $attachment->filename,
        ], $userId);

        return $attachment;
    }
}
