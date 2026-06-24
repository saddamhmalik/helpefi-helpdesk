<?php

namespace App\Domains\Tickets\Services;

use App\Domains\Assets\Services\AssetService;
use App\Domains\Csat\Services\CsatService;
use App\Domains\Integrations\Services\TicketExternalIssueService;
use App\Domains\ServiceDesk\Services\TicketItsmContextService;
use App\Domains\SideConversations\Services\SideConversationService;
use App\Domains\Sla\Services\SlaService;
use App\Domains\TimeTracking\Services\TimeTrackingService;
use Illuminate\Support\Collection;

class TicketShowPageService
{
    public function __construct(
        private TicketService $ticketService,
        private TicketReadService $ticketReads,
        private TicketFormReferenceService $referenceData,
        private SlaService $slaService,
        private CsatService $csatService,
        private TicketLifecycleService $lifecycleService,
        private SideConversationService $sideConversationService,
        private TimeTrackingService $timeTracking,
        private TicketExternalIssueService $externalIssues,
        private TicketItsmContextService $itsmContext,
    ) {
    }

    public function payload(int $ticketId, int $userId): array
    {
        $ticket = $this->ticketService->show($ticketId);
        $this->ticketReads->markAsRead($userId, $ticket->id);

        return array_merge(
            [
                'ticket' => $ticket,
                'sla' => $this->slaService->snapshotForTicket($ticket),
                'currentUserId' => $userId,
                'mergeCandidates' => [],
            ],
            $this->referenceData->payload(),
            $this->itsmContext->forTicket($ticket, $userId),
        );
    }

    public function lazyPanels(int $ticketId): array
    {
        $ticket = $this->ticketService->show($ticketId);

        return [
            'csat' => $this->csatService->promptForTicket($ticket),
            'lifecycle' => $this->lifecycleService->timeline($ticketId),
            'sideConversations' => $this->sideConversationService->listForTicket($ticketId),
            'timeTracking' => $this->timeTracking->snapshotForTicket($ticketId),
            'externalIssues' => $this->externalIssues->listForTicket($ticketId),
        ];
    }

    public function mergeCandidates(int $userId, int $ticketId): array
    {
        return $this->ticketService->listFiltered([], $userId, 50)
            ->getCollection()
            ->where('id', '!=', $ticketId)
            ->values()
            ->all();
    }

    public function workspaceTicketContext(\App\Domains\Tickets\Models\Ticket $ticket, int $userId): array
    {
        return array_merge(
            [
                'sla' => $this->slaService->snapshotForTicket($ticket),
                'mergeCandidates' => [],
            ],
            $this->itsmContext->forTicket($ticket, $userId),
        );
    }
}
