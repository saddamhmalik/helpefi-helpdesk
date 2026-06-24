<?php

namespace App\Domains\Growth\Services;

use App\Domains\Ai\Repositories\AiDeflectionRepository;
use App\Domains\Billing\Services\BillingService;
use App\Domains\Growth\Repositories\AiCopilotMetricsRepository;
use App\Domains\Knowledge\Repositories\KbDeflectionRepository;
use Illuminate\Support\Carbon;

class GrowthHubService
{
    public function __construct(
        private SetupHealthService $setupHealth,
        private WorkspaceEngagementService $engagement,
        private AiCopilotMetricsRepository $copilotMetrics,
        private AiDeflectionRepository $aiDeflection,
        private KbDeflectionRepository $kbDeflection,
        private BillingService $billing,
    ) {
    }

    public function snapshot(?array $deflectionFilters = null): array
    {
        $deflectionFilters ??= $this->defaultDeflectionFilters();

        return [
            'billing' => $this->billing->snapshot(),
            'engagement' => $this->engagement->snapshot(),
            'setup_health' => $this->setupHealth->snapshot(),
            'ai_usage' => $this->copilotMetrics->summary($deflectionFilters),
            'ai_deflection' => $this->aiDeflection->summary($deflectionFilters),
            'kb_deflection' => $this->kbDeflection->summary($deflectionFilters),
            'deflection_filters' => $deflectionFilters,
        ];
    }

    public function defaultDeflectionFilters(): array
    {
        return [
            'date_from' => Carbon::now()->subDays(30)->toDateString(),
            'date_to' => Carbon::now()->toDateString(),
        ];
    }
}
