<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ConfigureApplicationSession
{
    public function handle(Request $request, Closure $next): Response
    {
        config([
            'session.connection' => 'central',
            'session.domain' => $this->sessionDomain(),
            'session.secure' => $this->sessionSecure($request),
        ]);

        return $next($request);
    }

    private function sessionDomain(): ?string
    {
        $configured = config('session.domain');

        if (! is_string($configured) || $configured === '' || $configured === 'null') {
            return null;
        }

        if (str_starts_with($configured, '.')) {
            return null;
        }

        return $configured;
    }

    private function sessionSecure(Request $request): bool
    {
        $configured = config('session.secure');

        if ($configured === null) {
            return $request->isSecure();
        }

        return (bool) $configured;
    }
}
