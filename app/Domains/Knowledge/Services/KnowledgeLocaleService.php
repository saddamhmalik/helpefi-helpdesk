<?php

namespace App\Domains\Knowledge\Services;

use Illuminate\Http\Request;

class KnowledgeLocaleService
{
    private ?string $resolved = null;

    public function __construct(private KnowledgeSettingService $settings)
    {
    }

    public function resolve(Request $request): string
    {
        $enabled = $this->settings->enabledLocales();
        $default = $this->settings->defaultLocale();
        $candidate = $default;

        if ($request->filled('lang')) {
            $candidate = strtolower((string) $request->query('lang'));
        } elseif ($request->hasSession() && $request->session()->has('portal_locale')) {
            $candidate = (string) $request->session()->get('portal_locale');
        } elseif ($request->headers->has('Accept-Language')) {
            $candidate = $this->matchAcceptLanguage((string) $request->header('Accept-Language'), $enabled) ?? $default;
        }

        if (! in_array($candidate, $enabled, true)) {
            $candidate = $default;
        }

        if ($request->hasSession()) {
            $request->session()->put('portal_locale', $candidate);
        }

        $this->resolved = $candidate;

        return $candidate;
    }

    public function current(): string
    {
        return $this->resolved ?? $this->settings->defaultLocale();
    }

    public function setCurrent(string $locale): void
    {
        $this->resolved = $locale;
    }

    private function matchAcceptLanguage(string $header, array $enabled): ?string
    {
        foreach (explode(',', $header) as $part) {
            $tag = strtolower(trim(explode(';', $part)[0]));
            $primary = explode('-', $tag)[0];

            if (in_array($tag, $enabled, true)) {
                return $tag;
            }

            if (in_array($primary, $enabled, true)) {
                return $primary;
            }
        }

        return null;
    }
}
