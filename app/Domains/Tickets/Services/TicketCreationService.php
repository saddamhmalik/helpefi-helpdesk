<?php

namespace App\Domains\Tickets\Services;

use App\Domains\Assignment\Services\AssignmentService;
use App\Domains\Billing\Contracts\FeatureEntitlementChecker;
use App\Domains\Channels\Repositories\ChannelRepository;
use App\Domains\Security\Support\AuditRecorder;
use App\Domains\Tickets\Events\TicketCreated;
use App\Domains\Tickets\Jobs\ProcessTicketCreationSideEffectsJob;
use App\Domains\Sla\Services\SlaService;
use App\Domains\Tickets\Models\Ticket;
use App\Domains\Tickets\Repositories\TicketRepository;
use App\Domains\Tickets\Support\MessageBodySanitizer;
use App\Domains\Tickets\Services\TicketStatusLookup;

class TicketCreationService
{
    public function __construct(
        private TicketRepository $tickets,
        private SlaService $sla,
        private ChannelRepository $channels,
        private FeatureEntitlementChecker $entitlements,
        private AuditRecorder $audit,
        private AssignmentService $assignment,
        private TicketCcService $ticketCcs,
        private TicketPeopleFieldResolver $peopleFields,
        private TicketRealtimeBroadcaster $realtime,
        private TicketStatusLookup $statusLookup,
    ) {
    }

    public function create(array $data, ?int $userId = null): Ticket
    {
        $this->entitlements->assertLimit('tickets_monthly', 1);

        [$ccEmails, $requesterEmail, $requesterName] = $this->peopleFields->extractPeopleFields($data);
        $data = $this->peopleFields->resolveRequester($data, $requesterEmail, $requesterName, $userId);

        if (empty($data['channel_id'])) {
            $data['channel_id'] = $this->channels->findActiveBySlug('web')?->id;
        }

        $data = $this->peopleFields->applyWorkforceRouting($data);
        $data = $this->assignment->enrichUnassignedTicket($data);

        if (array_key_exists('custom_fields', $data)) {
            $data['custom_fields'] = $this->peopleFields->resolveTicketCustomFields($data);
        }

        if (array_key_exists('description', $data)) {
            $data['description'] = $this->peopleFields->normalizeRichText($data['description']);
        }

        if (empty($data['ticket_status_id'])) {
            $data['ticket_status_id'] = $this->statusLookup->defaultOpen()?->id
                ?? $this->tickets->statuses()->first()?->id;
        }

        if (empty($data['ticket_priority_id'])) {
            $data['ticket_priority_id'] = $this->tickets->priorities()->firstWhere('slug', 'normal')?->id
                ?? $this->tickets->priorities()->first()?->id;
        }

        $ticket = $this->tickets->create($data);
        $this->ticketCcs->sync($ticket, $ccEmails, $userId);
        $this->sla->applyToTicket($ticket);
        $this->createInitialMessageFromDescription($ticket, $userId);

        $ticket->load(['status', 'priority', 'contact', 'assignee', 'channel']);
        TicketCreated::dispatch($ticket);

        $this->audit->record('ticket.created', $ticket, [
            'number' => $ticket->number,
            'subject' => $ticket->subject,
        ], $userId);

        ProcessTicketCreationSideEffectsJob::dispatch($ticket->id, $userId);

        $this->realtime->broadcastTicketSnapshot($this->tickets->findForBroadcast($ticket->id));

        return $ticket;
    }

    private function createInitialMessageFromDescription(Ticket $ticket, ?int $userId): void
    {
        $description = $ticket->description;

        if ($description === null || MessageBodySanitizer::isEmpty($description)) {
            return;
        }

        $channelId = $ticket->channel_id ?? $this->channels->findActiveBySlug('web')?->id;

        if ($ticket->contact_id) {
            $this->tickets->addMessage($ticket, [
                'contact_id' => $ticket->contact_id,
                'body' => $description,
                'is_internal' => false,
                'channel_id' => $channelId,
            ]);

            return;
        }

        if ($userId) {
            $this->tickets->addMessage($ticket, [
                'user_id' => $userId,
                'body' => $description,
                'is_internal' => false,
                'channel_id' => $channelId,
            ]);
        }
    }
}
