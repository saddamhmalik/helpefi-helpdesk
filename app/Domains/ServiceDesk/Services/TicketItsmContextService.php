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
        $type = $ticket->type;
        $majorIncident = $type === ServiceCatalogItem::TYPE_INCIDENT
            ? $this->majorIncidents->snapshotForTicket($ticket)
            : null;
        $approval = in_array($type, [ServiceCatalogItem::TYPE_SERVICE_REQUEST, ServiceCatalogItem::TYPE_CHANGE], true)
            ? $this->approvals->snapshotForTicket($ticket->id)
            : null;

        return [
            'approval' => $approval,
            'canDecideApproval' => $this->canDecideApproval($approval, $userId),
            'changeRecord' => $type === ServiceCatalogItem::TYPE_CHANGE
                ? $this->changeRecords->snapshotForTicket($ticket)
                : null,
            'problemRecord' => $type === ServiceCatalogItem::TYPE_PROBLEM
                ? $this->problemRecords->snapshotForTicket($ticket)
                : null,
            'incidentCandidates' => $this->serviceDesk->isAvailable()
                && $type === ServiceCatalogItem::TYPE_PROBLEM
                ? $this->problemRecords->incidentCandidates($ticket)->values()
                : [],
            'changeRiskOptions' => $type === ServiceCatalogItem::TYPE_CHANGE
                ? $this->changeRecords->riskOptions()
                : [],
            'majorIncident' => $majorIncident,
            'canDeclareMajorIncident' => $this->serviceDesk->isAvailable()
                && $type === ServiceCatalogItem::TYPE_INCIDENT
                && $majorIncident === null,
        ];
    }

    private function canDecideApproval(?array $snapshot, int $userId): bool
    {
        if (! $snapshot || ($snapshot['status'] ?? '') !== 'pending') {
            return false;
        }

        $currentStep = collect($snapshot['steps'] ?? [])->firstWhere('step_order', $snapshot['current_step'] ?? 0);

        return (int) ($currentStep['approver']['id'] ?? 0) === $userId
            && ($currentStep['status'] ?? '') === 'pending';
    }
}
