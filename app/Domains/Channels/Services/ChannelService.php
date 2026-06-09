<?php

namespace App\Domains\Channels\Services;

use App\Domains\Channels\Models\Channel;
use App\Domains\Channels\Models\EmailInbox;
use App\Domains\Channels\Services\Mailbox\EmailQuoteStripper;
use App\Domains\Channels\Repositories\ChannelRepository;
use App\Domains\Contacts\Services\ContactService;
use App\Domains\Security\Support\AuditRecorder;
use App\Domains\Settings\Services\HelpdeskSettingService;
use App\Domains\Tickets\Models\Ticket;
use App\Domains\SideConversations\Services\SideConversationService;
use App\Domains\Tickets\Services\TicketCcService;
use App\Domains\Tickets\Services\TicketService;
use App\Domains\Workspace\Services\TicketPresenceService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;
use InvalidArgumentException;

class ChannelService
{
    public function __construct(
        private ChannelRepository $channels,
        private ContactService $contacts,
        private TicketService $tickets,
        private EmailInboxService $inboxes,
        private EmailThreadService $threads,
        private EmailQuoteStripper $quoteStripper,
        private HelpdeskSettingService $helpdeskSettings,
        private AuditRecorder $audit,
        private TicketCcService $ticketCcs,
        private TicketPresenceService $presence,
        private SideConversationService $sideConversations,
        private InboundEmailPayloadNormalizer $inboundNormalizer,
    ) {
    }

    public function all(): Collection
    {
        return $this->channels->all();
    }

    public function update(int $id, array $data): Channel
    {
        $channel = $this->channels->find($id);

        if (isset($data['settings']) && is_array($data['settings'])) {
            $data['settings'] = $this->mergeChannelSettings($channel, $data['settings']);
        }

        $before = $channel->only(array_keys($data));
        $channel = $this->channels->update($channel, $data);

        $this->audit->recordChanges('channel.updated', $channel, $before, $channel->only(array_keys($data)), [
            'slug' => $channel->slug,
        ]);

        return $channel;
    }

    public function defaultWebChannel(): Channel
    {
        return $this->channels->findActiveBySlug('web')
            ?? throw new InvalidArgumentException('Web channel is not configured.');
    }

    public function portalChannel(): Channel
    {
        return $this->channels->findActiveBySlug('portal')
            ?? $this->defaultWebChannel();
    }

    public function emailChannel(): ?Channel
    {
        return $this->channels->findActiveBySlug('email');
    }

    public function chatChannel(): ?Channel
    {
        return $this->channels->findActiveBySlug('chat');
    }

