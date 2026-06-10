<?php

namespace App\Providers;

use App\Domains\Platform\Services\PlatformAuthorizationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Laravel\Telescope\Http\Middleware\Authorize;
use Laravel\Telescope\IncomingEntry;
use Laravel\Telescope\Telescope;
use Laravel\Telescope\TelescopeApplicationServiceProvider;
use Symfony\Component\HttpFoundation\Response;

class TelescopeServiceProvider extends TelescopeApplicationServiceProvider
{
    public function register(): void
    {
        if (! config('telescope.enabled')) {
            return;
        }

        $this->hideSensitiveRequestDetails();

        Telescope::filter(function (IncomingEntry $entry) {
            if ($this->app->environment('local', 'testing')) {
                return true;
            }

            return $entry->isReportableException()
                || $entry->isFailedRequest()
                || $entry->isFailedJob()
                || $entry->isScheduledTask();
        });
    }

    protected function hideSensitiveRequestDetails(): void
    {
        Telescope::hideRequestParameters(['_token', 'password', 'password_confirmation', 'current_password']);

        Telescope::hideRequestHeaders([
            'cookie',
            'x-csrf-token',
            'x-xsrf-token',
            'authorization',
        ]);
    }

    protected function authorization(): void
    {
        Telescope::auth(function (Request $request): bool {
            if (! config('telescope.enabled')) {
                return false;
            }

            if (! $this->isCentralRequest($request)) {
                return false;
            }

            $user = Auth::guard('platform')->user();

            if (! $user || ! $user->is_active) {
                return false;
            }

            return app(PlatformAuthorizationService::class)->allows($user, 'observability.view');
        });
    }

    public function boot(): void
    {
        parent::boot();

        if (! config('telescope.enabled')) {
            return;
        }

        $this->app->booted(function (): void {
            Route::middlewareGroup('telescope', [
                'web',
                Authorize::class,
            ]);
        });
    }

    public static function redirectUrl(Request $request): string
    {
        $path = '/'.trim((string) config('telescope.path', 'telescope'), '/');

        return $request->getSchemeAndHttpHost().$path;
    }

    public static function redirectResponse(Request $request): Response
    {
        $url = self::redirectUrl($request);

        if ($request->header('X-Inertia')) {
            return Inertia::location($url);
        }

        return redirect()->to($url);
    }

    private function isCentralRequest(Request $request): bool
    {
        $host = strtolower($request->getHost());

        if ($host === strtolower((string) config('tenancy.central_app_domain'))) {
            return true;
        }

        foreach (config('tenancy.central_domains', []) as $domain) {
            if ($host === strtolower((string) $domain)) {
                return true;
            }
        }

        return false;
    }
}
