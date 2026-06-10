<?php

namespace App\Domains\Platform\Notifications;

use App\Domains\Platform\Models\PlatformNotice;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class PlatformNoticeNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private PlatformNotice $notice)
    {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'platform_notice',
            'platform_notice_id' => $this->notice->id,
            'title' => $this->notice->title,
            'notice_type' => $this->notice->notice_type,
            'priority' => $this->notice->priority,
            'message' => $this->notice->title,
            'url' => '/dashboard',
        ];
    }
}
