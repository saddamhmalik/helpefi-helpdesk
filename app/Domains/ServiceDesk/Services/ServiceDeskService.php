<?php

namespace App\Domains\ServiceDesk\Services;

use App\Domains\Billing\Contracts\FeatureEntitlementChecker;
use App\Domains\ServiceDesk\Repositories\ServiceDeskRepository;
use App\Domains\ServiceDesk\Support\TicketTypes;

class ServiceDeskService
{
    public function __construct(
        private ServiceDeskRepository $repository,
        private FeatureEntitlementChecker $entitlements,
    ) {
    }

    public function isAvailable(): bool
    {
        return $this->entitlements->canUseFeature('service_desk');
    }

    public function assertAvailable(): void
    {
        $this->entitlements->assertFeature('service_desk');
    }

    public function overview(): array
    {
        $this->assertAvailable();

        $summaries = $this->repository->typeSummaries();
        $recentGrouped = $this->repository->recentGroupedByType();

        return [
            'summaries' => $summaries,
            'totals' => [
                'open' => collect($summaries)->sum('open'),
                'unassigned' => collect($summaries)->sum('unassigned'),
            ],
            'recent' => collect(TicketTypes::values())
                ->mapWithKeys(fn (string $type) => [
                    $type => $recentGrouped->get($type, collect())->values(),
                ])
                ->all(),
        ];
    }

    public function ticketTypes(): array
    {
        return TicketTypes::all();
    }

    public function resolveType(string $type): array
    {
        $definition = TicketTypes::find($type);

        if ($definition === null) {
            abort(404);
        }

        return $definition;
    }

    public function assertValidType(string $type): array
    {
        return $this->resolveType($type);
    }

    public function upgradeContext(): array
    {
        $snapshot = $this->entitlements->snapshot();
        $addon = collect($snapshot['available_addons'] ?? [])->firstWhere('key', 'service_desk');

        $canPurchase = ($snapshot['on_trial'] ?? false)
            || ($snapshot['status'] ?? null) === 'active';

        return [
            'plan' => $snapshot['plan']['name'] ?? 'Current plan',
            'features' => $snapshot['features'] ?? [],
            'addon' => $addon,
            'currency' => $snapshot['currency'] ?? null,
            'onTrial' => $snapshot['on_trial'] ?? false,
            'canPurchase' => $canPurchase,
        ];
    }
}
