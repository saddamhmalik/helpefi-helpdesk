<?php

namespace App\Domains\Notifications\Notifications;

use App\Domains\Notifications\Repositories\NotificationSettingRepository;
use App\Domains\Tickets\Models\Ticket;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class CustomerReplyNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private Ticket $ticket,
        private string $preview,
    ) {
    }

    public function via(object $notifiable): array
    {
        return app(NotificationSettingRepository::class)->channelsFor('customer_reply');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'customer_reply',
            'ticket_id' => $this->ticket->id,
            'ticket_number' => $this->ticket->number,
            'subject' => $this->ticket->subject,
            'url' => '/tickets/'.$this->ticket->id,
            'message' => "Customer replied on {$this->ticket->number}: {$this->preview}",
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Customer reply: {$this->ticket->number}")
            ->line("A customer replied on {$this->ticket->number}.")
            ->line($this->preview)
            ->action('View ticket', url('/tickets/'.$this->ticket->id));
    }
}
