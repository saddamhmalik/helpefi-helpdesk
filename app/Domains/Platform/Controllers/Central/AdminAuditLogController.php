<?php

namespace App\Domains\Platform\Controllers\Central;

use App\Domains\Platform\Services\PlatformAuditLogExportService;
use App\Domains\Platform\Services\PlatformAuditLogService;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AdminAuditLogController extends Controller
{
    public function __construct(
        private PlatformAuditLogService $audit,
        private PlatformAuditLogExportService $exportService,
    ) {
    }

    public function index(Request $request): Response
    {
        $filters = $request->validate([
            'event' => ['nullable', 'string', 'max:255'],
            'search' => ['nullable', 'string', 'max:255'],
            'tenant_id' => ['nullable', 'string', 'max:255'],
        ]);

        return Inertia::render('Central/Admin/AuditLogs/Index', [
            'auditLogs' => $this->audit->list($filters),
            'filters' => $filters,
            'eventLabels' => config('platform_audit.events', []),
            'summary' => $this->audit->summary(7),
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $filters = $request->validate([
            'event' => ['nullable', 'string', 'max:255'],
            'search' => ['nullable', 'string', 'max:255'],
            'tenant_id' => ['nullable', 'string', 'max:255'],
        ]);

        return $this->exportService->csv($filters);
    }
}
