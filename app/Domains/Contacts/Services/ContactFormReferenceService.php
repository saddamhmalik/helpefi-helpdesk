<?php

namespace App\Domains\Contacts\Services;

use App\Domains\Contacts\Repositories\OrganizationRepository;
use App\Domains\Contacts\Repositories\TagRepository;
use App\Domains\Contacts\Support\ContactFormReferenceCache;
use App\Domains\Settings\Services\HelpdeskSettingService;
use App\Support\TenantCache;
use Illuminate\Support\Facades\Cache;

class ContactFormReferenceService
{
    private const CACHE_TTL_SECONDS = 300;

    public function __construct(
        private OrganizationRepository $organizations,
        private TagRepository $tags,
        private HelpdeskSettingService $helpdeskSettings,
    ) {
    }

    public function payload(): array
    {
        if (! tenancy()->initialized) {
            return $this->loadPayload();
        }

        return Cache::remember(
            TenantCache::key('contact_form_reference'),
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
        ContactFormReferenceCache::forget();
    }

    private function loadPayload(): array
    {
        return [
            'organizations' => $this->organizations->allForSelect()->values()->all(),
            'tags' => $this->tags->all()->values()->all(),
            'customFieldDefinitions' => $this->helpdeskSettings->contactFieldDefinitions(),
        ];
    }
}
