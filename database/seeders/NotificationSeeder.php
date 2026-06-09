<?php

namespace Database\Seeders;

use App\Domains\Notifications\Models\NotificationSetting;
use Illuminate\Database\Seeder;

class NotificationSeeder extends Seeder
{
    public function run(): void
    {
        NotificationSetting::query()->firstOrCreate([], [
            'email_enabled' => false,
            'notify_ticket_assigned' => true,
            'notify_customer_reply' => true,
            'notify_sla_breach' => true,
        ]);
    }
}
