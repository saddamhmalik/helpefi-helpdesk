<?php

namespace Tests\Feature;

use App\Domains\Reports\Jobs\SendScheduledReportJob;
use App\Domains\Reports\Mail\ScheduledReportMail;
use App\Domains\Reports\Models\ReportSchedule;
use App\Domains\Reports\Models\SavedReport;
use App\Domains\Reports\Services\ReportScheduleService;
use App\Models\User;
use Database\Seeders\TicketLookupSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ScheduledReportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(TicketLookupSeeder::class);
    }

    private function savedReportFor(User $user): SavedReport
    {
        return SavedReport::query()->create([
            'user_id' => $user->id,
            'name' => 'Weekly SLA breaches',
            'type' => SavedReport::TYPE_SLA_BREACHES,
            'filters' => ['date_from' => now()->subWeek()->toDateString()],
            'is_default' => false,
        ]);
    }

    public function test_agent_can_save_weekly_report_schedule(): void
    {
        $user = User::factory()->create();
        $report = $this->savedReportFor($user);

        $this->actingAs($user)
            ->put("/reports/{$report->id}/schedule", [
                'frequency' => ReportSchedule::FREQUENCY_WEEKLY,
                'weekday' => 1,
                'send_hour' => 8,
                'format' => ReportSchedule::FORMAT_CSV,
                'is_enabled' => true,
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseHas('report_schedules', [
            'saved_report_id' => $report->id,
            'user_id' => $user->id,
            'frequency' => ReportSchedule::FREQUENCY_WEEKLY,
            'weekday' => 1,
            'send_hour' => 8,
            'format' => ReportSchedule::FORMAT_CSV,
            'is_enabled' => true,
        ]);
    }

    public function test_agent_can_remove_report_schedule(): void
    {
        $user = User::factory()->create();
        $report = $this->savedReportFor($user);

        app(ReportScheduleService::class)->upsert($user->id, $report->id, [
            'frequency' => ReportSchedule::FREQUENCY_DAILY,
            'send_hour' => 9,
            'format' => ReportSchedule::FORMAT_PDF,
            'is_enabled' => true,
        ]);

        $this->actingAs($user)
            ->delete("/reports/{$report->id}/schedule")
            ->assertRedirect();

        $this->assertDatabaseMissing('report_schedules', [
            'saved_report_id' => $report->id,
        ]);
    }

    public function test_dispatch_command_queues_due_schedules(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        $report = $this->savedReportFor($user);

        ReportSchedule::query()->create([
            'saved_report_id' => $report->id,
            'user_id' => $user->id,
            'frequency' => ReportSchedule::FREQUENCY_DAILY,
            'send_hour' => 8,
            'format' => ReportSchedule::FORMAT_CSV,
            'is_enabled' => true,
            'next_run_at' => now()->subMinute(),
        ]);

        $this->artisan('reports:dispatch-scheduled')
            ->assertSuccessful();

        Queue::assertPushed(SendScheduledReportJob::class);
    }

    public function test_scheduled_report_job_emails_csv_attachment(): void
    {
        Mail::fake();

        $user = User::factory()->create();
        $report = $this->savedReportFor($user);

        $schedule = ReportSchedule::query()->create([
            'saved_report_id' => $report->id,
            'user_id' => $user->id,
            'frequency' => ReportSchedule::FREQUENCY_WEEKLY,
            'weekday' => 1,
            'send_hour' => 8,
            'format' => ReportSchedule::FORMAT_CSV,
            'is_enabled' => true,
            'next_run_at' => now()->subMinute(),
        ]);

        (new SendScheduledReportJob($schedule->id))->handle(
            app(\App\Domains\Reports\Services\ReportService::class),
            app(ReportScheduleService::class),
        );

        Mail::assertSent(ScheduledReportMail::class, function (ScheduledReportMail $mail) use ($user) {
            return $mail->hasTo($user->email);
        });

        $schedule->refresh();
        $this->assertNotNull($schedule->last_sent_at);
        $this->assertNotNull($schedule->next_run_at);
        $this->assertTrue($schedule->next_run_at->isFuture());
    }

    public function test_reports_page_includes_schedule_options(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/reports')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Reports/Index')
                ->has('scheduleOptions.frequencies')
                ->has('scheduleOptions.formats')
            );
    }
}
