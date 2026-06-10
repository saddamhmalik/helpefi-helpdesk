<?php

namespace App\Domains\Notifications\Listeners;

use App\Domains\Notifications\Services\NotificationService;
use App\Domains\Realtime\Services\RealtimePublisher;
use App\Models\User;
use Illuminate\Notifications\Events\NotificationSent;

class PublishAgentNotificationRealtime
{
    public function __construct(
        private NotificationService $notifications,
        private RealtimePublisher $realtime,
    ) {
    }

    public function handle(NotificationSent $event): void
    {
        if ($event->channel !== 'database' || ! $event->notifiable instanceof User) {
            return;
        }

        $record = $event->response;

        if (! $record) {
            return;
        }

        $this->realtime->notificationCreated(
            $event->notifiable->id,
            $this->notifications->formatNotification($record),
        );
    }
}
