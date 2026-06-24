<?php

namespace App\Domains\Automation\Services;

use App\Domains\Channels\Repositories\ChannelRepository;
use App\Domains\Contacts\Repositories\TagRepository;
use App\Domains\Integrations\Services\WebhookService;
use App\Domains\Sla\Services\SlaService;
use App\Domains\Tickets\Models\Ticket;
use App\Domains\Tickets\Repositories\TicketRepository;
use App\Domains\Tickets\Services\TicketStatusLookup;
use App\Domains\Workforce\Services\WorkforceService;

class AutomationActionExecutor
{
    public function __construct(
        private TicketRepository $tickets,
        private TicketStatusLookup $statusLookup,
        private SlaService $sla,
        private ChannelRepository $channels,
        private TagRepository $tags,
        private WebhookService $webhooks,
        private WorkforceService $workforce,
    ) {
    }

    public function execute(Ticket $ticket, array $action, array $context = []): Ticket
    {
        return match ($action['type'] ?? '') {
            'set_status' => $this->setStatus($ticket, (int) $action['value']),
            'set_priority' => $this->setPriority($ticket, (int) $action['value']),
            'assign_to' => $this->assignTo($ticket, filled($action['value']) ? (int) $action['value'] : null),
            'add_watcher' => $this->addWatcher($ticket, (int) $action['value']),
            'add_internal_note' => $this->addInternalNote($ticket, (string) $action['value']),
            'add_tag' => $this->addTag($ticket, (string) $action['value']),
            'send_webhook' => $this->sendWebhook($ticket, (int) $action['value'], $context),
            default => $ticket,
        };
    }

    private function setStatus(Ticket $ticket, int $statusId): Ticket
    {
        $isClosed = $this->statusLookup->isClosedId($statusId);
        $data = [
            'ticket_status_id' => $statusId,
            'closed_at' => $isClosed ? now() : null,
        ];

        $ticket = $this->tickets->update($ticket, $data);

        if ($isClosed) {
            $this->sla->recordResolution($ticket);
        }

        return $this->tickets->find($ticket->id);
    }

    private function setPriority(Ticket $ticket, int $priorityId): Ticket
    {
        return $this->tickets->update($ticket, ['ticket_priority_id' => $priorityId]);
    }

    private function assignTo(Ticket $ticket, ?int $userId): Ticket
    {
        if ($userId && ! in_array($userId, $this->workforce->assignableAgentIds(), true)) {
            return $ticket;
        }

        return $this->tickets->update($ticket, ['assigned_to' => $userId]);
    }

    private function addWatcher(Ticket $ticket, int $userId): Ticket
    {
        if (! in_array($userId, $this->workforce->assignableAgentIds(), true)) {
            return $ticket;
        }

        $this->tickets->addWatcher($ticket, $userId);

        return $this->tickets->find($ticket->id);
    }

    private function addInternalNote(Ticket $ticket, string $body): Ticket
    {
        $channelId = $this->channels->findActiveBySlug('web')?->id;

        $this->tickets->addMessage($ticket, [
            'body' => $body,
            'is_internal' => true,
            'channel_id' => $channelId,
        ]);

        return $this->tickets->find($ticket->id);
    }

    private function addTag(Ticket $ticket, string $tagName): Ticket
    {
        $tagName = trim($tagName);

        if ($tagName === '') {
            return $ticket;
        }

        $this->tags->attachToTicket($ticket->id, $this->tags->firstOrCreateByName($tagName)->id);

        return $this->tickets->find($ticket->id);
    }

    private function sendWebhook(Ticket $ticket, int $webhookId, array $context): Ticket
    {
        $this->webhooks->deliverAutomation($webhookId, $ticket, $context);

        return $ticket;
    }
}
