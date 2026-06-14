<?php

namespace Tests\Feature;

use App\Domains\Knowledge\Support\PlatformKnowledge;
use App\Domains\Settings\Repositories\HelpdeskSettingRepository;
use App\Domains\Tenancy\Services\TenantDummyDataService;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\PlatformHandbookSeeder;
use Database\Seeders\ProductKnowledgeSeeder;
use Database\Seeders\TenantBootstrapSeeder;
use Database\Seeders\TicketLookupSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TenantTestCase;

class PlatformHandbookTest extends TenantTestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([
            PermissionSeeder::class,
            TenantBootstrapSeeder::class,
            TicketLookupSeeder::class,
            PlatformHandbookSeeder::class,
        ]);

        app(HelpdeskSettingRepository::class)->current()->update(['setup_completed_at' => now()]);
    }

    private function admin(): User
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        return $admin;
    }

    public function test_handbook_page_lists_sections_and_articles(): void
    {
        $this->actingAs($this->admin())
            ->tenantGet('/how-to')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Handbook/Index')
                ->has('sections', 8)
                ->where('collection.name', 'How to use helpefi'));
    }

    public function test_handbook_articles_default_to_private_and_can_be_made_public(): void
    {
        $admin = $this->admin();
        $article = \App\Domains\Knowledge\Models\KnowledgeArticle::query()
            ->where('slug', 'handbook-start-here')
            ->firstOrFail();

        $this->assertFalse($article->is_public);

        $this->actingAs($admin)
            ->tenantPut("/knowledge/{$article->id}", ['is_public' => true])
            ->assertRedirect();

        $this->assertTrue($article->fresh()->is_public);
    }

    public function test_system_handbook_survives_bootstrap_demo_removal(): void
    {
        $this->seed(ProductKnowledgeSeeder::class);

        $admin = $this->admin();

        $this->actingAs($admin)
            ->from($this->tenantUrl('/setup'))
            ->tenantDelete('/setup/bootstrap-demo');

        $this->assertFalse(app(TenantDummyDataService::class)->hasBootstrapDemo());

        $this->assertDatabaseHas('knowledge_collections', [
            'slug' => PlatformKnowledge::HANDBOOK_COLLECTION_SLUG,
            'is_system' => true,
        ]);

        foreach (PlatformKnowledge::HANDBOOK_ARTICLE_SLUGS as $slug) {
            $this->assertDatabaseHas('knowledge_articles', [
                'slug' => $slug,
                'is_system' => true,
                'is_public' => false,
            ]);
        }

        $this->assertFalse(
            \App\Domains\Knowledge\Models\KnowledgeArticle::query()
                ->where('slug', 'helpdesk-platform-overview')
                ->exists()
        );
    }

    public function test_system_collection_cannot_be_deleted(): void
    {
        $collection = \App\Domains\Knowledge\Models\KnowledgeCollection::query()
            ->where('slug', PlatformKnowledge::HANDBOOK_COLLECTION_SLUG)
            ->firstOrFail();

        $this->actingAs($this->admin())
            ->from($this->tenantUrl('/knowledge/collections'))
            ->tenantDelete("/knowledge/collections/{$collection->id}")
            ->assertRedirect($this->tenantUrl('/knowledge/collections'))
            ->assertSessionHasErrors('collection');

        $this->assertDatabaseHas('knowledge_collections', [
            'id' => $collection->id,
            'slug' => PlatformKnowledge::HANDBOOK_COLLECTION_SLUG,
        ]);
    }
}
