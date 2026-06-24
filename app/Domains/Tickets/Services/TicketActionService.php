<?php

namespace App\Domains\Tickets\Services;

use App\Domains\Channels\Repositories\ChannelRepository;
use App\Domains\Tickets\Models\Ticket;
use App\Domains\Tickets\Models\TicketMessage;
use App\Domains\Tickets\Repositories\TicketRepository;
use App\Domains\Workforce\Models\Department;
use App\Domains\Workforce\Repositories\TeamRepository;
use App\Models\User;

class TicketActionService
{
    public function __construct(
        private TicketRepository $tickets,
        private TicketUpdateService $updates,
        private TicketWatcherService $watchers,
        private TicketReplyService $replies,
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
        $ticket = $this->tickets->findForWrite($ticket->id);

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
        return $this->updates->update($ticket->id, ['ticket_status_id' => $statusId], null, [
            'source' => 'escalation',
        ]);
    }

    private function setPriority(Ticket $ticket, int $priorityId): Ticket
    {
        return $this->updates->update($ticket->id, ['ticket_priority_id' => $priorityId], null, [
            'source' => 'escalation',
        ]);
    }

    private function assignTo(Ticket $ticket, ?int $userId): Ticket
    {
        return $this->updates->update($ticket->id, ['assigned_to' => $userId], null, [
            'source' => 'escalation',
        ]);
    }

    private function addWatcher(Ticket $ticket, int $userId): Ticket
    {
        if ($userId) {
            $this->watchers->addWatcher($ticket->id, $userId);
        }

        return $this->tickets->findForWrite($ticket->id);
    }

    private function addInternalNote(Ticket $ticket, string $body): Ticket
    {
        $this->replies->addInternalNote($ticket->id, $body);

        return $this->tickets->findForWrite($ticket->id);
    }

    private function notifyTeamLead(Ticket $ticket, string $note): Ticket
    {
        $lead = $this->resolveTeamLead($ticket);

        if ($lead) {
            $this->watchers->addWatcher($ticket->id, $lead->id);

            if ($note !== '') {
                $this->replies->addInternalNote($ticket->id, $note);
            }
        }

        return $this->tickets->findForWrite($ticket->id);
    }

    private function notifyDepartmentHead(Ticket $ticket, string $note): Ticket
    {
        $head = $this->resolveDepartmentHead($ticket);

        if ($head) {
            $this->watchers->addWatcher($ticket->id, $head->id);

            if ($note !== '') {
                $this->replies->addInternalNote($ticket->id, $note);
            }
        }

        return $this->tickets->findForWrite($ticket->id);
    }

    private function assignToTeamLead(Ticket $ticket): Ticket
    {
        $lead = $this->resolveTeamLead($ticket);

        return $lead
            ? $this->updates->update($ticket->id, ['assigned_to' => $lead->id], null, ['source' => 'escalation'])
            : $ticket;
    }

    private function assignToDepartmentHead(Ticket $ticket): Ticket
    {
        $head = $this->resolveDepartmentHead($ticket);

        return $head
            ? $this->updates->update($ticket->id, ['assigned_to' => $head->id], null, ['source' => 'escalation'])
            : $ticket;
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
