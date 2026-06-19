<?php

namespace App\Http\Middleware;

use App\Domains\Platform\Services\HelpefiLicenseService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureValidHelpefiLicense
{
    public function handle(Request $request, Closure $next): Response
    {
        if (config('deployment.mode') !== 'self_hosted') {
            return $next($request);
        }

        $error = app(HelpefiLicenseService::class)->resolveValidationError();

        if ($error === null) {
            return $next($request);
        }

        if ($request->expectsJson()) {
            abort(503, $error);
        }

        return response()->view('errors.license', ['message' => $error], 503);
    }
}
