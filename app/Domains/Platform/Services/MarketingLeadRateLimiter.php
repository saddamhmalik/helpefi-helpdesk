<?php

namespace App\Domains\Platform\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

class MarketingLeadRateLimiter
{
    public function assertWithinLimit(Request $request): void
    {
        $ipKey = $this->ipKey($request->ip());

        if ($this->tooMany($ipKey, $this->ipMaxAttempts())) {
            throw ValidationException::withMessages([
                'rate_limit' => $this->message(RateLimiter::availableIn($ipKey)),
            ]);
        }

        $email = $this->normalizeEmail($request->input('email'));

        if ($email === null) {
            return;
        }

        $emailKey = $this->emailKey($email);

        if ($this->tooMany($emailKey, $this->emailMaxAttempts())) {
            throw ValidationException::withMessages([
                'rate_limit' => $this->message(RateLimiter::availableIn($emailKey)),
            ]);
        }
    }

    public function recordAttempt(Request $request): void
    {
        RateLimiter::hit($this->ipKey($request->ip()), $this->ipDecaySeconds());

        $email = $this->normalizeEmail($request->input('email'));

        if ($email !== null) {
            RateLimiter::hit($this->emailKey($email), $this->emailDecaySeconds());
        }
    }

    private function tooMany(string $key, int $maxAttempts): bool
    {
        return RateLimiter::tooManyAttempts($key, $maxAttempts);
    }

    private function ipKey(string $ip): string
    {
        return 'marketing-lead:ip:'.$ip;
    }

    private function emailKey(string $email): string
    {
        return 'marketing-lead:email:'.$email;
    }

    private function normalizeEmail(mixed $email): ?string
    {
        if (! is_string($email)) {
            return null;
        }

        $email = strtolower(trim($email));

        return $email !== '' ? $email : null;
    }

    private function ipMaxAttempts(): int
    {
        return max(1, (int) config('platform_marketing_leads.capture.ip_max_attempts', 8));
    }

    private function emailMaxAttempts(): int
    {
        return max(1, (int) config('platform_marketing_leads.capture.email_max_attempts', 5));
    }

    private function ipDecaySeconds(): int
    {
        return max(60, (int) config('platform_marketing_leads.capture.ip_decay_minutes', 1) * 60);
    }

    private function emailDecaySeconds(): int
    {
        return max(60, (int) config('platform_marketing_leads.capture.email_decay_minutes', 60) * 60);
    }

    private function message(int $seconds): string
    {
        $minutes = max(1, (int) ceil($seconds / 60));

        return "Too many submissions. Please try again in {$minutes} minute(s).";
    }
}
