<?php

namespace App\Http\Middleware;

use App\Domains\Auth\Services\UserPreferenceService;
use App\Domains\Tenancy\Support\CentralDomain;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

class SetUserLocale
{
    public function __construct(private UserPreferenceService $preferences)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        if (CentralDomain::isCentralHost($request->getHost())) {
            return $next($request);
        }

        $user = $request->user();

        if ($user instanceof User) {
            App::setLocale($this->preferences->locale($user));
        }

        return $next($request);
    }
}
