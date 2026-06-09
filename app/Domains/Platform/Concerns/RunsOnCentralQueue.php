<?php

namespace App\Domains\Platform\Concerns;

use Stancl\Tenancy\Database\DatabaseManager;

trait RunsOnCentralQueue
{
    protected function bindToCentralQueue(): void
    {
        $this->onConnection(config('tenancy.central_queue_connection', 'central'));
    }

    protected function ensureCentralContext(): void
    {
        if (tenancy()->initialized) {
            tenancy()->end();
        }

        app(DatabaseManager::class)->reconnectToCentral();
    }
}
