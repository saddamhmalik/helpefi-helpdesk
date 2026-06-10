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

class ApprovalRequestedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private ApprovalRequest $request,
        private string $reviewUrl,
    ) {
    }

    public function via(object $notifiable): array
    {
        return app(NotificationSettingRepository::class)->channelsFor('approval_pending');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'approval_pending',
            'approval_request_id' => $this->request->id,
            'ticket_id' => $this->request->ticket_id,
            'subject' => $this->request->subject,
            'url' => '/service-desk/approvals?mine=1',
            'message' => "Approval required: {$this->request->subject}",
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return app(EmailTemplateService::class)->mailMessage(
            EmailTemplate::SLUG_APPROVAL_REQUESTED,
            [
                'request_subject' => $this->request->subject,
                'action_url' => $this->reviewUrl,
            ],
            fn () => (new MailMessage)
                ->subject("Approval required: {$this->request->subject}")
                ->line('A service desk request is waiting for your approval.')
                ->line($this->request->subject)
                ->action('Review request', $this->reviewUrl),
        );
    }
}
