<?php

namespace App\Http\Middleware;

use App\Domains\Ai\Services\AiAssistService;
use App\Domains\Billing\Services\BillingService;
use App\Domains\Notifications\Services\NotificationService;
use App\Domains\Realtime\Services\RealtimeTokenService;
use App\Domains\Sla\Repositories\BusinessHoursRepository;
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
        $user = $request->user();

        return array_merge(parent::share($request), [
            'portalBrand' => fn () => app(\App\Domains\Brands\Support\BrandContext::class)->hasBrand()
                ? app(\App\Domains\Brands\Support\BrandContext::class)->toPortalArray()
                : null,
            'auth' => [
                'user' => $user ? [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'roles' => $user->getRoleNames()->values()->all(),
                    'permissions' => $user->getAllPermissions()->pluck('name')->values()->all(),
                    'is_admin' => $user->hasRole('admin'),
                    'is_customer' => $user->hasRole('customer'),
                    'contact_id' => $user->contact_id,
                ] : null,
            ],
            'ai' => fn () => $this->tenantFeature($user, fn () => app(AiAssistService::class)->status()),
            'billing' => fn () => $this->tenantFeature($user, fn () => app(BillingService::class)->snapshot()),
            'notifications' => fn () => $this->tenantFeature($user, fn () => app(NotificationService::class)->inboxSummary($user)),
            'realtime' => fn () => $this->tenantFeature($user, fn () => [
                'url' => config('realtime.ws_url'),
                'token' => app(RealtimeTokenService::class)->agentToken($user),
            ]),
            'tenantId' => fn () => tenant('id'),
            'helpdesk' => fn () => [
                'timezone' => $this->helpdeskTimezone(),
            ],
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
                'invite_url' => fn () => $request->session()->get('invite_url'),
                'webhook_secret' => fn () => $request->session()->get('webhook_secret'),
                'two_factor_setup' => fn () => $request->session()->get('two_factor_setup'),
                'recovery_codes' => fn () => $request->session()->get('recovery_codes'),
            ],
        ]);
    }

    private function helpdeskTimezone(): string
    {
        if (! tenant('id')) {
            return config('app.timezone');
        }

        return app(BusinessHoursRepository::class)->default()?->timezone
            ?? config('app.timezone');
    }

    private function tenantFeature($user, callable $callback): mixed
    {
        if (! tenant('id') || ! $user || $user->hasRole('customer')) {
            return null;
        }

        return $callback();
    }
}
