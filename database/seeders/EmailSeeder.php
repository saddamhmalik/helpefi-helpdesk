<?php

namespace Database\Seeders;

use App\Domains\Channels\Models\EmailInbox;
use App\Domains\Channels\Models\MailSetting;
use App\Domains\Channels\Support\InboundEmailToken;
use App\Domains\Tenancy\Support\BootstrapDemoContent;
use Illuminate\Database\Seeder;

class EmailSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->environment(['local', 'testing'])) {
            EmailInbox::query()->firstOrCreate(
                ['address' => BootstrapDemoContent::DEMO_INBOX_ADDRESS],
                [
                    'name' => 'Support',
                    'inbound_token' => InboundEmailToken::generate(),
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
