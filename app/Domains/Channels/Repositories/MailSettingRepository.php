<?php

namespace App\Domains\Channels\Repositories;

use App\Domains\Channels\Models\MailSetting;

class MailSettingRepository
{
    public function current(): MailSetting
    {
        return MailSetting::query()->firstOrCreate([], [
            'enabled' => false,
            'reply_enabled' => true,
            'delivery_mode' => MailSetting::DELIVERY_QUEUE,
            'queue_connection' => config('queue.default', MailSetting::QUEUE_DATABASE),
            'driver' => 'smtp',
            'from_address' => config('mail.from.address'),
            'from_name' => config('mail.from.name'),
            'host' => config('mail.mailers.smtp.host'),
            'port' => config('mail.mailers.smtp.port'),
            'encryption' => config('mail.mailers.smtp.encryption'),
            'username' => config('mail.mailers.smtp.username'),
        ]);
    }

    public function update(MailSetting $setting, array $data): MailSetting
    {
        if (array_key_exists('password', $data) && ($data['password'] === null || $data['password'] === '')) {
            unset($data['password']);
        }

        $setting->update($data);

        return $setting->fresh();
    }
}
