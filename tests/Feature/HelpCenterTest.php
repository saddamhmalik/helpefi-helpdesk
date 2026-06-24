<?php

namespace Tests\Feature;

use App\Domains\Knowledge\Models\KnowledgeArticle;
use App\Domains\Knowledge\Models\KnowledgeCollection;
use Database\Seeders\ChannelSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\SlaSeeder;
use Database\Seeders\TenantBootstrapSeeder;
use Database\Seeders\TicketLookupSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TenantTestCase;

class HelpCenterTest extends TenantTestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([
            PermissionSeeder::class,
            TicketLookupSeeder::class,
            ChannelSeeder::class,
            SlaSeeder::class,
            TenantBootstrapSeeder::class,
        ]);
    }

    public function test_new_tenant_starts_without_seeded_knowledge_base(): void
    {
        $this->assertSame(0, KnowledgeArticle::query()->count());
        $this->assertSame(0, KnowledgeCollection::query()->count());
    }

    public function test_login_page_includes_help_center_links(): void
    {
        $this->tenantGet('/login')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('helpCenter.brandSlug')
                ->has('helpCenter.homeUrl')
                ->has('helpCenter.searchUrl')
                ->has('helpCenter.trackUrl')
                ->has('helpCenter.loginUrl')
                ->has('helpCenter.registerUrl')
                ->has('helpCenter.submitUrl')
                ->has('helpCenter.articleCount')
            );
    }

    public function test_portal_home_is_public_for_default_brand(): void
    {
        $this->tenantGet('/portal/default')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Portal/Index'));
    }

    public function test_unscoped_portal_track_url_is_rewritten_to_default_brand(): void
    {
        $this->tenantGet('/portal/track')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Portal/Track'));
    }
}
