<?php

namespace App\Domains\Notifications\Repositories;

use App\Domains\Notifications\Models\NotificationSetting;

class NotificationSettingRepository
{
    public function current(): NotificationSetting
    {
        return NotificationSetting::query()->firstOrCreate([], [
            'email_enabled' => false,
            'notify_ticket_assigned' => true,
            'notify_customer_reply' => true,
            'notify_sla_breach' => true,
            'notify_approval_pending' => true,
        ]);
    }

    public function update(NotificationSetting $setting, array $data): NotificationSetting
    {
        $setting->update($data);

        return $setting->fresh();
    }

    public function channelsFor(string $event): array
    {
        $setting = $this->current();

        $enabled = match ($event) {
            'ticket_assigned' => $setting->notify_ticket_assigned,
            'customer_reply' => $setting->notify_customer_reply,
            'sla_breach' => $setting->notify_sla_breach,
            'approval_pending' => $setting->notify_approval_pending,
            default => false,
        };

        if (! $enabled) {
            return [];
        }

        $channels = ['database'];

        if ($setting->email_enabled) {
            $channels[] = 'mail';
        }

        return $channels;
    }
}
