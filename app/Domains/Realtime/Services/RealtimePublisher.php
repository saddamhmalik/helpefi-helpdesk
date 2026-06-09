<?php

namespace App\Domains\Realtime\Services;

use App\Domains\Realtime\Support\RealtimeChannelNames;
use Illuminate\Support\Facades\Redis;

class RealtimePublisher
{
    public function ticketMessage(int $ticketId, array $message, ?string $chatSessionUuid = null): void
    {
        $payload = [
            'event' => 'message.created',
            'data' => [
                'ticket_id' => $ticketId,
                'message' => $message,
            ],
        ];

        $this->publish(RealtimeChannelNames::ticket($ticketId), $payload);

        if ($chatSessionUuid) {
            $this->publish(RealtimeChannelNames::chat($chatSessionUuid), $payload);
        }
    }

    public function ticketUpdated(int $ticketId, array $ticket): void
    {
        $this->publish(RealtimeChannelNames::ticket($ticketId), [
            'event' => 'ticket.updated',
            'data' => [
                'ticket_id' => $ticketId,
                'ticket' => $ticket,
            ],
        ]);

        $this->publish(RealtimeChannelNames::workspace(), [
            'event' => 'queue.updated',
            'data' => [
                'ticket' => $ticket,
            ],
        ]);
    }

    public function presenceUpdated(int $ticketId, array $viewers): void
    {
        $this->publish(RealtimeChannelNames::ticket($ticketId), [
            'event' => 'presence.updated',
            'data' => [
                'ticket_id' => $ticketId,
                'viewers' => $viewers,
            ],
        ]);
    }

    private function publish(string $channel, array $payload): void
    {
        $payload['timestamp'] = now()->toIso8601String();

        Redis::connection('realtime')->publish(
            config('realtime.redis_prefix').$channel,
            json_encode($payload, JSON_THROW_ON_ERROR),
        );
    }
}
