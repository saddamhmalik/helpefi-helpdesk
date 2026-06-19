<?php

namespace App\Domains\ServiceDesk\Controllers;

use App\Domains\ServiceDesk\Services\ApprovalService;
use App\Domains\ServiceDesk\Services\MajorIncidentService;
use App\Domains\ServiceDesk\Services\ServiceDeskService;
use App\Domains\Tickets\Services\TicketFormReferenceService;
use App\Domains\Tickets\Services\TicketService;
use App\Domains\Tickets\Support\TicketFilters;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ServiceDeskController extends Controller
{
    public function __construct(
        private ServiceDeskService $serviceDesk,
        private ApprovalService $approvals,
        private MajorIncidentService $majorIncidents,
        private TicketService $ticketService,
        private TicketFormReferenceService $ticketReferenceData,
    ) {
    }

    public function index(Request $request): Response
    {
        if (! $this->serviceDesk->isAvailable()) {
            return Inertia::render('ServiceDesk/Upgrade', $this->serviceDesk->upgradeContext());
        }

        $overview = $this->serviceDesk->overview();
        $user = $request->user();
        $approvalStats = $this->approvals->pendingCounts($user->id);
        $majorIncidentStats = $this->majorIncidents->dashboardCounts();

        return Inertia::render('ServiceDesk/Index', [
            'summaries' => $overview['summaries'],
            'totals' => $overview['totals'],
            'recent' => $overview['recent'],
            'approvalStats' => [
                'pending' => $approvalStats['pending'],
                'pending_mine' => $approvalStats['pending_mine'],
            ],
            'majorIncidentStats' => [
                'active' => $majorIncidentStats['active'],
                'pending_review' => $majorIncidentStats['pending_review'],
            ],
        ]);
    }

    public function queue(Request $request, string $type): Response
    {
        $this->serviceDesk->assertAvailable();
        $typeDefinition = $this->serviceDesk->assertValidType($type);

        $filters = TicketFilters::normalize(array_merge(
            $request->only(TicketFilters::KEYS),
            ['type' => $type],
        ));

        $user = $request->user();

        return Inertia::render('ServiceDesk/Queue', array_merge([
            'type' => $typeDefinition,
            'tickets' => $this->ticketService->listFiltered($filters, $user->id),
            'filters' => $filters,
        ], $this->ticketReferenceData->payload()));
    }
}
