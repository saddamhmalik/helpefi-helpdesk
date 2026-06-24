<?php

namespace App\Domains\Tickets\Services;

use App\Domains\Channels\Models\Channel;
use App\Domains\Channels\Repositories\ChannelRepository;
use App\Domains\Channels\Services\OutboundMailService;
use App\Domains\Channels\Services\TwilioMessagingService;
use App\Domains\Tickets\Models\Ticket;
use App\Domains\Tickets\Models\TicketMessage;
use App\Models\User;

class TicketOutboundDeliveryService
{
    public function __construct(
        private ChannelRepository $channels,
        private OutboundMailService $outboundMail,
        private TwilioMessagingService $messaging,
    ) {
    }

    public function deliverAgentReply(Ticket $ticket, TicketMessage $message, User $user): void
    {
        $ticket->loadMissing('channel', 'contact');
        $channelType = $ticket->channel?->type
            ?? $this->channels->find($message->channel_id)?->type;

        if ($channelType === Channel::TYPE_WHATSAPP || $channelType === Channel::TYPE_SMS) {
            $phone = $ticket->contact?->phone;

            if ($phone) {
                $this->messaging->send($phone, $message->body, $channelType);
            }

            return;
        }

        if ($channelType !== Channel::TYPE_CHAT) {
            $this->outboundMail->sendTicketReply($ticket, $message, $user);
        }
    }
}
