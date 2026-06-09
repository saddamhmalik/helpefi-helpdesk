<?php

namespace App\Domains\Dashboard\Controllers;

use App\Domains\Reports\Services\ReportService;
use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __construct(private ReportService $reportService)
    {
    }

    public function index(): Response
    {
        $widgets = $this->reportService->dashboardWidgets();

        return Inertia::render('Dashboard/Index', [
            'stats' => [
                'openTickets' => $widgets['openTickets'],
                'contacts' => $widgets['contacts'],
                'publishedArticles' => $widgets['publishedArticles'],
                'createdThisWeek' => $widgets['createdThisWeek'],
                'resolvedThisWeek' => $widgets['resolvedThisWeek'],
                'slaBreaches' => $widgets['slaBreaches'],
            ],
            'csat' => $widgets['csat'],
            'ticketStatuses' => $widgets['ticketStatuses'],
            'ticketPriorities' => $widgets['ticketPriorities'],
            'topAgents' => $widgets['topAgents'],
            'volumeTrend' => $widgets['volumeTrend'],
            'deflection' => $widgets['deflection'],
            'kbDeflection' => $widgets['kbDeflection'],
        ]);
    }
}
