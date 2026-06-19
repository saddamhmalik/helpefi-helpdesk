<?php

namespace App\Domains\Channels\Services;

use App\Domains\Brands\Services\BrandService;
use App\Domains\Channels\Support\EmailSettingsPageCache;
use App\Domains\Settings\Services\HelpdeskSettingService;
use App\Support\TenantCache;
use Illuminate\Support\Facades\Cache;

class EmailSettingsPageService
{
    private const CACHE_TTL_SECONDS = 300;

    public function __construct(
        private EmailInboxService $inboxes,
        private BrandService $brands,
        private HelpdeskSettingService $helpdeskSettings,
    ) {
    }

    public function staticPayload(): array
    {
        if (! tenancy()->initialized) {
            return $this->loadStaticPayload();
        }

        return Cache::remember(
            TenantCache::key('email_settings_reference'),
            self::CACHE_TTL_SECONDS,
            fn () => $this->loadStaticPayload(),
        );
    }

    public function forgetCache(): void
    {
        EmailSettingsPageCache::forget();
    }

    private function loadStaticPayload(): array
    {
        return [
            'mailboxProviders' => $this->inboxes->mailboxProviders(),
            'oauthProviders' => $this->inboxes->oauthProviders(),
            'brands' => $this->brands->listForSettings(),
            'emailAdvanced' => $this->helpdeskSettings->emailAdvancedSnapshot(),
        ];
    }
}
