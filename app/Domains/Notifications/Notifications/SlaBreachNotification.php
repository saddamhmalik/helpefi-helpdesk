<?php

namespace App\Domains\Notifications\Notifications;

use App\Domains\Channels\Models\EmailTemplate;
use App\Domains\Channels\Services\EmailTemplateService;
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
        $label = $this->breachLabel();

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
        $label = $this->breachLabel();

        return app(EmailTemplateService::class)->mailMessage(
            EmailTemplate::SLUG_SLA_BREACH,
            [
                'breach_label' => $label,
                'ticket_number' => $this->ticket->number,
                'ticket_subject' => $this->ticket->subject,
                'action_url' => url('/tickets/'.$this->ticket->id),
            ],
            fn () => (new MailMessage)
                ->subject("SLA breach: {$this->ticket->number}")
                ->line("{$label} on ticket {$this->ticket->number}.")
                ->line($this->ticket->subject)
                ->action('View ticket', url('/tickets/'.$this->ticket->id)),
        );
    }

    private function breachLabel(): string
    {
        if (preg_match('/^(.+)_escalation_l(\d+)$/', $this->breachType, $matches)) {
            $base = $matches[1] === 'first_response' ? 'First response SLA' : 'Resolution SLA';

            return "{$base} escalation (level {$matches[2]})";
        }

        return match ($this->breachType) {
            'first_response' => 'First response SLA breached',
            'resolution' => 'Resolution SLA breached',
            default => 'SLA breached',
        };
    }
}
