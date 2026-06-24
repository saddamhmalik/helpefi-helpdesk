<?php

namespace Database\Seeders;

use App\Domains\Channels\Models\Channel;
use App\Domains\Channels\Support\InboundEmailToken;
use Illuminate\Database\Seeder;

class ChannelSeeder extends Seeder
{
    public function run(): void
    {
        $channels = [
            [
                'name' => 'Web',
                'slug' => 'web',
                'type' => Channel::TYPE_WEB,
                'is_active' => true,
                'settings' => [],
            ],
            [
                'name' => 'Customer portal',
                'slug' => 'portal',
                'type' => Channel::TYPE_PORTAL,
                'is_active' => true,
                'settings' => [],
            ],
            [
                'name' => 'Email',
                'slug' => 'email',
                'type' => Channel::TYPE_EMAIL,
                'is_active' => true,
                'settings' => [
                    'address' => 'support@helpdesk.test',
                    'inbound_token' => InboundEmailToken::resolve(),
                ],
            ],
            [
                'name' => 'API',
                'slug' => 'api',
                'type' => Channel::TYPE_API,
                'is_active' => true,
                'settings' => [],
            ],
            [
                'name' => 'Live chat',
                'slug' => 'chat',
                'type' => Channel::TYPE_CHAT,
                'is_active' => true,
                'settings' => [
                    'widget_key' => \Illuminate\Support\Str::random(32),
                    'greeting' => 'Hi! How can we help you today?',
                    'offline_message' => 'We are currently offline. Leave your email and message and we will get back to you.',
                    'offline_mode' => 'business_hours',
                    'allowed_origins' => ['*'],
                ],
            ],
            [
                'name' => 'WhatsApp',
                'slug' => 'whatsapp',
                'type' => Channel::TYPE_WHATSAPP,
                'is_active' => false,
                'settings' => [],
            ],
            [
                'name' => 'SMS',
                'slug' => 'sms',
                'type' => Channel::TYPE_SMS,
                'is_active' => false,
                'settings' => [],
            ],
        ];

        foreach ($channels as $channel) {
            Channel::query()->updateOrCreate(['slug' => $channel['slug']], $channel);
        }
    }
}
