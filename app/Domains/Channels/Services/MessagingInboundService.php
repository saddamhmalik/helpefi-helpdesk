<?php

namespace App\Domains\Channels\Services;

use App\Domains\Billing\Contracts\FeatureEntitlementChecker;
use App\Domains\Channels\Models\Channel;
use App\Domains\Channels\Repositories\MessagingSettingRepository;
use App\Domains\Contacts\Services\ContactService;
use App\Domains\Tickets\Models\TicketMessage;
use App\Domains\Tickets\Services\TicketService;
use App\Domains\Tickets\Services\TicketStatusLookup;
use App\Domains\Workspace\Services\TicketPresenceService;
use App\Support\SecurityEventLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Twilio\Security\RequestValidator;

class MessagingInboundService
{
    public function __construct(
        private MessagingSettingRepository $settings,
        private ContactService $contacts,
        private TicketService $tickets,
        private ChannelService $channels,
        private TicketPresenceService $presence,
        private TwilioMessagingService $twilio,
        private FeatureEntitlementChecker $entitlements,
        private TicketStatusLookup $statusLookup,
    ) {
    }

    public function process(array $payload, ?string $token, Request $request): array
    {
        $this->entitlements->assertFeature('channels');
        $setting = $this->settings->current();

        if (! hash_equals($setting->webhook_token ?? '', (string) $token)) {
            throw new \InvalidArgumentException('Invalid messaging webhook token.');
        }

        $this->assertTwilioSignature($setting, $request);

        $from = (string) ($payload['From'] ?? '');
        $body = trim((string) ($payload['Body'] ?? ''));
        $messageSid = (string) ($payload['MessageSid'] ?? '');
        $channelType = str_starts_with(strtolower($from), 'whatsapp:')
            ? Channel::TYPE_WHATSAPP
            : Channel::TYPE_SMS;

        if ($body === '') {
            return ['action' => 'ignored'];
        }

        if ($messageSid !== '' && TicketMessage::query()->where('external_id', $messageSid)->exists()) {
            return ['action' => 'duplicate'];
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

        $openStatus = $this->statusLookup->defaultOpen()
            ?? $this->tickets->statuses()->first();
        $normalPriority = $this->tickets->priorities()->firstWhere('slug', 'normal')
            ?? $this->tickets->priorities()->first();

        $ticket = $this->tickets->create([
            'subject' => Str::limit($body, 80) ?: 'Message from '.$phone,
            'description' => $body,
            'contact_id' => $contact->id,
            'channel_id' => $channel->id,
            'ticket_status_id' => $openStatus->id,
            'ticket_priority_id' => $normalPriority->id,
        ], null);

        return ['action' => 'created', 'ticket_id' => $ticket->id];
    }

    private function assertTwilioSignature($setting, Request $request): void
    {
        $authToken = $setting->auth_token;

        if (! $authToken) {
            if (app()->environment('production')) {
                SecurityEventLogger::webhookSignatureFailed('twilio', 'missing_auth_token');
                throw new \InvalidArgumentException('Twilio auth token is required.');
            }

            return;
        }

        $signature = (string) $request->header('X-Twilio-Signature', '');

        if ($signature === '') {
            if (app()->environment('production')) {
                SecurityEventLogger::webhookSignatureFailed('twilio', 'missing_signature');
                throw new \InvalidArgumentException('Twilio signature is required.');
            }

            return;
        }

        $validator = new RequestValidator($authToken);

        if (! $validator->validate($signature, $request->fullUrl(), $request->all())) {
            SecurityEventLogger::webhookSignatureFailed('twilio', 'invalid_signature');
            throw new \InvalidArgumentException('Invalid Twilio signature.');
        }
    }

    private function resolveOpenTicket(int $contactId, int $channelId): ?\App\Domains\Tickets\Models\Ticket
    {
        return \App\Domains\Tickets\Models\Ticket::query()
            ->where('contact_id', $contactId)
            ->where('channel_id', $channelId)
            ->whereNull('merged_into_ticket_id')
            ->tap(fn ($query) => $this->statusLookup->restrictToOpenTickets($query))
            ->latest('updated_at')
            ->first();
    }
}
