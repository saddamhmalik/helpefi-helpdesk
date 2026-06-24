<?php

namespace App\Domains\Tickets\Services;

use App\Domains\Channels\Repositories\ChannelRepository;
use App\Domains\Security\Support\AuditRecorder;
use App\Domains\Tickets\Models\Ticket;
use App\Domains\Tickets\Repositories\TicketRepository;
use App\Domains\Tickets\Services\TicketRealtimeBroadcaster;
use InvalidArgumentException;

class TicketMergeService
{
    public function __construct(
        private TicketRepository $tickets,
        private TicketMergePersistence $mergePersistence,
        private TicketCcService $ticketCcs,
        private AuditRecorder $audit,
        private ChannelRepository $channels,
        private TicketPeopleFieldResolver $peopleFields,
        private TicketRealtimeBroadcaster $realtime,
    ) {
    }

    public function merge(int $targetId, int $sourceId, int $userId, bool $importConversation = true): Ticket
    {
        if ($targetId === $sourceId) {
            throw new InvalidArgumentException('Cannot merge a ticket into itself.');
        }

        $target = $this->tickets->findForMerge($targetId);
        $source = $this->tickets->findForMerge($sourceId);

        if ($source->merged_into_ticket_id) {
            throw new InvalidArgumentException('Source ticket is already merged.');
        }

        if ($target->merged_into_ticket_id) {
            throw new InvalidArgumentException('Target ticket is already merged.');
        }

        $sourceHadMessages = $source->messages()->exists();

        $merged = $this->mergePersistence->execute($target, $source, $userId, $importConversation);

        if ($importConversation && ! $sourceHadMessages) {
            $this->importMergedTicketDescription($merged, $source);
        }

        $this->ticketCcs->mergeFromTicket($merged, $source, $userId);

        $this->audit->record('ticket.merged', $merged, [
            'target_number' => $merged->number,
            'source_number' => $source->number,
            'source_id' => $source->id,
            'import_conversation' => $importConversation,
        ], $userId);

        $broadcast = $this->tickets->findForBroadcast($merged->id);
        $this->realtime->broadcastTicketSnapshot($broadcast);

        return $broadcast;
    }

    private function importMergedTicketDescription(Ticket $target, Ticket $source): void
    {
        $description = $this->peopleFields->normalizeRichText($source->description);

        if ($description === null) {
            return;
        }

        $channelId = $source->channel_id ?? $target->channel_id ?? $this->channels->findActiveBySlug('web')?->id;

        $payload = [
            'body' => $description,
            'is_internal' => false,
            'channel_id' => $channelId,
            'merged_from_ticket_id' => $source->id,
        ];

        if ($source->contact_id) {
            $payload['contact_id'] = $source->contact_id;
        }

        $this->tickets->addMessage($target, $payload);
    }
}
