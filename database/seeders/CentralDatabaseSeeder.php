<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class CentralDatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            PlatformPermissionSeeder::class,
            PlatformUserSeeder::class,
            PlatformEmailTemplateSeeder::class,
        ]);
    }
}
