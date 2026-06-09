<?php

namespace Database\Seeders;

use App\Domains\Billing\Models\Subscription;
use Illuminate\Database\Seeder;

class BillingSeeder extends Seeder
{
    public function run(): void
    {
        Subscription::query()->firstOrCreate([], [
            'plan' => config('billing.default_plan', 'professional'),
            'status' => Subscription::STATUS_ACTIVE,
            'renews_at' => now()->addMonth(),
        ]);
    }
}
