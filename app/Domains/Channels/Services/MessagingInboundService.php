<?php

namespace App\Domains\Channels\Services;

use App\Domains\Billing\Services\BillingService;
use App\Domains\Channels\Models\Channel;
use App\Domains\Channels\Repositories\MessagingSettingRepository;
use App\Domains\Contacts\Services\ContactService;
use App\Domains\Security\Support\AuditRecorder;
use App\Domains\Tickets\Services\TicketService;
use App\Domains\Workspace\Services\TicketPresenceService;
use Illuminate\Support\Str;

class MessagingInboundService
{
    public function __construct(
        private MessagingSettingRepository $settings,
        private ContactService $contacts,
        private TicketService $tickets,
        private ChannelService $channels,
        private TicketPresenceService $presence,
        private TwilioMessagingService $twilio,
        private BillingService $billing,
    ) {
    }

    public function process(array $payload, ?string $token): array
    {
        $this->billing->assertFeature('channels');
        $setting = $this->settings->current();

        if (! hash_equals($setting->webhook_token ?? '', (string) $token)) {
            throw new \InvalidArgumentException('Invalid messaging webhook token.');
        }

        $from = (string) ($payload['From'] ?? '');
        $body = trim((string) ($payload['Body'] ?? ''));
        $messageSid = (string) ($payload['MessageSid'] ?? '');
        $channelType = str_starts_with(strtolower($from), 'whatsapp:')
            ? Channel::TYPE_WHATSAPP
            : Channel::TYPE_SMS;

        if ($body === '') {
            return ['action' => 'ignored'];
        }

        $channel = $this->channels->channelByType($channelType);

        if (! $channel?->is_active) {
            throw new \InvalidArgumentException('Messaging channel is not active.');
        }

        $phone = $this->twilio->stripWhatsAppPrefix($from);
        $contact = $this->contacts->findOrCreateByPhone($phone, $phone);
        $ticket = $this->resolveOpenTicket($contact->id, $channel->id);

        if ($ticket) {
            $message = $this->tickets->addContactMessage(
                $ticket->id,
                $contact->id,
                $body,
                $channel->id,
                $messageSid ?: null,
            );

            $this->presence->pulse($ticket->id);

            return ['action' => 'reply', 'ticket_id' => $ticket->id, 'message_id' => $message->id];
        }

        $ticket = $this->tickets->create([
            'subject' => Str::limit($body, 80) ?: 'Message from '.$phone,
            'description' => $body,
            'contact_id' => $contact->id,
            'channel_id' => $channel->id,
            'priority_id' => null,
            'status_id' => null,
        ], null);

        return ['action' => 'created', 'ticket_id' => $ticket->id];
    }

    private function resolveOpenTicket(int $contactId, int $channelId): ?\App\Domains\Tickets\Models\Ticket
    {
        return \App\Domains\Tickets\Models\Ticket::query()
            ->where('contact_id', $contactId)
            ->where('channel_id', $channelId)
            ->whereHas('status', fn ($q) => $q->where('is_closed', false))
            ->latest('updated_at')
            ->first();
    }
}
