<?php

namespace App\Domains\Reports\Controllers\Api;

use App\Domains\Reports\Models\SavedReport;
use App\Domains\Reports\Services\ReportService;
use App\Domains\Tickets\Services\TicketFormReferenceService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function __construct(
        private ReportService $reportService,
        private TicketFormReferenceService $ticketReferenceData,
    ) {
    }

    public function meta(): JsonResponse
    {
        return response()->json(array_merge(
            ['types' => $this->reportService->types()],
            $this->ticketReferenceData->only(['statuses', 'priorities', 'agents']),
        ));
    }

    public function dashboard(): JsonResponse
    {
        return response()->json($this->reportService->dashboardWidgets());
    }

    public function run(Request $request): JsonResponse
    {
        $data = $this->validatedFilters($request);
        $type = $request->input('type', SavedReport::TYPE_TICKETS);

        return response()->json($this->reportService->run($type, $data));
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
        ]);

        $type = $data['type'];
        unset($data['type']);

        return $this->reportService->exportCsv($type, $data);
    }

    public function saved(Request $request): JsonResponse
    {
        return response()->json($this->reportService->savedForUser($request->user()->id));
    }

    public function store(Request $request): JsonResponse
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
            'is_default' => ['boolean'],
        ]);

        $report = $this->reportService->createSaved(
            $request->user()->id,
            $data['name'],
            $data['type'],
            $data['filters'] ?? [],
            $data['is_default'] ?? false,
        );

        return response()->json($report, 201);
    }

    public function destroy(Request $request, int $report): JsonResponse
    {
        $this->reportService->deleteSaved($report, $request->user()->id);

        return response()->json(['message' => 'Report deleted.']);
    }

    private function validatedFilters(Request $request): array
    {
        return array_filter($request->validate([
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date'],
            'status_id' => ['nullable', 'integer'],
            'priority_id' => ['nullable', 'integer'],
            'assigned_to' => ['nullable', 'integer'],
        ]), fn ($value) => $value !== null && $value !== '');
    }
}
