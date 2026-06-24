<?php

namespace Tests\Unit;

use App\Support\QueueConnectionFallback;
use Tests\TestCase;

class QueueConnectionFallbackTest extends TestCase
{
    public function test_falls_back_to_sync_when_redis_extension_is_missing(): void
    {
        if (extension_loaded('redis')) {
            $this->markTestSkipped('Requires an environment without the phpredis extension.');
        }

        config(['queue.default' => 'redis']);

        QueueConnectionFallback::apply();

        $this->assertSame('sync', config('queue.default'));
    }

    public function test_keeps_redis_queue_when_extension_is_available(): void
    {
        if (! extension_loaded('redis')) {
            $this->markTestSkipped('Requires the phpredis extension.');
        }

        config(['queue.default' => 'redis']);

        QueueConnectionFallback::apply();

        $this->assertSame('redis', config('queue.default'));
    }
}
