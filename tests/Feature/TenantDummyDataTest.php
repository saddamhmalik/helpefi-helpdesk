<?php

namespace Tests\Feature;

use App\Domains\Assets\Models\Asset;
use App\Domains\Channels\Models\Channel;
use App\Domains\Channels\Models\EmailInbox;
use App\Domains\Contacts\Models\Contact;
use App\Domains\Contacts\Models\Organization;
use App\Domains\Contacts\Models\Tag;
use App\Domains\Knowledge\Models\KnowledgeArticle;
use App\Domains\Knowledge\Models\KnowledgeCategory;
use App\Domains\Knowledge\Models\KnowledgeCollection;
use App\Domains\ServiceCatalog\Models\ServiceCategory;
use App\Domains\Settings\Repositories\HelpdeskSettingRepository;
use App\Domains\Tenancy\Services\TenantDummyDataService;
use App\Domains\Tenancy\Support\BootstrapDemoContent;
use App\Domains\Tickets\Models\Ticket;
use App\Domains\Workforce\Models\Department;
use App\Domains\Workforce\Models\Team;
use App\Models\User;
use Database\Seeders\ChannelSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\ProductKnowledgeSeeder;
use Database\Seeders\SlaSeeder;
use Database\Seeders\TenantBootstrapSeeder;
use Database\Seeders\TicketLookupSeeder;
use Database\Seeders\WorkforceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TenantTestCase;

