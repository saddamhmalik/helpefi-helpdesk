<?php

namespace App\Domains\ServiceDesk\Notifications;

use App\Domains\Channels\Models\EmailTemplate;
use App\Domains\Channels\Services\EmailTemplateService;
use App\Domains\Notifications\Repositories\NotificationSettingRepository;
use App\Domains\ServiceDesk\Models\ApprovalRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ApprovalDecidedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private ApprovalRequest $request,
        private bool $approved,
    ) {
    }

    public function via(object $notifiable): array
    {
        return app(NotificationSettingRepository::class)->channelsFor('approval_pending');
    }

    public function toArray(object $notifiable): array
    {
        $verb = $this->approved ? 'approved' : 'rejected';

        return [
            'type' => 'approval_decided',
            'approval_request_id' => $this->request->id,
            'ticket_id' => $this->request->ticket_id,
            'subject' => $this->request->subject,
            'url' => '/tickets/'.$this->request->ticket_id,
            'message' => "Your request was {$verb}: {$this->request->subject}",
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $verb = $this->approved ? 'approved' : 'rejected';

        return app(EmailTemplateService::class)->mailMessage(
            EmailTemplate::SLUG_APPROVAL_DECIDED,
            [
                'decision' => $verb,
                'request_subject' => $this->request->subject,
                'action_url' => url('/tickets/'.$this->request->ticket_id),
            ],
            fn () => (new MailMessage)
                ->subject("Request {$verb}: {$this->request->subject}")
                ->line("Your service desk request was {$verb}.")
                ->line($this->request->subject)
                ->action('View ticket', url('/tickets/'.$this->request->ticket_id)),
        );
    }
}
