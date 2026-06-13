<?php

namespace Database\Seeders;

use App\Domains\Channels\Models\EmailInbox;
use App\Domains\Channels\Models\MailSetting;
use Illuminate\Database\Seeder;

class EmailSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->environment(['local', 'testing'])) {
            EmailInbox::query()->firstOrCreate(
                ['address' => 'support@helpdesk.test'],
                [
                    'name' => 'Support',
                    'inbound_token' => config('helpdesk.inbound_email_token') ?: 'dev-inbound-token',
                    'is_active' => true,
                ],
            );
        }

        MailSetting::query()->firstOrCreate([], [
            'enabled' => false,
            'reply_enabled' => true,
            'delivery_mode' => MailSetting::DELIVERY_QUEUE,
            'driver' => 'log',
            'from_address' => config('mail.from.address'),
            'from_name' => config('mail.from.name'),
            'queue_connection' => MailSetting::QUEUE_REDIS,
        ]);
    }
}
