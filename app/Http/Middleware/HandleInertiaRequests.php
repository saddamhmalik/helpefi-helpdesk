<?php

namespace App\Http\Middleware;

use App\Domains\Tenancy\Support\CentralDomain;
use App\Domains\Tenancy\Services\SchemaService;
use App\Domains\Ai\Services\AiAssistService;
use App\Domains\Auth\Services\UserPreferenceService;
use App\Domains\Billing\Services\BillingService;
use App\Domains\Realtime\Services\RealtimeTokenService;
use App\Domains\Sla\Repositories\BusinessHoursRepository;
use App\Domains\Tenancy\Services\TenantDummyDataService;
use App\Models\User;
use App\Support\AppVersion;
use App\Support\AvatarSupport;
use App\Support\LocaleSupport;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    private ?string $resolvedHelpdeskTimezone = null;

    private ?array $resolvedAdminMenuState = null;

    private ?array $resolvedAiStatus = null;

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    public function share(Request $request): array
    {
        $isCentral = CentralDomain::isCentralHost($request->getHost());
        $user = $isCentral ? null : $request->user();
        $preferenceContext = $user instanceof User ? $this->preferenceContext($user) : null;

        return array_merge(parent::share($request), [
            'appVersion' => fn () => AppVersion::current(),
            'csrf_token' => fn () => csrf_token(),
            'seo_schema' => fn () => $this->centralOnly($isCentral, fn () => app(SchemaService::class)->forRequest($request)),
            'seo_meta' => fn () => $this->centralOnly($isCentral, fn () => $this->centralSeoMeta($request)),
            'platformAuth' => function () {
                $platformUser = auth('platform')->user();

                if (! $platformUser) {
                    return [
                        'user' => null,
                        'permissions' => [],
                    ];
                }

                $permissions = $platformUser->permissionNames();

                return [
                    'user' => [
                        'id' => $platformUser->id,
                        'name' => $platformUser->name,
                        'email' => $platformUser->email,
                        'roles' => $platformUser->roles()->pluck('name')->values()->all(),
                        'permissions' => $permissions,
                    ],
                    'permissions' => $permissions,
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
            'auth' => fn () => [
                'user' => $user ? $this->authUserPayload($user, $preferenceContext) : null,
            ],
            'locale' => fn () => $preferenceContext['locale'] ?? (
                CentralDomain::isCentralHost($request->getHost())
                    ? 'en'
                    : LocaleSupport::resolveFromRequest($request)
            ),
            'direction' => fn () => $preferenceContext['direction'] ?? (
                CentralDomain::isCentralHost($request->getHost())
                    ? 'ltr'
                    : (LocaleSupport::isRtl(LocaleSupport::resolveFromRequest($request)) ? 'rtl' : 'ltr')
            ),
            'localeOptions' => fn () => CentralDomain::isCentralHost($request->getHost())
                ? []
                : LocaleSupport::options(),
            'timezone' => fn () => $preferenceContext['timezone'] ?? $this->helpdeskTimezone(),
            'appearance' => fn () => $preferenceContext['appearance'] ?? 'light',
            'ai' => fn () => $this->tenantFeature($user, fn () => $this->aiStatus()),
            'billing' => fn () => $this->tenantFeature($user, fn () => app(BillingService::class)->layoutSnapshot()),
            'realtime' => fn () => $this->tenantFeature($user, fn () => [
                'url' => config('realtime.ws_url'),
                'token' => app(RealtimeTokenService::class)->agentToken($user),
            ]),
            'tenantId' => fn () => tenant('id'),
            'platformNotices' => fn () => $this->tenantFeature($user, fn () => app(\App\Domains\Platform\Services\TenantPlatformNoticeService::class)->activeForUser($user)),
            'setupWarnings' => fn () => $this->tenantFeature($user, fn () => $this->adminMenuState($user)['warnings']),
            'setupGuideDismissed' => fn () => $this->tenantFeature($user, fn () => $this->adminMenuState($user)['guide_dismissed']),
            'helpdesk' => fn () => [
                'timezone' => $this->helpdeskTimezone(),
                'name' => tenant('id') ? (string) tenant('name') : null,
            ],
            'dummyData' => fn () => $this->dummyDataState($user),
            'helpCenter' => fn () => tenant('id')
                ? app(\App\Domains\Knowledge\Services\HelpCenterService::class)->cachedGuestState()
                : null,
            'flash' => fn () => [
                'success' => $request->session()->pull('success'),
                'warning' => $request->session()->pull('warning'),
                'error' => $request->session()->pull('error'),
                'created_inbox_id' => $request->session()->pull('created_inbox_id'),
                'invite_url' => $request->session()->pull('invite_url'),
                'webhook_secret' => $request->session()->pull('webhook_secret'),
                'two_factor_setup' => $request->session()->pull('two_factor_setup'),
                'recovery_codes' => $request->session()->pull('recovery_codes'),
                'integration_secret' => $request->session()->pull('integration_secret'),
                'razorpay_checkout' => $request->session()->pull('razorpay_checkout'),
            ],
        ]);
    }

    private function preferenceContext(User $user): array
    {
        static $cache = [];

        if (isset($cache[$user->id])) {
            return $cache[$user->id];
        }

        $preferences = app(UserPreferenceService::class);
        $locale = $preferences->locale($user);

        return $cache[$user->id] = [
            'locale' => $locale,
            'timezone' => $preferences->timezone($user),
            'appearance' => $preferences->appearance($user),
            'direction' => $preferences->isRtl($user) ? 'rtl' : 'ltr',
        ];
    }

    private function authUserPayload(User $user, ?array $preferenceContext = null): array
    {
        static $cache = [];

        if (isset($cache[$user->id])) {
            return $cache[$user->id];
        }

        $preferenceContext ??= $this->preferenceContext($user);
        $user->loadMissing(['roles.permissions', 'permissions']);

        $permissions = collect($user->permissions)
            ->pluck('name')
            ->merge(
                collect($user->roles)
                    ->flatMap(fn ($role) => collect($role->permissions))
                    ->pluck('name')
            )
            ->unique()
            ->values()
            ->all();

        return $cache[$user->id] = array_merge([
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'locale' => $preferenceContext['locale'],
            'timezone' => $preferenceContext['timezone'],
            'appearance' => $preferenceContext['appearance'],
            'roles' => collect($user->roles)->pluck('name')->values()->all(),
            'permissions' => $permissions,
            'is_admin' => $user->roles->contains('name', 'admin'),
            'is_customer' => $user->roles->contains('name', 'customer'),
            'contact_id' => $user->contact_id,
        ], AvatarSupport::payload($user));
    }

    private function aiStatus(): array
    {
        if ($this->resolvedAiStatus !== null) {
            return $this->resolvedAiStatus;
        }

        return $this->resolvedAiStatus = app(AiAssistService::class)->status();
    }

    private function adminMenuState(User $user): array
    {
        if ($this->resolvedAdminMenuState !== null) {
            return $this->resolvedAdminMenuState;
        }

        if (! $user->hasRole('admin')) {
            return $this->resolvedAdminMenuState = [
                'warnings' => [],
                'guide_dismissed' => true,
            ];
        }

        return $this->resolvedAdminMenuState = app(\App\Domains\Tenancy\Services\TenantSetupService::class)
            ->sharedAdminMenuState();
    }

    private function helpdeskTimezone(): string
    {
        if ($this->resolvedHelpdeskTimezone !== null) {
            return $this->resolvedHelpdeskTimezone;
        }

        if (! tenant('id')) {
            return $this->resolvedHelpdeskTimezone = config('app.timezone');
        }

        try {
            return $this->resolvedHelpdeskTimezone = app(BusinessHoursRepository::class)->default()?->timezone
                ?? config('app.timezone');
        } catch (\Throwable) {
            return $this->resolvedHelpdeskTimezone = config('app.timezone');
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

        return app(TenantDummyDataService::class)->cachedPublicState();
    }

    private function centralOnly(bool $isCentral, callable $callback): mixed
    {
        if (! $isCentral) {
            return null;
        }

        return $callback();
    }

    private function centralSeoMeta(Request $request): array
    {
        return [
            'route' => $request->route()?->getName(),
            'path' => '/'.ltrim($request->path(), '/'),
        ];
    }
}
