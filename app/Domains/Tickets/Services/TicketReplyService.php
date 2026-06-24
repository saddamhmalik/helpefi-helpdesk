<?php

namespace App\Domains\Tickets\Services;

use App\Domains\Channels\Repositories\ChannelRepository;
use App\Domains\Channels\Services\ClosedTicketInboundReopenService;
use App\Domains\Notifications\Services\NotificationService;
use App\Domains\Security\Support\AuditRecorder;
use App\Domains\Settings\Services\AutoFirstResponseService;
use App\Domains\Sla\Services\SlaService;
use App\Domains\Tickets\Events\TicketCustomerMessageReceived;
use App\Domains\Tickets\Models\TicketMessage;
use App\Domains\Tickets\Repositories\TicketRepository;
use App\Domains\Tickets\Support\MessageBodySanitizer;
use App\Domains\Tickets\Support\TicketAttachmentRules;
use App\Domains\Workspace\Services\TicketPresenceService;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use InvalidArgumentException;

class TicketReplyService
{
    public function __construct(
        private TicketRepository $tickets,
        private TicketAttachmentService $attachments,
        private ChannelRepository $channels,
        private SlaService $sla,
        private TicketOutboundDeliveryService $outboundDelivery,
        private AuditRecorder $audit,
        private ClosedTicketInboundReopenService $closedTicketReopen,
        private TicketPresenceService $presence,
        private TicketRealtimeBroadcaster $realtime,
        private NotificationService $notifications,
        private AutoFirstResponseService $autoFirstResponse,
    ) {
    }

    public function reply(int $id, int $userId, string $body, bool $isInternal = false, array $attachments = []): TicketMessage
    {
        $body = MessageBodySanitizer::sanitize($body);
        $uploads = array_values(array_filter($attachments, fn ($file) => $file instanceof UploadedFile));

        if (MessageBodySanitizer::isEmpty($body) && $uploads === []) {
            throw new InvalidArgumentException('Message body or attachment is required.');
        }

        $ticket = $this->tickets->findForWrite($id);

        if (! $isInternal && $this->closedTicketReopen->maybeReopenOnAgentReply($ticket)) {
            $ticket->refresh();
            $this->realtime->broadcastTicketSnapshot($this->tickets->findForBroadcast($id));
        }

        $channelId = $ticket->channel_id && $ticket->channel?->type === 'email'
            ? $ticket->channel_id
            : $this->channels->findActiveBySlug('web')?->id;

        $message = $this->tickets->addMessage($ticket, [
            'user_id' => $userId,
            'body' => MessageBodySanitizer::isEmpty($body) ? '' : $body,
            'is_internal' => $isInternal,
            'channel_id' => $channelId,
        ]);

        foreach ($uploads as $file) {
            $this->attachments->addFromUpload($ticket, $message, $userId, $file);
        }

        $message->load('attachments');

        $user = User::query()->find($userId);

        if (! $isInternal && $this->sla->isAgentUser($user)) {
            $this->sla->recordFirstResponse($ticket);
            $this->outboundDelivery->deliverAgentReply($ticket, $message, $user);
        }

        $this->audit->record('ticket.replied', $ticket, [
            'number' => $ticket->number,
            'message_id' => $message->id,
            'is_internal' => $isInternal,
        ], $userId);

        $this->presence->pulse($id);
        $this->realtime->broadcastMessage($message, ! $isInternal);

        return $message;
    }

    public function addInternalNote(int $ticketId, string $body, ?int $userId = null): ?TicketMessage
    {
        if ($body === '') {
            return null;
        }

        $ticket = $this->tickets->findForWrite($ticketId);

        return $this->tickets->addMessage($ticket, [
            'user_id' => $userId,
            'body' => $body,
            'is_internal' => true,
            'channel_id' => $this->channels->findActiveBySlug('web')?->id,
        ]);
    }

    public function addContactMessage(
        int $ticketId,
        int $contactId,
        string $body,
        int $channelId,
        ?string $externalId = null,
        bool $fromEmail = false,
    ): TicketMessage {
        $body = MessageBodySanitizer::sanitize($body);
        $ticket = $this->tickets->findForWrite($ticketId);

        if ($this->closedTicketReopen->maybeReopenOnCustomerMessage($ticket, $body, $fromEmail)) {
            $ticket->refresh();
            $this->realtime->broadcastTicketSnapshot($this->tickets->findForBroadcast($ticketId));
        }

        $message = $this->tickets->addMessage($ticket, [
            'contact_id' => $contactId,
            'body' => $body,
            'is_internal' => false,
            'channel_id' => $channelId,
            'external_id' => $externalId,
        ]);

        $ticket->refresh();

        TicketCustomerMessageReceived::dispatch($ticket, $message);

        $this->notifications->customerReply($ticket, $message);

        $isFirstCustomerMessage = ! TicketMessage::query()
            ->where('ticket_id', $ticketId)
            ->where('id', '<', $message->id)
            ->whereNotNull('contact_id')
            ->exists();

        if ($isFirstCustomerMessage) {
            $this->autoFirstResponse->sendIfEnabled($ticket, $message);
        }

        $this->audit->record('ticket.customer_message', $ticket, [
            'number' => $ticket->number,
            'message_id' => $message->id,
            'contact_id' => $contactId,
        ]);

        $this->presence->pulse($ticketId);
        $this->realtime->broadcastMessage($message, true);

        return $message;
    }

    public function storeInboundAttachments(int $ticketId, TicketMessage $message, array $attachments): void
    {
        if ($attachments === []) {
            return;
        }

        $ticket = $this->tickets->findForWrite($ticketId);
        $seen = [];
        $filenames = [];
        $stored = 0;

        foreach ($attachments as $attachment) {
            if (! empty($attachment['filename'])) {
                $filenames[] = $attachment['filename'];
            }
        }

        $existing = $this->attachments->existingFingerprints($ticket, array_unique($filenames));

        foreach ($attachments as $attachment) {
            if ($stored >= 5) {
                break;
            }

            if (empty($attachment['filename']) || empty($attachment['content'])) {
                continue;
            }

            $raw = $attachment['content'];
            $decoded = base64_decode($raw, true);
            $content = ($decoded !== false && base64_encode($decoded) === $raw) ? $decoded : $raw;

            if ($content === false || $content === '') {
                continue;
            }

            $filename = $attachment['filename'];
            $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

            if (! in_array($extension, TicketAttachmentRules::ALLOWED_MIMES, true)) {
                continue;
            }

            $size = strlen($content);

            if ($size > TicketAttachmentRules::MAX_SIZE_KB * 1024) {
                continue;
            }

            $fingerprint = hash('sha256', $filename.'|'.$size.'|'.hash('sha256', $content));

            if (isset($seen[$fingerprint]) || $existing->has($filename.'|'.$size)) {
                continue;
            }

            $seen[$fingerprint] = true;

            $this->attachments->addFromContent(
                $ticket,
                $message,
                $filename,
                $content,
                $attachment['mime_type'] ?? null,
            );

            $stored++;
        }
    }
}
