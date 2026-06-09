<?php

namespace App\Domains\Notifications\Notifications;

use App\Domains\Notifications\Repositories\NotificationSettingRepository;
use App\Domains\Tickets\Models\Ticket;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SlaBreachNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private Ticket $ticket,
        private string $breachType,
    ) {
    }

    public function via(object $notifiable): array
    {
        return app(NotificationSettingRepository::class)->channelsFor('sla_breach');
    }

    public function toArray(object $notifiable): array
    {
        $label = $this->breachType === 'first_response' ? 'First response SLA breached' : 'Resolution SLA breached';

        return [
            'type' => 'sla_breach',
            'breach_type' => $this->breachType,
            'ticket_id' => $this->ticket->id,
            'ticket_number' => $this->ticket->number,
            'subject' => $this->ticket->subject,
            'url' => '/tickets/'.$this->ticket->id,
            'message' => "{$label} on {$this->ticket->number}: {$this->ticket->subject}",
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $label = $this->breachType === 'first_response' ? 'First response SLA breached' : 'Resolution SLA breached';

        return (new MailMessage)
            ->subject("SLA breach: {$this->ticket->number}")
            ->line("{$label} on ticket {$this->ticket->number}.")
            ->line($this->ticket->subject)
            ->action('View ticket', url('/tickets/'.$this->ticket->id));
    }
}