    public function processInboundEmail(array $payload, ?string $token = null, bool $fromPoll = false): array
    {
        $inbox = $this->inboxes->resolveForInbound($token, $payload['to_email'] ?? null);

        if (! $inbox) {
            throw new InvalidArgumentException('No matching email inbox found.');
        }

        $channel = $this->emailChannel();

        if (! $channel) {
            throw new InvalidArgumentException('Email channel is not active.');
        }

        $this->assertInboundToken($inbox, $token);

        if (! $fromPoll && in_array($inbox->inbound_method, ['poll', 'oauth'], true)) {
            throw new InvalidArgumentException('This inbox uses mailbox polling. Webhook delivery is disabled.');
        }

        if ($this->helpdeskSettings->isEmailBlocked($payload['from_email'])) {
            return ['action' => 'blocked', 'ticket' => null, 'message' => null, 'inbox_id' => $inbox->id];
        }

        if ($this->inboundNormalizer->shouldSkip($payload)) {
            return ['action' => 'ignored', 'ticket' => null, 'message' => null, 'inbox_id' => $inbox->id];
        }

        $payload = $this->inboundNormalizer->normalize($payload);

        if ($this->helpdeskSettings->isEmailBlocked($payload['from_email'])) {
            return ['action' => 'blocked', 'ticket' => null, 'message' => null, 'inbox_id' => $inbox->id];
        }

        $payload['body'] = $this->quoteStripper->strip($payload['body']);

        $externalId = isset($payload['message_id'])
            ? EmailThreadService::normalizeMessageId($payload['message_id'])
            : null;

        if ($externalId && $this->sideConversations->messageExistsByExternalId($externalId)) {
            return ['action' => 'duplicate', 'ticket' => null, 'message' => null, 'inbox_id' => $inbox->id];
        }

        if ($externalId && $this->channels->messageExistsByExternalId($channel->id, $externalId)) {
            return ['action' => 'duplicate', 'ticket' => null, 'message' => null, 'inbox_id' => $inbox->id];
        }

        $sideConversation = $this->sideConversations->resolveFromInbound($payload);

        if ($sideConversation) {
            $message = $this->sideConversations->addInboundMessage(
                $sideConversation,
                $payload['from_email'],
                $payload['from_name'] ?? null,
                $payload['body'],
                $externalId,
            );

            return [
                'action' => 'side_reply',
                'ticket' => $this->tickets->show($sideConversation->ticket_id),
                'side_conversation_id' => $sideConversation->id,
                'message' => $message,
                'inbox_id' => $inbox->id,
            ];
        }

        $contact = $this->contacts->findOrCreateByEmail(
            $payload['from_email'],
            $payload['from_name'] ?? $payload['from_email'],
        );

        $ticket = $this->threads->resolveTicket($channel, $contact, $inbox, $payload);

        if ($ticket && $this->helpdeskSettings->emailCreateTicketOnSubjectChange()) {
            $incomingSubject = $this->threads->cleanSubject($payload['subject'] ?? '');
            $existingSubject = $this->threads->cleanSubject($ticket->subject ?? '');

            if ($incomingSubject !== '' && $existingSubject !== '' && $incomingSubject !== $existingSubject) {
                $ticket = null;
            }
        }

        if ($ticket) {
            $message = $this->tickets->addContactMessage(
                $ticket->id,
                $contact->id,
                $payload['body'],
                $channel->id,
                $externalId,
            );

            $this->tickets->storeInboundAttachments($ticket->id, $message, $payload['attachments'] ?? []);
            $this->syncInboundCcs($ticket, $payload);
            $this->presence->pulse($ticket->id);

            return ['action' => 'reply', 'ticket' => $this->tickets->show($ticket->id), 'message' => $message, 'inbox_id' => $inbox->id];
        }

        $openStatus = $this->tickets->statuses()->firstWhere('slug', 'open')
            ?? $this->tickets->statuses()->first();
        $normalPriority = $this->tickets->priorities()->firstWhere('slug', 'normal')
            ?? $this->tickets->priorities()->first();

        $ticket = $this->tickets->create([
            'subject' => $this->threads->cleanSubject($payload['subject'] ?? 'Email request'),
            'description' => null,
            'contact_id' => $contact->id,
            'ticket_status_id' => $openStatus->id,
            'ticket_priority_id' => $normalPriority->id,
            'channel_id' => $channel->id,
            'email_inbox_id' => $inbox->id,
            'brand_id' => $inbox->brand_id,
            'department_id' => $inbox->department_id,
            'team_id' => $inbox->team_id,
        ]);

        $message = $this->tickets->addContactMessage(
            $ticket->id,
            $contact->id,
            $payload['body'],
            $channel->id,
            $externalId,
        );

        $this->tickets->storeInboundAttachments($ticket->id, $message, $payload['attachments'] ?? []);
        $this->syncInboundCcs($ticket, $payload);
        $this->presence->pulse($ticket->id);

        return ['action' => 'created', 'ticket' => $this->tickets->show($ticket->id), 'message' => $message, 'inbox_id' => $inbox->id];
    }

    private function syncInboundCcs(Ticket $ticket, array $payload): void
    {
        $ccEmails = $payload['cc_emails'] ?? [];

        if ($ccEmails === []) {
            return;
        }

        $this->ticketCcs->mergeFromInbound($ticket->fresh(['contact']), $ccEmails);
    }

    public function extractTicketNumber(string $subject): ?string
    {
        return $this->threads->extractTicketNumber($subject);
    }

    private function mergeChannelSettings(Channel $channel, array $incoming): array
    {
        $merged = array_merge($channel->settings ?? [], $incoming);

        if ($channel->type === Channel::TYPE_CHAT) {
            $existingKey = $channel->settings['widget_key'] ?? null;
            $merged['widget_key'] = filled($existingKey) ? $existingKey : Str::random(32);
        }

        return $merged;
    }

    private function assertInboundToken(EmailInbox $inbox, ?string $token): void
    {
        if (! $token) {
            throw new InvalidArgumentException('Invalid inbound channel token.');
        }

        if ($token !== $inbox->inbound_token) {
            throw new InvalidArgumentException('Invalid inbound channel token.');
        }
    }
}
