<?php

namespace App\Domains\Realtime\Services;

use App\Domains\Realtime\Support\RealtimeChannelNames;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Throwable;

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

    public function notificationCreated(int $userId, array $notification): void
    {
        $this->publish(RealtimeChannelNames::user($userId), [
            'event' => 'notification.created',
            'data' => [
                'notification' => $notification,
                'unread_count' => null,
            ],
        ]);
    }

    private function publish(string $channel, array $payload): void
    {
        if (! $this->canPublish()) {
            return;
        }

        $payload['timestamp'] = now()->toIso8601String();

        try {
            Redis::connection('realtime')->publish(
                config('realtime.redis_prefix').$channel,
                json_encode($payload, JSON_THROW_ON_ERROR),
            );
        } catch (Throwable $exception) {
            Log::warning('Realtime publish skipped', [
                'channel' => $channel,
                'message' => $exception->getMessage(),
            ]);
        }
    }

    private function canPublish(): bool
    {
        if (! config('realtime.enabled', true)) {
            return false;
        }

        if (config('database.redis.client') === 'phpredis' && ! extension_loaded('redis')) {
            return false;
        }

        return true;
    }
}
