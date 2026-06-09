<?php

namespace App\Domains\Realtime\Support;

class RealtimeChannelNames
{
    public static function scoped(string $name): string
    {
        $tenantId = tenant('id');

        return $tenantId ? "{$tenantId}.{$name}" : $name;
    }

    public static function ticket(int $ticketId): string
    {
        return self::scoped("ticket.{$ticketId}");
    }

    public static function chat(string $sessionUuid): string
    {
        return self::scoped("chat.{$sessionUuid}");
    }

    public static function workspace(): string
    {
        return self::scoped('workspace');
    }
}
