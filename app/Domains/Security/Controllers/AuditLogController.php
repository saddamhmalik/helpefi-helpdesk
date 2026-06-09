<?php

namespace App\Domains\Security\Controllers;

use App\Domains\Security\Services\AuditLogExportService;
use App\Domains\Security\Services\AuditLogService;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AuditLogController extends Controller
{
    public function __construct(
        private AuditLogService $audit,
        private AuditLogExportService $exportService,
    ) {
    }

    public function export(Request $request): StreamedResponse
    {
        $filters = $request->validate([
            'event' => ['nullable', 'string', 'max:255'],
            'search' => ['nullable', 'string', 'max:255'],
        ]);

        return $this->exportService->csv($filters);
    }

    public function index(Request $request): Response
    {
        return Inertia::render('Settings/AuditLogs', [
            'auditLogs' => $this->audit->list($request->only(['event', 'search'])),
            'filters' => $request->only(['event', 'search']),
            'eventLabels' => config('audit.events', []),
            'summary' => $this->audit->summary(7),
        ]);
    }
}
