<?php

namespace App\Domains\Notifications\Observers;

use App\Domains\Notifications\Services\NotificationService;
use App\Domains\Tickets\Models\Ticket;
use Illuminate\Support\Facades\Auth;

class TicketNotificationObserver
{
    public function __construct(private NotificationService $notifications)
    {
    }

    public function created(Ticket $ticket): void
    {
        if ($ticket->assigned_to) {
            $this->notifications->ticketAssigned($ticket, Auth::id());
        }
    }

    public function updated(Ticket $ticket): void
    {
        if ($ticket->wasChanged('assigned_to')) {
            $this->notifications->ticketAssigned($ticket, Auth::id());
        }
    }
}
