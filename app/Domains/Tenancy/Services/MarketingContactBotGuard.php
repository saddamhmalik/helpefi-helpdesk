<?php

namespace App\Domains\Tenancy\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class MarketingContactBotGuard
{
    private const SESSION_STARTED_AT = 'marketing_contact_form_started_at';

    public function beginFormSession(): void
    {
        session([self::SESSION_STARTED_AT => now()->timestamp]);
    }

    public function isSilentBot(Request $request): bool
    {
        if ($this->honeypotFilled($request)) {
            return true;
        }

        return $this->submittedTooFast();
    }

    public function assertTurnstile(Request $request): void
    {
        if (! $this->turnstileEnabled()) {
            return;
        }

        if ($this->verifyTurnstile($request)) {
            return;
        }

        throw \Illuminate\Validation\ValidationException::withMessages([
            'cf_turnstile_response' => __('messages.marketing_contact_turnstile_failed'),
        ]);
    }

    public function turnstileEnabled(): bool
    {
        $secret = config('marketing_seo.turnstile.secret_key');

        return is_string($secret) && $secret !== '';
    }

    public function turnstileSiteKey(): ?string
    {
        if (! $this->turnstileEnabled()) {
            return null;
        }

        $siteKey = config('marketing_seo.turnstile.site_key');

        return is_string($siteKey) && $siteKey !== '' ? $siteKey : null;
    }

    private function honeypotFilled(Request $request): bool
    {
        $value = $request->input('website');

        return is_string($value) && trim($value) !== '';
    }

    private function submittedTooFast(): bool
    {
        $startedAt = session(self::SESSION_STARTED_AT);

        if (! is_int($startedAt) && ! is_numeric($startedAt)) {
            return true;
        }

        $elapsed = now()->timestamp - (int) $startedAt;
        $minimum = max(1, (int) config('marketing_seo.contact_form.min_seconds_on_page', 3));
        $maximum = max($minimum, (int) config('marketing_seo.contact_form.max_form_age_minutes', 120) * 60);

        return $elapsed < $minimum || $elapsed > $maximum;
    }

    private function verifyTurnstile(Request $request): bool
    {
        $token = $request->input('cf_turnstile_response');

        if (! is_string($token) || trim($token) === '') {
            return false;
        }

        $response = Http::asForm()->post('https://challenges.cloudflare.com/turnstile/v0/siteverify', [
            'secret' => config('marketing_seo.turnstile.secret_key'),
            'response' => $token,
            'remoteip' => $request->ip(),
        ]);

        if (! $response->successful()) {
            return false;
        }

        return (bool) $response->json('success');
    }
}
