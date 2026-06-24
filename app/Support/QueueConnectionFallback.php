<?php

namespace App\Support;

class QueueConnectionFallback
{
    public static function apply(): void
    {
        if (extension_loaded('redis')) {
            return;
        }

        if (config('queue.default') === 'redis') {
            config(['queue.default' => 'sync']);
        }

        $centralConnection = config('tenancy.central_queue_connection');

        if ($centralConnection && config("queue.connections.{$centralConnection}.driver") === 'redis') {
            config(["queue.connections.{$centralConnection}.driver" => 'sync']);
        }
    }
}
