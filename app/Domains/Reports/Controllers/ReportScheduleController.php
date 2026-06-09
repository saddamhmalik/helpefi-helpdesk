<?php

namespace App\Domains\Reports\Controllers;

use App\Domains\Reports\Models\ReportSchedule;
use App\Domains\Reports\Services\ReportScheduleService;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ReportScheduleController extends Controller
{
    public function __construct(
        private ReportScheduleService $schedules,
    ) {
    }

    public function upsert(Request $request, int $report): RedirectResponse
    {
        $data = $request->validate([
            'frequency' => ['required', 'string', 'in:'.ReportSchedule::FREQUENCY_DAILY.','.ReportSchedule::FREQUENCY_WEEKLY],
            'weekday' => ['nullable', 'integer', 'min:1', 'max:7'],
            'send_hour' => ['required', 'integer', 'min:0', 'max:23'],
            'format' => ['required', 'string', 'in:'.ReportSchedule::FORMAT_CSV.','.ReportSchedule::FORMAT_PDF],
            'is_enabled' => ['boolean'],
        ]);

        $this->schedules->upsert($request->user()->id, $report, $data);

        return back()->with('success', 'Report schedule saved.');
    }

    public function destroy(Request $request, int $report): RedirectResponse
    {
        $this->schedules->delete($request->user()->id, $report);

        return back()->with('success', 'Report schedule removed.');
    }
}
