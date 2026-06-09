<?php

namespace App\Domains\Chat\Services;

use App\Domains\Ai\Services\AiDeflectionService;
use App\Domains\Realtime\Services\RealtimeTokenService;
use App\Domains\Channels\Models\Channel;
use App\Domains\Channels\Repositories\ChannelRepository;
use App\Domains\Chat\Models\ChatSession;
use App\Domains\Chat\Repositories\ChatSessionRepository;
use App\Domains\Contacts\Services\ContactService;
use App\Domains\Tickets\Models\TicketMessage;
use App\Domains\Tickets\Services\TicketService;
use App\Domains\Tickets\Support\MessageBodySanitizer;
use App\Domains\Workspace\Services\TicketPresenceService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class ChatService
{
    public function __construct(
        private ChannelRepository $channels,
        private ChatSessionRepository $sessions,
        private ContactService $contacts,
        private TicketService $tickets,
        private ChatAvailabilityService $availability,
        private TicketPresenceService $presence,
        private AiDeflectionService $deflection,
        private RealtimeTokenService $realtimeTokens,
    ) {
    }

    public function resolveChannel(string $widgetKey): Channel
    {
        $channel = $this->channels->findActiveBySlug('chat');

        if (! $channel || $channel->type !== Channel::TYPE_CHAT) {
            throw new InvalidArgumentException('Live chat is not configured.');
        }

        $expected = $channel->settings['widget_key'] ?? null;

        if (! $expected || ! hash_equals($expected, $widgetKey)) {
            throw new InvalidArgumentException('Invalid widget key.');
        }

        return $channel;
    }

    public function config(Channel $channel): array
    {
        return [
            'online' => $this->availability->isOnline($channel),
            'greeting' => $channel->settings['greeting'] ?? 'Hi! How can we help you today?',
            'offline_message' => $channel->settings['offline_message'] ?? 'We are currently offline.',
            'deflection_enabled' => $this->deflection->isEnabledForChannel('widget'),
            'realtime' => [
                'url' => config('realtime.ws_url'),
            ],
        ];
    }

    public function startSession(Channel $channel, array $data, ?string $userAgent = null): array
    {
        $online = $this->availability->isOnline($channel);

        if (! $online) {
            return $this->handleOffline($channel, $data);
        }

        if (! empty($data['session_uuid'])) {
            $existing = $this->sessions->findByUuid($data['session_uuid']);

            if ($existing && $existing->isOpen() && $existing->channel_id === $channel->id) {
                return $this->sessionPayload($existing, $this->messagesForSession($existing));
            }
        }

        $name = trim((string) ($data['name'] ?? '')) ?: 'Visitor';
        $email = trim((string) ($data['email'] ?? ''));

        if ($email !== '') {
            $contact = $this->contacts->findOrCreateByEmail($email, $name);
            $resume = $this->sessions->findOpenForContact($contact->id, $channel->id);

            if ($resume) {
                $this->sessions->touch($resume, [
                    'page_url' => $data['page_url'] ?? $resume->page_url,
                    'user_agent' => $userAgent ?? $resume->user_agent,
                ]);

                if (! empty($data['message'])) {
                    $this->sendVisitorMessage($resume, $data['message']);
                }

                return $this->sessionPayload($resume, $this->messagesForSession($resume));
            }
        } else {
            $contact = $this->contacts->findOrCreateByEmail(
                'visitor-'.Str::lower(Str::random(12)).'@chat.local',
                $name,
            );
        }

        $message = trim((string) ($data['message'] ?? ''));

        if ($message === '') {
            throw ValidationException::withMessages([
                'message' => 'Please enter a message to start the chat.',
            ]);
        }

        $openStatus = $this->tickets->statuses()->firstWhere('slug', 'open')
            ?? $this->tickets->statuses()->first();
        $normalPriority = $this->tickets->priorities()->firstWhere('slug', 'normal')
            ?? $this->tickets->priorities()->first();

        $ticket = $this->tickets->create([
            'subject' => 'Live chat from '.$name,
            'description' => $message,
            'contact_id' => $contact->id,
            'channel_id' => $channel->id,
            'ticket_status_id' => $openStatus->id,
            'ticket_priority_id' => $normalPriority->id,
        ]);

        $session = $this->sessions->create([
            'uuid' => (string) Str::uuid(),
            'channel_id' => $channel->id,
            'contact_id' => $contact->id,
            'ticket_id' => $ticket->id,
            'token' => Str::random(64),
            'visitor_name' => $name,
            'page_url' => $data['page_url'] ?? null,
            'user_agent' => $userAgent,
            'last_seen_at' => now(),
        ]);

        return $this->sessionPayload($session, $this->messagesForSession($session));
    }

    public function authenticateSession(string $uuid, string $token): ChatSession
    {
        $session = $this->sessions->findByUuid($uuid);

        if (! $session || ! hash_equals($session->token, $token) || ! $session->isOpen()) {
            throw new InvalidArgumentException('Invalid chat session.');
        }

        return $session;
    }

    public function sendMessage(ChatSession $session, string $body): array
    {
        $body = trim($body);

        if ($body === '') {
            throw ValidationException::withMessages([
                'body' => 'Message cannot be empty.',
            ]);
        }

        $this->sendVisitorMessage($session, $body);
        $this->sessions->touch($session);

        return [
            'message' => $this->formatMessage(
                $session->ticket->messages()->latest('id')->first(),
            ),
        ];
    }

    public function poll(ChatSession $session, ?string $since, ?int $sincePulse = null): array
    {
        $this->sessions->touch($session);

        $sinceAt = $since ? Carbon::parse(str_replace(' ', '+', urldecode($since))) : null;
        $ticketChanged = $this->presence->pulseSince($session->ticket_id, $sincePulse);

        $query = TicketMessage::query()
            ->where('ticket_id', $session->ticket_id)
            ->where('is_internal', false)
            ->with(['user:id,name', 'contact:id,name'])
            ->orderBy('created_at');

        if ($sinceAt) {
            $query->where('created_at', '>', $sinceAt);
        }

        return [
            'messages' => $query->get()->map(fn ($message) => $this->formatMessage($message))->values()->all(),
            'ticket_changed' => $ticketChanged,
            'server_time' => now()->toIso8601String(),
            'pulse' => now()->timestamp,
            'realtime' => [
                'url' => config('realtime.ws_url'),
                'channel' => 'chat.'.$session->uuid,
                'token' => $this->realtimeTokens->forChannel('chat.'.$session->uuid),
            ],
        ];
    }

    private function handleOffline(Channel $channel, array $data): array
    {
        $email = trim((string) ($data['email'] ?? ''));
        $message = trim((string) ($data['message'] ?? ''));
        $name = trim((string) ($data['name'] ?? '')) ?: 'Visitor';

        if ($email === '' || $message === '') {
            throw ValidationException::withMessages([
                'email' => 'Email and message are required while we are offline.',
            ]);
        }

        $contact = $this->contacts->findOrCreateByEmail($email, $name);
        $openStatus = $this->tickets->statuses()->firstWhere('slug', 'open')
            ?? $this->tickets->statuses()->first();
        $normalPriority = $this->tickets->priorities()->firstWhere('slug', 'normal')
            ?? $this->tickets->priorities()->first();

        $ticket = $this->tickets->create([
            'subject' => 'Offline chat from '.$name,
            'description' => $message,
            'contact_id' => $contact->id,
            'channel_id' => $channel->id,
            'ticket_status_id' => $openStatus->id,
            'ticket_priority_id' => $normalPriority->id,
        ]);

        return [
            'mode' => 'offline',
            'online' => false,
            'ticket_number' => $ticket->number,
            'message' => $channel->settings['offline_message'] ?? 'We will get back to you by email.',
        ];
    }

    private function sendVisitorMessage(ChatSession $session, string $body): void
    {
        $session->loadMissing('ticket');

        $this->tickets->addContactMessage(
            $session->ticket_id,
            $session->contact_id,
            $body,
            $session->channel_id,
            'chat.'.$session->uuid.'.'.now()->timestamp,
        );
    }

    private function messagesForSession(ChatSession $session): array
    {
        return TicketMessage::query()
            ->where('ticket_id', $session->ticket_id)
            ->where('is_internal', false)
            ->with(['user:id,name', 'contact:id,name'])
            ->orderBy('created_at')
            ->get()
            ->map(fn ($message) => $this->formatMessage($message))
            ->values()
            ->all();
    }

    private function sessionPayload(ChatSession $session, array $messages): array
    {
        $session->loadMissing('ticket');

        return [
            'mode' => 'live',
            'online' => true,
            'session_uuid' => $session->uuid,
            'session_token' => $session->token,
            'ticket_number' => $session->ticket->number,
            'messages' => $messages,
            'realtime' => [
                'url' => config('realtime.ws_url'),
                'channel' => 'chat.'.$session->uuid,
                'token' => $this->realtimeTokens->forChannel('chat.'.$session->uuid),
            ],
        ];
    }

    private function formatMessage(TicketMessage $message): array
    {
        $body = $message->body ?? '';

        if ($message->user_id) {
            $body = MessageBodySanitizer::toPlainText($body);
        }

        return [
            'id' => $message->id,
            'body' => $body,
            'author_type' => $message->user_id ? 'agent' : 'visitor',
            'author_name' => $message->user_id
                ? ($message->user?->name ?? 'Agent')
                : ($message->contact?->name ?? 'Visitor'),
            'created_at' => $message->created_at?->toIso8601String(),
        ];
    }
}
