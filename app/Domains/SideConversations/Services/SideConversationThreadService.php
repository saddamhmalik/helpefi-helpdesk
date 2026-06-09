<?php

namespace App\Domains\SideConversations\Services;

use App\Domains\SideConversations\Models\SideConversation;
use App\Domains\SideConversations\Repositories\SideConversationRepository;
use App\Domains\Tickets\Models\Ticket;

class SideConversationThreadService
{
    public function __construct(
        private SideConversationRepository $conversations,
    ) {
    }

    public function resolveFromInbound(array $payload): ?SideConversation
    {
        $sideConversationId = $this->extractSideConversationId($payload['subject'] ?? '');

        if ($sideConversationId) {
            return $this->conversations->findById($sideConversationId);
        }

        $referenceIds = $this->referenceMessageIds($payload);

        if ($referenceIds !== []) {
            $conversation = $this->conversations->findByMessageReferences($referenceIds);

            if ($conversation) {
                return $conversation;
            }
        }

        if (! preg_match('/^(re|fwd):\s*/i', $payload['subject'] ?? '')) {
            return null;
        }

        $subject = $this->normalizeSubject($payload['subject'] ?? '');

        return $this->conversations->findOpenByRecipientAndSubject(
            $payload['from_email'] ?? '',
            $subject,
        );
    }

    public function referenceMessageIds(array $payload): array
    {
        $ids = [];

        foreach (['in_reply_to', 'references'] as $key) {
            foreach ($payload[$key] ?? [] as $id) {
                $normalized = self::normalizeMessageId((string) $id);

                if ($normalized !== '') {
                    $ids[] = $normalized;
                }
            }
        }

        return array_values(array_unique($ids));
    }

    public static function normalizeMessageId(string $id): string
    {
        return strtolower(trim($id, " <>\\t\\n\\r"));
    }

    public static function outboundMessageId(int $sideConversationId, int $messageId): string
    {
        $host = parse_url(config('app.url'), PHP_URL_HOST) ?: 'helpdesk.local';

        return "side.{$sideConversationId}.message.{$messageId}@{$host}";
    }

    public static function subjectTag(Ticket $ticket, int $sideConversationId): string
    {
        return "[{$ticket->number} Side #{$sideConversationId}]";
    }

    public static function emailSubject(Ticket $ticket, SideConversation $conversation, bool $reply = false): string
    {
        $tag = self::subjectTag($ticket, $conversation->id);
        $prefix = $reply ? 'Re: ' : '';

        return "{$prefix}{$tag} {$conversation->subject}";
    }

    public function extractSideConversationId(string $subject): ?int
    {
        if (preg_match('/Side\s#(\d+)/i', $subject, $matches)) {
            return (int) $matches[1];
        }

        return null;
    }

    public function normalizeSubject(string $subject): string
    {
        $subject = preg_replace('/^(re|fwd):\s*/i', '', $subject) ?? $subject;
        $subject = preg_replace('/\[[^\]]*Side\s#\d+[^\]]*\]/i', '', $subject) ?? $subject;
        $pattern = app(\App\Domains\Settings\Services\HelpdeskSettingService::class)->ticketNumberPattern();
        $subject = preg_replace($pattern, '', $subject) ?? $subject;

        return trim($subject);
    }
}
