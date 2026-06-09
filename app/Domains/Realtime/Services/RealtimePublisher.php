<?php

namespace App\Domains\Realtime\Services;

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

        $this->publish("ticket.{$ticketId}", $payload);

        if ($chatSessionUuid) {
            $this->publish("chat.{$chatSessionUuid}", $payload);
        }
    }

    public function ticketUpdated(int $ticketId, array $ticket): void
    {
        $this->publish("ticket.{$ticketId}", [
            'event' => 'ticket.updated',
            'data' => [
                'ticket_id' => $ticketId,
                'ticket' => $ticket,
            ],
        ]);

        $this->publish('workspace', [
            'event' => 'queue.updated',
            'data' => [
                'ticket' => $ticket,
            ],
        ]);
    }

    public function presenceUpdated(int $ticketId, array $viewers): void
    {
        $this->publish("ticket.{$ticketId}", [
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

        Redis::publish(
            config('realtime.redis_prefix').$channel,
            json_encode($payload, JSON_THROW_ON_ERROR),
        );
    }
}
