<?php

namespace App\Domains\Channels\Services;

use App\Domains\Settings\Services\HelpdeskSettingService;
use App\Domains\Channels\Models\Channel;
use App\Domains\Channels\Models\EmailInbox;
use App\Domains\Channels\Repositories\ChannelRepository;
use App\Domains\Contacts\Models\Contact;
use App\Domains\Tickets\Models\Ticket;
use Illuminate\Support\Str;

class EmailThreadService
{
    public function __construct(
        private ChannelRepository $channels,
        private HelpdeskSettingService $helpdeskSettings,
    ) {
    }

    public function resolveTicket(Channel $channel, Contact $contact, EmailInbox $inbox, array $payload): ?Ticket
    {
        if (! $this->helpdeskSettings->emailIgnoreTicketIdThreading()) {
            $ticketNumber = $payload['ticket_number'] ?? $this->extractTicketNumber($payload['subject'] ?? '');

            if ($ticketNumber) {
                $ticket = $this->channels->findTicketByNumber($ticketNumber);

                if ($ticket) {
                    return $ticket;
                }
            }
        }

        $referenceIds = $this->referenceMessageIds($payload);

        if ($referenceIds !== []) {
            $ticket = $this->channels->findTicketByMessageReferences($channel->id, $referenceIds);

            if ($ticket) {
                return $ticket;
            }
        }

        if (preg_match('/^(re|fwd):\s*/i', $payload['subject'] ?? '')) {
            $ticket = $this->channels->findTicketBySubjectAndContact(
                $channel->id,
                $contact->id,
                $inbox->id,
                self::normalizeSubject($payload['subject'] ?? ''),
            );

            if ($ticket) {
                return $ticket;
            }

            return $this->channels->findTicketByCcEmail(
                $channel->id,
                $payload['from_email'] ?? '',
            );
        }

        return null;
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

    public static function outboundMessageId(Ticket $ticket, int $messageId): string
    {
        $host = parse_url(config('app.url'), PHP_URL_HOST) ?: 'helpdesk.local';

        return "ticket.{$ticket->id}.message.{$messageId}@{$host}";
    }

    public function extractTicketNumber(string $subject): ?string
    {
        if (preg_match($this->helpdeskSettings->ticketNumberPattern(), $subject, $matches)) {
            $prefix = $this->helpdeskSettings->ticketNumberPrefix();

            return strtoupper($prefix.str_pad($matches[1], 5, '0', STR_PAD_LEFT));
        }

        return null;
    }

    public function cleanSubject(string $subject): string
    {
        return self::normalizeSubject($subject);
    }

    public static function normalizeSubject(string $subject): string
    {
        $subject = preg_replace('/^(re|fwd):\s*/i', '', $subject) ?? $subject;
        $pattern = app(HelpdeskSettingService::class)->ticketNumberPattern();
        $subject = preg_replace($pattern, '', $subject) ?? $subject;

        return Str::limit(trim($subject), 255, '');
    }
}
