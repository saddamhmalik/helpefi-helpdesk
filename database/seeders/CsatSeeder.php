<?php

namespace Database\Seeders;

use App\Domains\Csat\Models\CsatSetting;
use Illuminate\Database\Seeder;

class CsatSeeder extends Seeder
{
    public function run(): void
    {
        CsatSetting::query()->firstOrCreate([], [
            'enabled' => true,
            'comment_required' => false,
            'email_enabled' => false,
        ]);
    }
}
