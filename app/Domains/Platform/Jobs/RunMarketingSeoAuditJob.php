<?php

namespace App\Domains\Platform\Jobs;

use App\Domains\Platform\Concerns\RunsOnCentralQueue;
use App\Domains\Platform\Services\MarketingSeoAuditService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class RunMarketingSeoAuditJob implements ShouldQueue
{
    use Queueable;
    use RunsOnCentralQueue;

    public int $timeout = 600;

    public int $tries = 1;

    public function __construct()
    {
        $this->bindToCentralQueue();
    }

    public function handle(MarketingSeoAuditService $audit): void
    {
        $this->ensureCentralContext();

        try {
            $audit->storeReport($audit->performAudit());
        } finally {
            $audit->clearRunning();
        }
    }

    public function failed(?Throwable $exception): void
    {
        app(MarketingSeoAuditService::class)->clearRunning();
    }
}
