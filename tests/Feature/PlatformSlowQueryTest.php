<?php

namespace Tests\Feature;

use App\Domains\Platform\Jobs\RecordPlatformSlowQueryJob;
use App\Domains\Platform\Models\PlatformSlowQuery;
use App\Domains\Platform\Repositories\PlatformSlowQueryRepository;
use Database\Seeders\PlatformPermissionSeeder;
use Database\Seeders\PlatformUserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class PlatformSlowQueryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([PlatformPermissionSeeder::class, PlatformUserSeeder::class]);
    }

    private function centralUrl(string $path): string
    {
        return 'http://'.config('tenancy.central_app_domain').$path;
    }

    private function adminLogin(): void
    {
        $this->post($this->centralUrl('/admin/login'), [
            'email' => PlatformUserSeeder::DEFAULT_EMAIL,
            'password' => PlatformUserSeeder::DEFAULT_PASSWORD,
        ]);
    }

    public function test_platform_admin_can_view_slow_queries_page(): void
    {
        PlatformSlowQuery::query()->create([
            'connection' => 'tenant',
            'database_host' => 'db.customer.example',
            'database_name' => 'helpdesk_tenant_test',
            'sql' => 'select * from tickets',
            'time_ms' => 812,
            'method' => 'GET',
            'url' => 'https://demo.helpefi.com/tickets/1',
            'route_name' => 'tenant.tickets.show',
            'source_file' => 'app/Domains/Tickets/Repositories/TicketRepository.php',
            'source_line' => 169,
            'source_callable' => 'App\\Domains\\Tickets\\Repositories\\TicketRepository@find',
        ]);

        $this->adminLogin();

        $this->get($this->centralUrl('/admin/slow-queries'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Central/Admin/SlowQueries/Index')
                ->has('slowQueries.data', 1)
                ->where('slowQueries.data.0.time_ms', 812)
                ->where('slowQueries.data.0.database_host', 'db.customer.example'));
    }

    public function test_platform_admin_can_view_slow_query_detail(): void
    {
        $entry = PlatformSlowQuery::query()->create([
            'connection' => 'tenant',
            'database_host' => 'db.customer.example',
            'database_name' => 'helpdesk_tenant_test',
            'sql' => 'select * from tickets where id = ?',
            'time_ms' => 812,
            'bindings' => [9],
            'method' => 'GET',
            'url' => 'https://demo.helpefi.com/tickets/9',
            'route_name' => 'tenant.tickets.show',
            'source_file' => 'app/Domains/Tickets/Repositories/TicketRepository.php',
            'source_line' => 169,
            'source_callable' => 'App\\Domains\\Tickets\\Repositories\\TicketRepository@find',
            'tenant_id' => 'tenant-1',
        ]);

        $this->adminLogin();

        $this->get($this->centralUrl("/admin/slow-queries/{$entry->id}"))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Central/Admin/SlowQueries/Show')
                ->where('slowQuery.id', $entry->id)
                ->where('slowQuery.route_name', 'tenant.tickets.show')
                ->where('slowQuery.database_host', 'db.customer.example')
                ->where('slowQuery.source_callable', 'App\\Domains\\Tickets\\Repositories\\TicketRepository@find'));
    }

    public function test_record_job_persists_slow_query(): void
    {
        $job = new RecordPlatformSlowQueryJob(
            tenantId: 'tenant-1',
            databaseConnection: 'tenant',
            sql: 'select * from tickets',
            timeMs: 620,
            bindings: null,
            method: 'GET',
            url: 'https://demo.test/tickets',
            routeName: 'tenant.tickets.show',
            sourceFile: 'app/Domains/Tickets/Repositories/TicketRepository.php',
            sourceLine: 169,
            sourceCallable: 'App\\Domains\\Tickets\\Repositories\\TicketRepository@find',
            databaseHost: 'db.customer.example',
            databaseName: 'helpdesk_tenant_test',
        );

        $job->handle(app(PlatformSlowQueryRepository::class));

        $this->assertDatabaseHas('platform_slow_queries', [
            'tenant_id' => 'tenant-1',
            'connection' => 'tenant',
            'database_host' => 'db.customer.example',
            'database_name' => 'helpdesk_tenant_test',
            'route_name' => 'tenant.tickets.show',
            'time_ms' => 620,
            'method' => 'GET',
        ], 'central');
    }

    public function test_record_dispatches_to_queue_without_after_response(): void
    {
        Queue::fake();

        config(['database.slow_query.store' => true]);

        app(\App\Domains\Platform\Services\PlatformSlowQueryService::class)->record(
            connection: 'tenant',
            sql: 'select 1',
            timeMs: 900,
            tenantId: 'tenant-1',
            routeName: 'tenant.tickets.show',
            sourceFile: 'app/Domains/Tickets/Services/TicketService.php',
            sourceLine: 85,
            sourceCallable: 'App\\Domains\\Tickets\\Services\\TicketService@show',
            databaseHost: 'db.customer.example',
            databaseName: 'helpdesk_tenant_test',
        );

        Queue::assertPushed(RecordPlatformSlowQueryJob::class);
    }

    public function test_platform_admin_can_bulk_delete_selected_slow_queries(): void
    {
        $keep = PlatformSlowQuery::query()->create([
            'connection' => 'tenant',
            'sql' => 'select 1',
            'time_ms' => 500,
        ]);
        $remove = PlatformSlowQuery::query()->create([
            'connection' => 'tenant',
            'sql' => 'select 2',
            'time_ms' => 900,
        ]);

        $this->adminLogin();

        $this->delete($this->centralUrl('/admin/slow-queries/bulk'), [
            'ids' => [$remove->id],
        ])->assertRedirect();

        $this->assertDatabaseMissing('platform_slow_queries', ['id' => $remove->id], 'central');
        $this->assertDatabaseHas('platform_slow_queries', ['id' => $keep->id], 'central');
    }

    public function test_platform_admin_can_delete_slow_queries_matching_filters(): void
    {
        PlatformSlowQuery::query()->create([
            'connection' => 'tenant',
            'sql' => 'select slow',
            'time_ms' => 900,
            'tenant_id' => 'tenant-a',
        ]);
        PlatformSlowQuery::query()->create([
            'connection' => 'central',
            'sql' => 'select fast',
            'time_ms' => 600,
            'tenant_id' => 'tenant-b',
        ]);

        $this->adminLogin();

        $this->delete($this->centralUrl('/admin/slow-queries'), [
            'tenant_id' => 'tenant-a',
        ])->assertRedirect();

        $this->assertDatabaseMissing('platform_slow_queries', ['tenant_id' => 'tenant-a'], 'central');
        $this->assertDatabaseHas('platform_slow_queries', ['tenant_id' => 'tenant-b'], 'central');
    }
}
