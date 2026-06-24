<?php

namespace App\Domains\Tickets\Services;

use App\Domains\Assets\Services\AssetService;
use App\Domains\Channels\Repositories\ChannelRepository;
use App\Domains\Integrations\Services\TicketExternalIssueService;
use App\Domains\Settings\Services\HelpdeskSettingService;
use App\Domains\Tickets\Repositories\TicketRepository;
use App\Domains\Workforce\Services\WorkforceService;
use App\Support\ReferenceCacheInvalidator;
use App\Support\TenantCache;
use Illuminate\Support\Facades\Cache;

class TicketFormReferenceService
{
    private const CACHE_TTL_SECONDS = 300;

    public function __construct(
        private TicketRepository $tickets,
        private WorkforceService $workforce,
        private AssetService $assets,
        private TicketExternalIssueService $externalIssues,
        private HelpdeskSettingService $helpdeskSettings,
        private ChannelRepository $channels,
        private ReferenceCacheInvalidator $referenceCache,
    ) {
    }

    public function payload(): array
    {
        if (! tenancy()->initialized) {
            return $this->loadPayload();
        }

        return Cache::remember(
            TenantCache::key('ticket_form_reference'),
            self::CACHE_TTL_SECONDS,
            fn () => $this->loadPayload(),
        );
    }

    public function only(array $keys): array
    {
        return array_intersect_key($this->payload(), array_flip($keys));
    }

    public function forgetCache(): void
    {
        $this->referenceCache->forgetTicketFormReference();
    }

    private function loadPayload(): array
    {
        return [
            'statuses' => $this->tickets->statuses()->toArray(),
            'priorities' => $this->tickets->priorities()->toArray(),
            'agents' => $this->workforce->agentOptions()->toArray(),
            'departments' => $this->workforce->departmentOptions()->toArray(),
            'teams' => $this->workforce->teamOptions()->toArray(),
            'assetOptions' => $this->assets->options()->toArray(),
            'channels' => $this->channels->all()->toArray(),
            'issueProviders' => $this->externalIssues->configuredIssueProviders(),
            'customFieldDefinitions' => $this->helpdeskSettings->ticketFieldDefinitions(),
        ];
    }
}
