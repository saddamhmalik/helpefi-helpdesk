<?php

namespace Tests\Feature;

use App\Domains\Platform\Models\PlatformAuditLog;
use App\Domains\Platform\Repositories\PlatformAuditLogRepository;
use Database\Seeders\PlatformPermissionSeeder;
use Database\Seeders\PlatformUserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlatformAuditLogInfrastructureFilterTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([PlatformPermissionSeeder::class, PlatformUserSeeder::class]);
    }

    public function test_infrastructure_category_filter_matches_byo_and_infrastructure_events(): void
    {
        PlatformAuditLog::query()->create([
            'event' => 'platform.tenant.infrastructure_updated',
            'actor_email' => 'admin@example.com',
        ]);

        PlatformAuditLog::query()->create([
            'event' => 'platform.tenant.byo_allowed',
            'actor_email' => 'admin@example.com',
        ]);

        PlatformAuditLog::query()->create([
            'event' => 'platform.tenant.updated',
            'actor_email' => 'admin@example.com',
        ]);

        $logs = app(PlatformAuditLogRepository::class)->paginate(['category' => 'infrastructure']);

        $this->assertCount(2, $logs->items());
        $events = collect($logs->items())->pluck('event')->sort()->values()->all();
        $this->assertSame([
            'platform.tenant.byo_allowed',
            'platform.tenant.infrastructure_updated',
        ], $events);
    }
}
