<?php

namespace Database\Seeders;

use App\Domains\Tenancy\Models\CentralSetting;
use Illuminate\Database\Seeder;

class MarketingDefaultsSeeder extends Seeder
{
    public function run(): void
    {
        $setting = CentralSetting::query()->first();

        if ($setting === null) {
            return;
        }

        if ($setting->testimonials_enabled) {
            $setting->update(['testimonials_enabled' => false]);
        }
    }
}
