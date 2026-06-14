<?php

namespace Database\Seeders;

use App\Domains\Brands\Models\Brand;
use App\Domains\Knowledge\Models\KnowledgeArticle;
use App\Domains\Knowledge\Models\KnowledgeCategory;
use App\Domains\Knowledge\Models\KnowledgeCollection;
use App\Domains\Knowledge\Support\PlatformKnowledge;
use App\Models\User;
use Database\Seeders\Support\PlatformHandbookContent;
use Illuminate\Database\Seeder;

class PlatformHandbookSeeder extends Seeder
{
    public function run(): void
    {
        $authorId = User::query()->role('admin')->orderBy('id')->value('id');
        $brandId = Brand::query()->where('is_default', true)->value('id');

        $categoryIds = [];

        foreach (PlatformKnowledge::HANDBOOK_SECTIONS as $section) {
            $category = KnowledgeCategory::query()->updateOrCreate(
                ['slug' => $section['slug']],
                ['name' => $section['name']],
            );

            $categoryIds[$section['slug']] = $category->id;
        }

        $collection = KnowledgeCollection::query()->updateOrCreate(
            ['slug' => PlatformKnowledge::HANDBOOK_COLLECTION_SLUG],
            [
                'name' => 'How to use helpefi',
                'description' => 'Step-by-step guides from setup to daily agent work. Visible to agents by default; mark individual guides public to share on the customer portal.',
                'sort_order' => 0,
                'is_public' => false,
                'is_system' => true,
                'brand_id' => $brandId,
            ],
        );

        foreach (PlatformHandbookContent::articles($categoryIds, $collection->id, $authorId) as $article) {
            KnowledgeArticle::query()->updateOrCreate(
                ['slug' => $article['slug']],
                $article,
            );
        }
    }
}