class TenantDummyDataTest extends TenantTestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([
            PermissionSeeder::class,
            TenantBootstrapSeeder::class,
            TicketLookupSeeder::class,
            ChannelSeeder::class,
            SlaSeeder::class,
            \Database\Seeders\WorkforceSeeder::class,
            \Database\Seeders\ProductKnowledgeSeeder::class,
        ]);
    }

    private function admin(): User
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        return $admin;
    }

    public function test_admin_can_load_and_remove_sample_data(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->tenantPost('/setup/dummy-data')
            ->assertRedirect($this->tenantUrl('/workspace'));

        $service = app(TenantDummyDataService::class);

        $this->assertTrue($service->isActive());
        $this->assertFalse($service->needsChoice());
        $this->assertSame(6, Ticket::query()->count());
        $this->assertSame(5, Contact::query()->where('email', 'like', '%@%.example')->count());

        $this->actingAs($admin)
            ->tenantDelete('/setup/dummy-data')
            ->assertRedirect($this->tenantUrl('/setup'));

        $this->assertFalse($service->isActive());
        $this->assertFalse($service->hasBootstrapDemo());
        $this->assertSame(0, Ticket::query()->count());
        $this->assertSame(0, Department::query()->where('slug', 'like', 'sample-%')->count());
        $this->assertFalse(Organization::query()->where('name', 'Acme Inc')->exists());
        $this->assertFalse(ServiceCategory::query()->where('slug', 'it-support')->exists());
        $this->assertFalse(Asset::query()->where('asset_tag', 'AST-00001')->exists());
        $this->assertFalse(Contact::query()->where('email', 'customer@example.com')->exists());
        $this->assertFalse(KnowledgeArticle::query()->whereIn('slug', BootstrapDemoContent::DEMO_KNOWLEDGE_ARTICLE_SLUGS)->exists());
        $this->assertTrue(User::query()->whereKey($admin->id)->exists());
        $this->assertTrue(Department::query()->where('slug', 'support')->exists());
        $this->assertTrue(Team::query()->where('slug', 'tier-1')->exists());
    }

    public function test_admin_can_skip_sample_data(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->tenantPost('/setup/dummy-data/skip')
            ->assertRedirect();

        $service = app(TenantDummyDataService::class);

        $this->assertFalse($service->isActive());
        $this->assertFalse($service->needsChoice());
        $this->assertSame(0, Ticket::query()->count());
    }

    public function test_admin_can_load_sample_data_after_skipping(): void
    {
        $admin = $this->admin();
        $service = app(TenantDummyDataService::class);
        $service->skip();

        $this->actingAs($admin)
            ->tenantPost('/setup/dummy-data')
            ->assertRedirect($this->tenantUrl('/workspace'));

        $this->assertTrue($service->isActive());
        $this->assertSame(6, Ticket::query()->count());
    }

    public function test_admin_can_remove_bootstrap_demo_content(): void
    {
        $admin = $this->admin();
        $service = app(TenantDummyDataService::class);

        $this->assertTrue($service->hasBootstrapDemo());

        $this->actingAs($admin)
            ->from($this->tenantUrl('/setup'))
            ->tenantDelete('/setup/bootstrap-demo')
            ->assertRedirect($this->tenantUrl('/setup'));

        $this->assertFalse($service->hasBootstrapDemo());
        $this->assertFalse(EmailInbox::query()->where('address', BootstrapDemoContent::DEMO_INBOX_ADDRESS)->exists());
        $this->assertFalse(Organization::query()->where('name', 'Acme Inc')->exists());
        $this->assertFalse(ServiceCategory::query()->where('slug', 'it-support')->exists());
        $this->assertFalse(Asset::query()->where('asset_tag', 'AST-00001')->exists());
        $this->assertFalse(Tag::query()->where('slug', 'vip')->exists());
        $this->assertFalse(Contact::query()->where('email', 'customer@example.com')->exists());
        $this->assertFalse(KnowledgeArticle::query()->where('slug', 'helpdesk-platform-overview')->exists());
        $this->assertDemoChannelAddressCleared();
        $this->assertTrue(User::query()->whereKey($admin->id)->exists());
        $this->assertTrue(Department::query()->where('slug', 'support')->exists());
        $this->assertTrue(Team::query()->where('slug', 'tier-1')->exists());
    }

    public function test_has_bootstrap_demo_detects_orphaned_knowledge_categories(): void
    {
        KnowledgeArticle::query()
            ->whereIn('slug', BootstrapDemoContent::DEMO_KNOWLEDGE_ARTICLE_SLUGS)
            ->delete();

        KnowledgeCollection::query()
            ->whereIn('slug', BootstrapDemoContent::DEMO_KNOWLEDGE_COLLECTION_SLUGS)
            ->delete();

        KnowledgeCategory::query()->firstOrCreate(
            ['slug' => 'product-documentation'],
            ['name' => 'Product documentation'],
        );

        $this->assertTrue(app(TenantDummyDataService::class)->hasBootstrapDemo());
    }

    public function test_bootstrap_removal_clears_product_knowledge_without_touching_unrelated_articles(): void
    {
        $admin = $this->admin();
        $service = app(TenantDummyDataService::class);

        KnowledgeArticle::query()->create([
            'author_id' => $admin->id,
            'title' => 'Real article',
            'slug' => 'real-company-policy',
            'excerpt' => 'Internal policy',
            'body' => 'Keep this article after demo cleanup.',
            'is_published' => true,
            'published_at' => now(),
        ]);

        $service->removeBootstrapDemo();

        $this->assertFalse($service->hasBootstrapDemo());
        $this->assertTrue(KnowledgeArticle::query()->where('slug', 'real-company-policy')->exists());
        $this->assertFalse(KnowledgeArticle::query()->whereIn('slug', BootstrapDemoContent::DEMO_KNOWLEDGE_ARTICLE_SLUGS)->exists());
    }

    public function test_setup_page_offers_sample_and_bootstrap_controls_after_skip(): void
    {
        $admin = $this->admin();
        app(TenantDummyDataService::class)->skip();

        $this->actingAs($admin)
            ->tenantGet('/setup')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('dummyData.needs_choice', false)
                ->where('dummyData.active', false)
                ->where('dummyData.can_load_sample', true)
                ->where('dummyData.has_bootstrap_demo', true)
                ->where('dummyData.has_any_demo', true)
            );
    }

    public function test_setup_page_shows_dummy_data_choice(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->tenantGet('/setup')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('dummyData.needs_choice', true)
                ->where('dummyData.active', false)
            );
    }

    public function test_cannot_load_sample_data_twice(): void
    {
        $admin = $this->admin();
        $service = app(TenantDummyDataService::class);

        $service->install($admin);

        $this->actingAs($admin)
            ->tenantPost('/setup/dummy-data')
            ->assertSessionHasErrors('dummy_data');
    }

    public function test_sample_data_pauses_setup_guide_and_allows_workspace_access(): void
    {
        $admin = $this->admin();
        $service = app(TenantDummyDataService::class);
        $service->install($admin);

        $this->actingAs($admin)
            ->tenantGet('/workspace')
            ->assertOk();

        $this->actingAs($admin)
            ->tenantGet('/setup')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('dummyData.active', true)
            );

        $this->actingAs($admin)
            ->tenantPost('/setup/steps/business_hours')
            ->assertSessionHasErrors('setup');
    }

    public function test_can_load_sample_hidden_after_setup_complete(): void
    {
        $admin = $this->admin();
        $service = app(TenantDummyDataService::class);
        $service->skip();

        $setting = app(HelpdeskSettingRepository::class)->current();
        app(HelpdeskSettingRepository::class)->update($setting, [
            'setup_completed_at' => now(),
        ]);

        $state = app(TenantDummyDataService::class)->publicState();

        $this->assertFalse($state['can_load_sample']);
        $this->assertTrue($state['has_bootstrap_demo']);
    }

    public function test_bootstrap_removal_clears_demo_ticket_number(): void
    {
        $admin = $this->admin();

        Ticket::query()->create([
            'number' => 'HD-00001',
            'subject' => 'Demo ticket',
            'description' => 'Seeded demo ticket',
            'contact_id' => null,
            'assigned_to' => $admin->id,
            'ticket_status_id' => \App\Domains\Tickets\Models\TicketStatus::query()->where('slug', 'open')->value('id'),
            'ticket_priority_id' => \App\Domains\Tickets\Models\TicketPriority::query()->where('slug', 'normal')->value('id'),
        ]);

        app(TenantDummyDataService::class)->removeBootstrapDemo();

        $this->assertFalse(Ticket::query()->where('number', 'HD-00001')->exists());
    }

    public function test_bootstrap_removal_clears_demo_channel_settings(): void
    {
        $this->admin();

        app(TenantDummyDataService::class)->removeBootstrapDemo();

        $this->assertDemoChannelAddressCleared();
    }

    public function test_remove_button_state_is_inactive_after_cleanup(): void
    {
        $admin = $this->admin();
        $service = app(TenantDummyDataService::class);
        $service->install($admin);
        $service->remove();

        $setting = app(HelpdeskSettingRepository::class)->current();

        $this->assertFalse($setting->dummy_data_active);
        $this->assertNull($setting->dummy_data_manifest);
        $this->assertFalse($service->publicState()['active']);
        $this->assertFalse($service->publicState()['has_any_demo']);
    }

    private function assertDemoChannelAddressCleared(): void
    {
        $emailChannel = Channel::query()->where('slug', 'email')->first();

        $this->assertNotSame(
            BootstrapDemoContent::DEMO_INBOX_ADDRESS,
            $emailChannel?->settings['address'] ?? null,
        );
    }
}
