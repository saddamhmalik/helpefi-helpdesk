<?php

namespace Database\Seeders;

use App\Domains\Assets\Models\Asset;
use App\Domains\Assets\Models\AssetType;
use App\Domains\Contacts\Models\Contact;
use Illuminate\Database\Seeder;

class AssetSeeder extends Seeder
{
    public function run(): void
    {
        $types = [
            ['name' => 'Laptop', 'slug' => 'laptop'],
            ['name' => 'Monitor', 'slug' => 'monitor'],
            ['name' => 'Server', 'slug' => 'server'],
            ['name' => 'Mobile device', 'slug' => 'mobile'],
            ['name' => 'Software license', 'slug' => 'software'],
        ];

        foreach ($types as $type) {
            AssetType::query()->firstOrCreate(['slug' => $type['slug']], $type);
        }

        $laptopTypeId = AssetType::query()->where('slug', 'laptop')->value('id');
        $monitorTypeId = AssetType::query()->where('slug', 'monitor')->value('id');
        $contact = Contact::query()->where('email', 'customer@example.com')->first();

        Asset::query()->firstOrCreate(
            ['asset_tag' => 'AST-00001'],
            [
                'asset_type_id' => $laptopTypeId,
                'name' => 'MacBook Pro 14',
                'serial_number' => 'MBP-2024-001',
                'status' => Asset::STATUS_IN_USE,
                'contact_id' => $contact?->id,
                'organization_id' => $contact?->organization_id,
                'location' => 'HQ — Desk 12',
                'purchased_at' => now()->subYear()->toDateString(),
                'warranty_expires_at' => now()->addYear()->toDateString(),
            ],
        );

        Asset::query()->firstOrCreate(
            ['asset_tag' => 'AST-00002'],
            [
                'asset_type_id' => $monitorTypeId,
                'name' => 'Dell UltraSharp 27',
                'serial_number' => 'MON-8842',
                'status' => Asset::STATUS_IN_USE,
                'contact_id' => $contact?->id,
                'organization_id' => $contact?->organization_id,
                'location' => 'HQ — Desk 12',
            ],
        );

        Asset::query()->firstOrCreate(
            ['asset_tag' => 'AST-00003'],
            [
                'asset_type_id' => $laptopTypeId,
                'name' => 'Loaner ThinkPad',
                'serial_number' => 'TP-LOAN-03',
                'status' => Asset::STATUS_IN_STOCK,
                'location' => 'IT Storage',
            ],
        );
    }
}
