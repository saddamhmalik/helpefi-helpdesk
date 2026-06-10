<?php

namespace App\Domains\Platform\Services;

use App\Domains\Platform\Repositories\PlatformExecutiveMetricsRepository;

class PlatformExecutiveMetricsService
{
    public function __construct(private PlatformExecutiveMetricsRepository $metrics)
    {
    }

    public function snapshot(): array
    {
        return $this->metrics->aggregate();
    }
}
