<?php

namespace App\Domains\Reports\Jobs;

use App\Domains\Reports\Mail\ScheduledReportMail;
use App\Domains\Reports\Models\ReportSchedule;
use App\Domains\Reports\Services\ReportScheduleService;
use App\Domains\Reports\Services\ReportService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Mail;

class SendScheduledReportJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(
        public int $scheduleId,
    ) {
    }

    public function handle(ReportService $reports, ReportScheduleService $schedules): void
    {
        $schedule = ReportSchedule::query()
            ->with(['savedReport', 'user'])
            ->find($this->scheduleId);

        if (! $schedule || ! $schedule->is_enabled || ! $schedule->savedReport || ! $schedule->user) {
            return;
        }

        if ($schedule->next_run_at && $schedule->next_run_at->isFuture()) {
            return;
        }

        $saved = $schedule->savedReport;
        $filters = $saved->filters ?? [];
        $filename = str_replace(['/', '\\', ' '], '-', strtolower($saved->name)).'-'.now()->format('Y-m-d');

        if ($schedule->format === ReportSchedule::FORMAT_PDF) {
            $attachment = $reports->generatePdfContent($saved->type, $filters, $saved->name);
            $filename .= '.pdf';
            $mime = 'application/pdf';
        } else {
            $attachment = $reports->generateCsvContent($saved->type, $filters);
            $filename .= '.csv';
            $mime = 'text/csv';
        }

        Mail::to($schedule->user->email)->send(new ScheduledReportMail(
            $schedule->user,
            $saved,
            $schedule,
            $attachment,
            $filename,
            $mime,
        ));

        $schedules->markSent($schedule);
    }
}
