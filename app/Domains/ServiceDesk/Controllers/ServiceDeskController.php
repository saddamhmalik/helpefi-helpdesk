<?php

namespace App\Domains\ServiceDesk\Controllers;

use App\Domains\Channels\Services\ChannelService;
use App\Domains\ServiceDesk\Services\ApprovalService;
use App\Domains\ServiceDesk\Services\MajorIncidentService;
use App\Domains\ServiceDesk\Services\ServiceDeskService;
use App\Domains\Tickets\Services\TicketService;
use App\Domains\Tickets\Support\TicketFilters;
use App\Domains\Workforce\Services\WorkforceService;
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
        private ChannelService $channelService,
        private WorkforceService $workforceService,
    ) {
    }

    public function index(Request $request): Response
    {
        if (! $this->serviceDesk->isAvailable()) {
            return Inertia::render('ServiceDesk/Upgrade', $this->serviceDesk->upgradeContext());
        }

        $overview = $this->serviceDesk->overview();
        $user = $request->user();

        return Inertia::render('ServiceDesk/Index', [
            'summaries' => $overview['summaries'],
            'totals' => $overview['totals'],
            'recent' => $overview['recent'],
            'approvalStats' => [
                'pending' => $this->approvals->pendingCount(),
                'pending_mine' => $this->approvals->pendingCountForUser($user->id),
            ],
            'majorIncidentStats' => [
                'active' => $this->majorIncidents->activeCount(),
                'pending_review' => $this->majorIncidents->pendingReviewCount(),
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

        return Inertia::render('ServiceDesk/Queue', [
            'type' => $typeDefinition,
            'tickets' => $this->ticketService->listFiltered($filters, $user->id),
            'statuses' => $this->ticketService->statuses(),
            'priorities' => $this->ticketService->priorities(),
            'agents' => $this->workforceService->agentOptions(),
            'channels' => $this->channelService->all(),
            'departments' => $this->workforceService->departmentOptions(),
            'teams' => $this->workforceService->teamOptions(),
            'filters' => $filters,
        ]);
    }
}
