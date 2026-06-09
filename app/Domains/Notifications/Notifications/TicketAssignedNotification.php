<?php

namespace App\Domains\Notifications\Notifications;

use App\Domains\Notifications\Repositories\NotificationSettingRepository;
use App\Domains\Tickets\Models\Ticket;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TicketAssignedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private Ticket $ticket)
    {
    }

    public function via(object $notifiable): array
    {
        return app(NotificationSettingRepository::class)->channelsFor('ticket_assigned');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'ticket_assigned',
            'ticket_id' => $this->ticket->id,
            'ticket_number' => $this->ticket->number,
            'subject' => $this->ticket->subject,
            'url' => '/tickets/'.$this->ticket->id,
            'message' => "You were assigned to {$this->ticket->number}: {$this->ticket->subject}",
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Assigned: {$this->ticket->number}")
            ->line("You were assigned to ticket {$this->ticket->number}.")
            ->line($this->ticket->subject)
            ->action('View ticket', url('/tickets/'.$this->ticket->id));
    }
}
