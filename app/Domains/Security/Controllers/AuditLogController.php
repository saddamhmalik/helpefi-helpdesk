<?php

namespace App\Domains\Security\Controllers;

use App\Domains\Security\Services\AuditLogService;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AuditLogController extends Controller
{
    public function __construct(private AuditLogService $audit)
    {
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
