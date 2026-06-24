<?php

namespace App\Support;

use Illuminate\Support\Facades\Log;

class SecurityEventLogger
{
    public static function authLoginFailed(string $email, string $channel, ?string $reason = null): void
    {
        Log::warning('security.auth.login_failed', [
            'email' => $email,
            'channel' => $channel,
            'reason' => $reason,
            'ip' => request()->ip(),
        ]);
    }

    public static function webhookSignatureFailed(string $provider, ?string $message = null): void
    {
        Log::warning('security.webhook.signature_failed', [
            'provider' => $provider,
            'message' => $message,
            'ip' => request()->ip(),
        ]);
    }

    public static function rateLimitExceeded(string $route, ?string $key = null): void
    {
        Log::warning('security.rate_limit.exceeded', [
            'route' => $route,
            'key' => $key,
            'ip' => request()->ip(),
        ]);
    }

    public static function apiForbidden(int $userId, string $route): void
    {
        Log::notice('security.api.forbidden', [
            'user_id' => $userId,
            'route' => $route,
            'ip' => request()->ip(),
        ]);
    }
}
