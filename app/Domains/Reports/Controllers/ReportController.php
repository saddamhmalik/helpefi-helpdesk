<?php

namespace App\Domains\Reports\Controllers;

use App\Domains\Reports\Models\SavedReport;
use App\Domains\Reports\Services\ReportScheduleService;
use App\Domains\Reports\Services\ReportService;
use App\Domains\Tickets\Services\TicketFormReferenceService;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function __construct(
        private ReportService $reportService,
        private ReportScheduleService $scheduleService,
        private TicketFormReferenceService $ticketReferenceData,
    ) {
    }

    public function index(Request $request): Response
    {
        $filters = $this->filtersFromRequest($request);
        $type = $request->input('type', SavedReport::TYPE_TICKETS);
        $result = null;

        if ($request->has('run')) {
            $result = $this->reportService->run($type, $filters);
        }

        return Inertia::render('Reports/Index', array_merge([
            'reportTypes' => $this->reportService->types(),
            'savedReports' => $this->reportService->savedForUser($request->user()->id),
            'scheduleOptions' => $this->scheduleService->options(),
            'filters' => $filters,
            'activeType' => $type,
            'result' => $result,
        ], $this->ticketReferenceData->payload()));
    }

    public function export(Request $request): StreamedResponse
    {
        $data = $request->validate([
            'type' => ['required', 'string'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date'],
            'status_id' => ['nullable', 'integer'],
            'priority_id' => ['nullable', 'integer'],
            'assigned_to' => ['nullable', 'integer'],
            'team_id' => ['nullable', 'integer'],
        ]);

        $type = $data['type'];
        unset($data['type']);

        return $this->reportService->exportCsv($type, $data);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', 'string'],
            'filters' => ['nullable', 'array'],
            'filters.date_from' => ['nullable', 'date'],
            'filters.date_to' => ['nullable', 'date'],
            'filters.status_id' => ['nullable', 'integer'],
            'filters.priority_id' => ['nullable', 'integer'],
            'filters.assigned_to' => ['nullable', 'integer'],
            'filters.team_id' => ['nullable', 'integer'],
            'is_default' => ['boolean'],
        ]);

        $this->reportService->createSaved(
            $request->user()->id,
            $data['name'],
            $data['type'],
            $data['filters'] ?? [],
            $data['is_default'] ?? false,
        );

        return back()->with('success', 'Report saved.');
    }

    public function destroy(Request $request, int $report): RedirectResponse
    {
        $this->reportService->deleteSaved($report, $request->user()->id);

        return back()->with('success', 'Report deleted.');
    }

    private function filtersFromRequest(Request $request): array
    {
        return array_filter([
            'date_from' => $request->input('date_from'),
            'date_to' => $request->input('date_to'),
            'status_id' => $request->input('status_id'),
            'priority_id' => $request->input('priority_id'),
            'assigned_to' => $request->input('assigned_to'),
            'team_id' => $request->input('team_id'),
        ], fn ($value) => $value !== null && $value !== '');
    }
}
