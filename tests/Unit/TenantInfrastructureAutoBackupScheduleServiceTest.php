<?php

namespace Tests\Unit;

use App\Domains\Tenancy\Models\TenantInfrastructure;
use App\Domains\Tenancy\Services\TenantInfrastructureAutoBackupScheduleService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TenantInfrastructureAutoBackupScheduleServiceTest extends TestCase
{
    use RefreshDatabase;
    public function test_weekly_schedule_is_due_on_matching_day_and_time(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-19 02:00:00'));

        $record = new TenantInfrastructure([
            'auto_backup_enabled' => true,
            'auto_backup_frequency' => 'weekly',
            'auto_backup_weekday' => 5,
            'auto_backup_time' => '02:00',
            'database_mode' => TenantInfrastructure::MODE_EXTERNAL,
            'storage_mode' => TenantInfrastructure::MODE_EXTERNAL,
            'status' => TenantInfrastructure::STATUS_VERIFIED,
            'backup_export_status' => null,
        ]);

        $service = new TenantInfrastructureAutoBackupScheduleService();

        $this->assertTrue($service->isDue($record));
    }

    public function test_schedule_is_not_due_when_backup_already_ran_this_minute(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-19 02:00:00'));

        $record = new TenantInfrastructure([
            'auto_backup_enabled' => true,
            'auto_backup_frequency' => 'daily',
            'auto_backup_time' => '02:00',
            'database_mode' => TenantInfrastructure::MODE_EXTERNAL,
            'storage_mode' => TenantInfrastructure::MODE_EXTERNAL,
            'status' => TenantInfrastructure::STATUS_VERIFIED,
            'auto_backup_last_run_at' => Carbon::parse('2026-06-19 02:00:00'),
        ]);

        $service = new TenantInfrastructureAutoBackupScheduleService();

        $this->assertFalse($service->isDue($record));

        Carbon::setTestNow();
    }
}
