<?php

namespace Database\Seeders;

use App\Domains\Security\Models\SecuritySetting;
use Illuminate\Database\Seeder;

class SecuritySeeder extends Seeder
{
    public function run(): void
    {
        SecuritySetting::query()->firstOrCreate([], [
            'mfa_required_for_agents' => false,
            'audit_retention_days' => 90,
            'closed_ticket_retention_days' => null,
        ]);
    }
}
