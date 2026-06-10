<?php

namespace App\Domains\Settings\Services;

use App\Domains\Channels\Repositories\ChannelRepository;
use App\Domains\Channels\Services\OutboundMailService;
use App\Domains\Tickets\Models\Ticket;
use App\Domains\Tickets\Models\TicketMessage;
use App\Domains\Tickets\Repositories\TicketRepository;
use App\Domains\Tickets\Support\MessageBodySanitizer;

class AutoFirstResponseService
{
    public function __construct(
        private HelpdeskSettingService $settings,
        private TicketRepository $tickets,
        private ChannelRepository $channels,
        private OutboundMailService $outboundMail,
    ) {
    }

    public function sendIfEnabled(Ticket $ticket, ?TicketMessage $customerMessage = null): void
    {
        if (! $this->settings->autoFirstResponseEnabled()) {
            return;
        }

        $ticket = $this->tickets->find($ticket->id);
        $ticket->loadMissing(['contact', 'channel']);

        if (! $ticket->contact?->email) {
            return;
        }

        if (! $this->isEmailTicket($ticket)) {
            return;
        }

        $customerMessage ??= $ticket->messages()
            ->whereNotNull('contact_id')
            ->orderBy('created_at')
            ->orderBy('id')
            ->first();

        if (! $customerMessage) {
            return;
        }

        $plainBody = trim($this->settings->renderAutoFirstResponseBody($ticket));

        if ($plainBody === '') {
            return;
        }

        $body = collect(preg_split('/\R{2,}/', $plainBody))
            ->filter()
            ->map(fn (string $paragraph) => '<p>'.nl2br(e($paragraph)).'</p>')
            ->join('');

        $body = MessageBodySanitizer::sanitize($body);

        $message = $this->tickets->addMessage($ticket, [
            'body' => $body,
            'is_internal' => false,
            'channel_id' => $ticket->channel_id,
        ]);

        $this->outboundMail->sendAutoFirstResponse(
            $this->tickets->find($ticket->id),
            $message,
            $customerMessage,
        );
    }

    private function isEmailTicket(Ticket $ticket): bool
    {
        if (! $ticket->channel_id) {
            return false;
        }

        $channel = $this->channels->find($ticket->channel_id);

        return $channel?->type === 'email' || $channel?->slug === 'email';
    }
}
