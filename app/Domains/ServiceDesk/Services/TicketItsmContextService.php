<?php

namespace App\Domains\ServiceDesk\Services;

use App\Domains\ServiceCatalog\Models\ServiceCatalogItem;
use App\Domains\Tickets\Models\Ticket;

class TicketItsmContextService
{
    public function __construct(
        private ApprovalService $approvals,
        private ChangeRecordService $changeRecords,
        private ProblemRecordService $problemRecords,
        private MajorIncidentService $majorIncidents,
        private ServiceDeskService $serviceDesk,
    ) {
    }

    public function forTicket(Ticket $ticket, int $userId): array
    {
        $majorIncident = $this->majorIncidents->snapshotForTicket($ticket->id);

        return [
            'approval' => $this->approvals->snapshotForTicket($ticket->id),
            'canDecideApproval' => $this->canDecideApproval($ticket->id, $userId),
            'changeRecord' => $this->changeRecords->snapshotForTicket($ticket->id),
            'problemRecord' => $this->problemRecords->snapshotForTicket($ticket->id),
            'incidentCandidates' => $ticket->type === ServiceCatalogItem::TYPE_PROBLEM
                ? $this->problemRecords->incidentCandidates($ticket->id)->values()
                : [],
            'changeRiskOptions' => $this->changeRecords->riskOptions(),
            'majorIncident' => $majorIncident,
            'canDeclareMajorIncident' => $this->serviceDesk->isAvailable()
                && $ticket->type === ServiceCatalogItem::TYPE_INCIDENT
                && $majorIncident === null,
        ];
    }

    private function canDecideApproval(int $ticketId, int $userId): bool
    {
        $snapshot = $this->approvals->snapshotForTicket($ticketId);

        if (! $snapshot || ($snapshot['status'] ?? '') !== 'pending') {
            return false;
        }

        $currentStep = collect($snapshot['steps'] ?? [])->firstWhere('step_order', $snapshot['current_step'] ?? 0);

        return (int) ($currentStep['approver']['id'] ?? 0) === $userId
            && ($currentStep['status'] ?? '') === 'pending';
    }
}
