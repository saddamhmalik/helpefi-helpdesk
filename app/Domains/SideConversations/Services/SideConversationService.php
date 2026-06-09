<?php

namespace App\Domains\SideConversations\Services;

use App\Domains\Channels\Services\OutboundMailService;
use App\Domains\Security\Support\AuditRecorder;
use App\Domains\SideConversations\Models\SideConversation;
use App\Domains\SideConversations\Models\SideConversationMessage;
use App\Domains\SideConversations\Repositories\SideConversationRepository;
use App\Domains\Tickets\Repositories\TicketRepository;
use App\Domains\Tickets\Support\MessageBodySanitizer;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use InvalidArgumentException;

class SideConversationService
{
    public function __construct(
        private SideConversationRepository $conversations,
        private SideConversationThreadService $threads,
        private TicketRepository $tickets,
        private OutboundMailService $outboundMail,
        private AuditRecorder $audit,
    ) {
    }

    public function listForTicket(int $ticketId): Collection
    {
        return $this->conversations->forTicket($ticketId);
    }

    public function create(
        int $ticketId,
        int $userId,
        string $recipientEmail,
        ?string $recipientName,
        string $subject,
        string $body,
    ): SideConversation {
        $ticket = $this->tickets->find($ticketId);
        $recipientEmail = strtolower(trim($recipientEmail));
        $subject = trim($subject);
        $body = MessageBodySanitizer::sanitize($body);

        if ($recipientEmail === '' || ! filter_var($recipientEmail, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('A valid recipient email is required.');
        }

        if ($subject === '') {
            throw new InvalidArgumentException('Subject is required.');
        }

        if (trim(strip_tags($body)) === '') {
            throw new InvalidArgumentException('Message body is required.');
        }

        $conversation = $this->conversations->create([
            'ticket_id' => $ticket->id,
            'subject' => $subject,
            'recipient_email' => $recipientEmail,
            'recipient_name' => $recipientName ? trim($recipientName) : null,
            'status' => SideConversation::STATUS_OPEN,
            'created_by' => $userId,
        ]);

        $message = $this->conversations->addMessage($conversation, [
            'user_id' => $userId,
            'body' => $body,
            'is_inbound' => false,
        ]);

        $this->outboundMail->sendSideConversation($conversation->fresh(['ticket']), $message, User::query()->findOrFail($userId));

        $this->audit->record('side_conversation.created', $conversation, [
            'ticket_id' => $ticket->id,
            'recipient_email' => $recipientEmail,
        ], $userId);

        return $this->conversations->find($conversation->id);
    }

    public function reply(int $ticketId, int $conversationId, int $userId, string $body): SideConversationMessage
    {
        $conversation = $this->conversations->findForTicket($ticketId, $conversationId);

        if ($conversation->status !== SideConversation::STATUS_OPEN) {
            throw new InvalidArgumentException('This side conversation is closed.');
        }

        $body = MessageBodySanitizer::sanitize($body);

        if (trim(strip_tags($body)) === '') {
            throw new InvalidArgumentException('Message body is required.');
        }

        $message = $this->conversations->addMessage($conversation, [
            'user_id' => $userId,
            'body' => $body,
            'is_inbound' => false,
        ]);

        $this->outboundMail->sendSideConversation($conversation->fresh(['ticket']), $message, User::query()->findOrFail($userId));

        $this->audit->record('side_conversation.replied', $conversation, [
            'message_id' => $message->id,
        ], $userId);

        return $message->load('user:id,name,email');
    }

    public function close(int $ticketId, int $conversationId, int $userId): SideConversation
    {
        $conversation = $this->conversations->findForTicket($ticketId, $conversationId);

        if ($conversation->status === SideConversation::STATUS_CLOSED) {
            return $conversation;
        }

        $updated = $this->conversations->update($conversation, [
            'status' => SideConversation::STATUS_CLOSED,
            'closed_at' => now(),
        ]);

        $this->audit->record('side_conversation.closed', $updated, [], $userId);

        return $updated;
    }

    public function resolveFromInbound(array $payload): ?SideConversation
    {
        return $this->threads->resolveFromInbound($payload);
    }

    public function messageExistsByExternalId(string $externalId): bool
    {
        return $this->conversations->messageExistsByExternalId($externalId);
    }

    public function addInboundMessage(
        SideConversation $conversation,
        string $fromEmail,
        ?string $fromName,
        string $body,
        ?string $externalId = null,
    ): SideConversationMessage {
        if ($conversation->status === SideConversation::STATUS_CLOSED) {
            $conversation = $this->conversations->update($conversation, [
                'status' => SideConversation::STATUS_OPEN,
                'closed_at' => null,
            ]);
        }

        if ($fromName && ! $conversation->recipient_name) {
            $this->conversations->update($conversation, ['recipient_name' => trim($fromName)]);
        }

        $message = $this->conversations->addMessage($conversation, [
            'body' => MessageBodySanitizer::sanitize($body),
            'is_inbound' => true,
            'external_id' => $externalId,
        ]);

        $this->audit->record('side_conversation.inbound', $conversation, [
            'from_email' => strtolower(trim($fromEmail)),
            'message_id' => $message->id,
        ]);

        return $message;
    }
}
