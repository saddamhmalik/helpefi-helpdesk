<?php

namespace App\Domains\Tickets\Services;

use App\Domains\Channels\Repositories\ChannelRepository;
use App\Domains\Sla\Services\SlaService;
use App\Domains\Tickets\Models\Ticket;
use App\Domains\Tickets\Repositories\TicketRepository;
use App\Domains\Workforce\Models\Department;
use App\Domains\Workforce\Models\Team;
use App\Domains\Workforce\Repositories\TeamRepository;
use App\Models\User;

class TicketActionService
{
    public function __construct(
        private TicketRepository $tickets,
        private SlaService $sla,
        private ChannelRepository $channels,
        private TeamRepository $teams,
    ) {
    }

    public function execute(Ticket $ticket, array $actions): Ticket
    {
        foreach ($actions as $action) {
            $ticket = $this->executeOne($ticket, $action);
        }

        return $this->tickets->find($ticket->id);
    }

    public function executeOne(Ticket $ticket, array $action): Ticket
    {
        $ticket = $this->tickets->find($ticket->id);

        return match ($action['type'] ?? '') {
            'set_status' => $this->setStatus($ticket, (int) ($action['value'] ?? 0)),
            'set_priority' => $this->setPriority($ticket, (int) ($action['value'] ?? 0)),
            'assign_to' => $this->assignTo($ticket, filled($action['value']) ? (int) $action['value'] : null),
            'add_watcher' => $this->addWatcher($ticket, (int) ($action['value'] ?? 0)),
            'add_internal_note' => $this->addInternalNote($ticket, (string) ($action['value'] ?? '')),
            'notify_team_lead' => $this->notifyTeamLead($ticket, (string) ($action['value'] ?? '')),
            'notify_department_head' => $this->notifyDepartmentHead($ticket, (string) ($action['value'] ?? '')),
            'assign_to_team_lead' => $this->assignToTeamLead($ticket),
            'assign_to_department_head' => $this->assignToDepartmentHead($ticket),
            default => $ticket,
        };
    }

    private function setStatus(Ticket $ticket, int $statusId): Ticket
    {
        $status = $this->tickets->statuses()->firstWhere('id', $statusId);
        $data = ['ticket_status_id' => $statusId, 'closed_at' => $status?->is_closed ? now() : null];
        $ticket = $this->tickets->update($ticket, $data);

        if ($status?->is_closed) {
            $this->sla->recordResolution($ticket);
        }

        return $ticket;
    }

    private function setPriority(Ticket $ticket, int $priorityId): Ticket
    {
        return $this->tickets->update($ticket, ['ticket_priority_id' => $priorityId]);
    }

    private function assignTo(Ticket $ticket, ?int $userId): Ticket
    {
        return $this->tickets->update($ticket, ['assigned_to' => $userId]);
    }

    private function addWatcher(Ticket $ticket, int $userId): Ticket
    {
        if ($userId) {
            $this->tickets->addWatcher($ticket, $userId);
        }

        return $ticket;
    }

    private function addInternalNote(Ticket $ticket, string $body): Ticket
    {
        if ($body === '') {
            return $ticket;
        }

        $this->tickets->addMessage($ticket, [
            'body' => $body,
            'is_internal' => true,
            'channel_id' => $this->channels->findActiveBySlug('web')?->id,
        ]);

        return $ticket;
    }

    private function notifyTeamLead(Ticket $ticket, string $note): Ticket
    {
        $lead = $this->resolveTeamLead($ticket);

        if ($lead) {
            $this->tickets->addWatcher($ticket, $lead->id);

            if ($note !== '') {
                $this->addInternalNote($ticket, $note);
            }
        }

        return $ticket;
    }

    private function notifyDepartmentHead(Ticket $ticket, string $note): Ticket
    {
        $head = $this->resolveDepartmentHead($ticket);

        if ($head) {
            $this->tickets->addWatcher($ticket, $head->id);

            if ($note !== '') {
                $this->addInternalNote($ticket, $note);
            }
        }

        return $ticket;
    }

    private function assignToTeamLead(Ticket $ticket): Ticket
    {
        $lead = $this->resolveTeamLead($ticket);

        return $lead ? $this->assignTo($ticket, $lead->id) : $ticket;
    }

    private function assignToDepartmentHead(Ticket $ticket): Ticket
    {
        $head = $this->resolveDepartmentHead($ticket);

        return $head ? $this->assignTo($ticket, $head->id) : $ticket;
    }

    private function resolveTeamLead(Ticket $ticket): ?User
    {
        if ($ticket->team?->lead) {
            return $ticket->team->lead;
        }

        return $ticket->team_id
            ? $this->teams->find($ticket->team_id)->lead
            : null;
    }

    private function resolveDepartmentHead(Ticket $ticket): ?User
    {
        if ($ticket->department?->head) {
            return $ticket->department->head;
        }

        return $ticket->department_id
            ? Department::query()->with('head')->find($ticket->department_id)?->head
            : null;
    }
}
