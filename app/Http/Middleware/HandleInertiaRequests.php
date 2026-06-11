<?php

namespace App\Http\Middleware;

use App\Domains\Tenancy\Support\CentralDomain;
use App\Domains\Ai\Services\AiAssistService;
use App\Domains\Auth\Services\UserPreferenceService;
use App\Domains\Billing\Services\BillingService;
use App\Domains\Notifications\Services\NotificationService;
use App\Domains\Realtime\Services\RealtimeTokenService;
use App\Domains\Sla\Repositories\BusinessHoursRepository;
use App\Domains\Tenancy\Services\TenantDummyDataService;
use App\Models\User;
use App\Support\LocaleSupport;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    public function share(Request $request): array
    {
        $user = CentralDomain::isCentralHost($request->getHost()) ? null : $request->user();

        return array_merge(parent::share($request), [
            'csrf_token' => fn () => csrf_token(),
            'platformAuth' => function () {
                $platformUser = auth('platform')->user();

                return [
                    'user' => $platformUser ? [
                        'id' => $platformUser->id,
                        'name' => $platformUser->name,
                        'email' => $platformUser->email,
                        'roles' => $platformUser->roles()->pluck('name')->values()->all(),
                        'permissions' => $platformUser->permissionNames(),
                    ] : null,
                    'permissions' => $platformUser?->permissionNames() ?? [],
                ];
            },
            'portalBrand' => fn () => app(\App\Domains\Brands\Support\BrandContext::class)->hasBrand()
                ? app(\App\Domains\Brands\Support\BrandContext::class)->toPortalArray()
                : null,
            'portalLocale' => fn () => app(\App\Domains\Brands\Support\BrandContext::class)->hasBrand()
                ? app(\App\Domains\Knowledge\Services\KnowledgeLocaleService::class)->current()
                : null,
            'portalLocales' => fn () => app(\App\Domains\Brands\Support\BrandContext::class)->hasBrand()
                ? app(\App\Domains\Knowledge\Services\KnowledgeSettingService::class)->localeOptions()
                : null,
            'auth' => [
                'user' => $user ? $this->authUserPayload($user) : null,
            ],
            'locale' => fn () => $user instanceof User
                ? app(UserPreferenceService::class)->locale($user)
                : config('app.locale'),
            'direction' => fn () => $user instanceof User
                ? (app(UserPreferenceService::class)->isRtl($user) ? 'rtl' : 'ltr')
                : 'ltr',
            'localeOptions' => fn () => LocaleSupport::options(),
            'timezone' => fn () => $user instanceof User
                ? app(UserPreferenceService::class)->timezone($user)
                : $this->helpdeskTimezone(),
            'ai' => fn () => $this->tenantFeature($user, fn () => app(AiAssistService::class)->status()),
            'billing' => fn () => $this->tenantFeature($user, fn () => app(BillingService::class)->snapshot()),
            'notifications' => fn () => $this->tenantFeature($user, fn () => app(NotificationService::class)->inboxSummary($user)),
            'realtime' => fn () => $this->tenantFeature($user, fn () => [
                'url' => config('realtime.ws_url'),
                'token' => app(RealtimeTokenService::class)->agentToken($user),
            ]),
            'tenantId' => fn () => tenant('id'),
            'platformNotices' => fn () => $this->tenantFeature($user, fn () => app(\App\Domains\Platform\Services\TenantPlatformNoticeService::class)->activeForUser($user)),
            'setupWarnings' => fn () => $this->tenantFeature($user, function () use ($user) {
                if (! $user->hasRole('admin')) {
                    return [];
                }

                return app(\App\Domains\Tenancy\Services\TenantSetupService::class)->incompleteRequiredWarnings();
            }),
            'setupGuideDismissed' => fn () => $this->tenantFeature($user, function () use ($user) {
                if (! $user->hasRole('admin')) {
                    return true;
                }

                return ! app(\App\Domains\Tenancy\Services\TenantSetupService::class)->shouldRedirect();
            }),
            'helpdesk' => fn () => [
                'timezone' => $this->helpdeskTimezone(),
            ],
            'dummyData' => fn () => $this->dummyDataState($user),
            'helpCenter' => fn () => tenant('id')
                ? app(\App\Domains\Knowledge\Services\HelpCenterService::class)->guestState()
                : null,
            'flash' => [
                'success' => fn () => $request->session()->pull('success'),
                'error' => fn () => $request->session()->pull('error'),
                'created_inbox_id' => fn () => $request->session()->pull('created_inbox_id'),
                'invite_url' => fn () => $request->session()->get('invite_url'),
                'webhook_secret' => fn () => $request->session()->get('webhook_secret'),
                'two_factor_setup' => fn () => $request->session()->get('two_factor_setup'),
                'recovery_codes' => fn () => $request->session()->get('recovery_codes'),
            ],
        ]);
    }

    private function authUserPayload(User $user): array
    {
        $preferences = app(UserPreferenceService::class);

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'locale' => $preferences->locale($user),
            'timezone' => $preferences->timezone($user),
            'roles' => $user->getRoleNames()->values()->all(),
            'permissions' => $user->getAllPermissions()->pluck('name')->values()->all(),
            'is_admin' => $user->hasRole('admin'),
            'is_customer' => $user->hasRole('customer'),
            'contact_id' => $user->contact_id,
        ];
    }

    private function helpdeskTimezone(): string
    {
        if (! tenant('id')) {
            return config('app.timezone');
        }

        try {
            return app(BusinessHoursRepository::class)->default()?->timezone
                ?? config('app.timezone');
        } catch (\Throwable) {
            return config('app.timezone');
        }
    }

    private function tenantFeature($user, callable $callback): mixed
    {
        if (! tenant('id') || ! $user || $user->hasRole('customer')) {
            return null;
        }

        return $callback();
    }

    private function dummyDataState($user): ?array
    {
        if (! tenant('id') || ! $user?->hasRole('admin')) {
            return null;
        }

        return app(TenantDummyDataService::class)->publicState();
    }
}
