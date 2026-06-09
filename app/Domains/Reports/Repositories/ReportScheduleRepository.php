<?php

namespace App\Domains\Reports\Repositories;

use App\Domains\Reports\Models\ReportSchedule;
use App\Domains\Reports\Models\SavedReport;
use Illuminate\Database\Eloquent\Collection;

class ReportScheduleRepository
{
    public function findForSavedReport(int $savedReportId, int $userId): ?ReportSchedule
    {
        return ReportSchedule::query()
            ->where('saved_report_id', $savedReportId)
            ->where('user_id', $userId)
            ->first();
    }

    public function due(): Collection
    {
        return ReportSchedule::query()
            ->with(['savedReport', 'user'])
            ->where('is_enabled', true)
            ->whereNotNull('next_run_at')
            ->where('next_run_at', '<=', now())
            ->get();
    }

    public function upsert(SavedReport $report, int $userId, array $attributes): ReportSchedule
    {
        return ReportSchedule::query()->updateOrCreate(
            ['saved_report_id' => $report->id],
            array_merge($attributes, [
                'user_id' => $userId,
            ]),
        );
    }

    public function deleteForSavedReport(SavedReport $report): void
    {
        ReportSchedule::query()
            ->where('saved_report_id', $report->id)
            ->delete();
    }
}
