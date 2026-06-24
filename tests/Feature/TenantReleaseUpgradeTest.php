<?php

namespace Tests\Feature;

use App\Domains\Knowledge\Support\PlatformKnowledge;
use App\Domains\Tenancy\Services\TenantReleaseUpgradeService;
use App\Domains\Tenancy\Services\TenantWorkspaceUpgradeService;
use App\Domains\Tickets\Models\Ticket;
use App\Domains\Tickets\Models\TicketNumberSequence;
use App\Domains\Tickets\Models\TicketPriority;
use App\Domains\Tickets\Models\TicketStatus;
use App\Support\AppVersion;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\PlatformHandbookSeeder;
use Database\Seeders\TenantBootstrapSeeder;
use Database\Seeders\TicketLookupSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TenantTestCase;

class TenantReleaseUpgradeTest extends TenantTestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['app.version' => '1.0.0']);

        $this->seed([
            PermissionSeeder::class,
            TenantBootstrapSeeder::class,
            TicketLookupSeeder::class,
        ]);
    }

    public function test_target_release_matches_application_version(): void
    {
        $this->assertSame(AppVersion::current(), app(TenantReleaseUpgradeService::class)->targetRelease());
    }

    public function test_release_upgrade_backfills_ticket_number_sequences(): void
    {
        $status = TicketStatus::query()->firstOrFail();
        $priority = TicketPriority::query()->firstOrFail();

        Ticket::query()->create([
            'number' => 'HD-00042',
            'subject' => 'Existing ticket',
            'ticket_status_id' => $status->id,
            'ticket_priority_id' => $priority->id,
        ]);

        TicketNumberSequence::query()->delete();
        DB::table('tenant_release_migrations')->delete();

        $result = app(TenantReleaseUpgradeService::class)->upgradeTenant($this->tenant);

        $this->assertContains('1.0.0:backfill_ticket_number_sequences', $result['ran_steps']);
        $this->assertSame(43, TicketNumberSequence::query()->value('last_value'));
        $this->assertSame('1.0.0', $this->tenant->fresh()->release_version);
    }

    public function test_release_upgrade_is_idempotent(): void
    {
        $service = app(TenantReleaseUpgradeService::class);

        $first = $service->upgradeTenant($this->tenant);
        $second = $service->upgradeTenant($this->tenant->fresh());

        $this->assertNotEmpty($first['ran_steps']);
        $this->assertSame([], $second['ran_steps']);
        $this->assertSame(0, $service->pendingStepCount($this->tenant->fresh()));
    }

    public function test_release_upgrade_normalizes_platform_handbook_metadata(): void
    {
        $this->seed(PlatformHandbookSeeder::class);

        \App\Domains\Knowledge\Models\KnowledgeCollection::query()
            ->where('slug', PlatformKnowledge::HANDBOOK_COLLECTION_SLUG)
            ->update(['is_system' => false, 'is_public' => true]);

        \App\Domains\Knowledge\Models\KnowledgeArticle::query()
            ->where('slug', 'handbook-ai-copilot')
            ->update(['is_system' => false]);

        DB::table('tenant_release_migrations')->delete();

        app(TenantReleaseUpgradeService::class)->upgradeTenant($this->tenant);

        $this->assertDatabaseHas('knowledge_collections', [
            'slug' => PlatformKnowledge::HANDBOOK_COLLECTION_SLUG,
            'is_system' => true,
            'is_public' => false,
        ]);

        $this->assertDatabaseHas('knowledge_articles', [
            'slug' => 'handbook-ai-copilot',
            'is_system' => true,
        ]);
    }

    public function test_tenants_upgrade_command_runs_for_current_tenant(): void
    {
        $this->artisan('tenants:upgrade', [
            'tenant' => $this->tenant->slug,
            '--skip-cache' => true,
        ])->assertSuccessful();
    }

    public function test_legacy_upgrade_workspaces_command_delegates_to_release_upgrades(): void
    {
        DB::table('tenant_release_migrations')->delete();
        $this->tenant->forceFill(['release_version' => null, 'release_upgraded_at' => null])->save();

        $this->artisan('helpdesk:upgrade-workspaces', [
            'tenant' => $this->tenant->slug,
        ])->assertSuccessful();

        $this->assertSame('1.0.0', $this->tenant->fresh()->release_version);
    }

    public function test_workspace_upgrade_service_methods_remain_available(): void
    {
        $status = TicketStatus::query()->firstOrFail();
        $priority = TicketPriority::query()->firstOrFail();

        Ticket::query()->create([
            'number' => 'HD-00010',
            'subject' => 'Existing ticket',
            'ticket_status_id' => $status->id,
            'ticket_priority_id' => $priority->id,
        ]);

        TicketNumberSequence::query()->delete();

        $upgrade = app(TenantWorkspaceUpgradeService::class);

        $this->assertSame(1, $upgrade->syncTicketNumberSequences());
        $this->assertSame(0, $upgrade->syncTicketNumberSequences());
    }
}
