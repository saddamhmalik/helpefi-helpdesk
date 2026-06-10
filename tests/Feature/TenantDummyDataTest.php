<?php

namespace Tests\Feature;

use App\Domains\Contacts\Models\Contact;
use App\Domains\Knowledge\Models\KnowledgeArticle;
use App\Domains\Knowledge\Models\KnowledgeCategory;
use App\Domains\Knowledge\Models\KnowledgeCollection;
use App\Domains\Settings\Repositories\HelpdeskSettingRepository;
use App\Domains\Tenancy\Services\TenantDummyDataService;
use App\Domains\Tickets\Models\Ticket;
use App\Domains\Workforce\Models\Department;
use App\Models\User;
use Database\Seeders\ChannelSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\SlaSeeder;
use Database\Seeders\TenantBootstrapSeeder;
use Database\Seeders\TicketLookupSeeder;
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
        $this->assertSame(0, Ticket::query()->count());
        $this->assertSame(0, Department::query()->where('slug', 'like', 'sample-%')->count());
        $this->assertSame(0, KnowledgeArticle::query()->count());
        $this->assertSame(0, KnowledgeCollection::query()->count());
        $this->assertSame(0, KnowledgeCategory::query()->count());
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
    }
}
