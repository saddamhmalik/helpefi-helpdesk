<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class TenantBootstrapSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            TicketLookupSeeder::class,
            SlaSeeder::class,
            ContactLookupSeeder::class,
            ChannelSeeder::class,
            EmailSeeder::class,
            ServiceCatalogSeeder::class,
            AssetSeeder::class,
            SecuritySeeder::class,
            NotificationSeeder::class,
            CsatSeeder::class,
            PermissionSeeder::class,
        ]);
    }
}
