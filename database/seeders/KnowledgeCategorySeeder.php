<?php

namespace Database\Seeders;

use App\Domains\Knowledge\Models\KnowledgeCategory;
use Illuminate\Database\Seeder;

class KnowledgeCategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            ['name' => 'Getting Started', 'slug' => 'getting-started'],
            ['name' => 'Account & Billing', 'slug' => 'account-billing'],
            ['name' => 'Troubleshooting', 'slug' => 'troubleshooting'],
        ];

        foreach ($categories as $category) {
            KnowledgeCategory::query()->updateOrCreate(['slug' => $category['slug']], $category);
        }
    }
}
