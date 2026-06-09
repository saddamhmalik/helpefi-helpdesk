<?php

namespace Database\Seeders;

use App\Domains\Brands\Models\Brand;
use App\Domains\Knowledge\Models\KnowledgeCollection;
use Illuminate\Database\Seeder;

class KnowledgeCollectionSeeder extends Seeder
{
    public function run(): void
    {
        $brandId = Brand::query()->where('is_default', true)->value('id');

        $collections = [
            [
                'name' => 'Getting Started',
                'slug' => 'getting-started',
                'description' => 'Basics for new users',
                'sort_order' => 1,
                'is_public' => true,
            ],
            [
                'name' => 'Account Help',
                'slug' => 'account-help',
                'description' => 'Login, password, and account settings',
                'sort_order' => 2,
                'is_public' => true,
            ],
        ];

        foreach ($collections as $collection) {
            KnowledgeCollection::query()->updateOrCreate(['slug' => $collection['slug']], array_merge($collection, [
                'brand_id' => $brandId,
            ]));
        }
    }
}
