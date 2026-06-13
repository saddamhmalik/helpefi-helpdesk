<?php

namespace App\Domains\Tenancy\Services;

use App\Support\LocaleSupport;
use Illuminate\Support\Facades\App;

class CentralSeoService
{
    private const PATHS = [
        'home' => '/',
        'register' => '/register',
        'login' => '/login',
    ];

    private array $stringCache = [];

    public function shared(): array
    {
        return [
            'siteUrl' => $this->siteUrl(),
            'siteName' => config('app.name', 'helpefi'),
            'ogImage' => $this->ogImageUrl(),
            'locales' => LocaleSupport::APP_LOCALES,
        ];
    }

    public function meta(string $page, string $brand, int $trialDays, string $currency = 'USD'): array
    {
        $strings = $this->seoStrings();
        $replacements = ['brand' => $brand, 'days' => (string) $trialDays];

        $title = $this->interpolate($strings["{$page}_title"] ?? $brand, $replacements);
        $description = $this->interpolate($strings["{$page}_description"] ?? '', $replacements);

        return [
            'title' => $title,
            'description' => $description,
            'robots' => $page === 'login' ? 'noindex, follow' : 'index, follow',
            'canonical' => $this->siteUrl().(self::PATHS[$page] ?? '/'),
            'brand' => $brand,
            'ogImage' => $this->ogImageUrl(),
            'ogLocale' => $this->ogLocale(),
            'ogLocaleAlternates' => $this->ogLocaleAlternates(),
            'jsonLd' => $page === 'home'
                ? $this->jsonLd($brand, $description, $trialDays, $strings, $currency)
                : null,
        ];
    }

    public function siteUrl(): string
    {
        return rtrim((string) config('app.url'), '/');
    }

    public function ogLocale(): string
    {
        return str_replace('-', '_', App::getLocale());
    }

    public function ogImageUrl(): ?string
    {
        $configured = config('tenancy.central_og_image');

        if (is_string($configured) && $configured !== '') {
            return $configured;
        }

        return file_exists(public_path('og-image.png'))
            ? $this->siteUrl().'/og-image.png'
            : null;
    }

    public function sitemapEntries(): array
    {
        return [
            [
                'loc' => route('central.home'),
                'changefreq' => 'weekly',
                'priority' => '1.0',
            ],
            [
                'loc' => route('central.register'),
                'changefreq' => 'monthly',
                'priority' => '0.8',
            ],
        ];
    }

    public function robotsLines(): array
    {
        return [
            'User-agent: *',
            'Allow: /',
            'Disallow: /admin/',
            'Disallow: /dashboard',
            '',
            'Sitemap: '.$this->siteUrl().'/sitemap.xml',
        ];
    }

    private function ogLocaleAlternates(): array
    {
        $current = App::getLocale();

        return collect(LocaleSupport::APP_LOCALES)
            ->reject(fn (string $code): bool => $code === $current)
            ->map(fn (string $code): string => str_replace('-', '_', $code))
            ->values()
            ->all();
    }

    private function jsonLd(string $brand, string $description, int $trialDays, array $strings, string $currency): string
    {
        $baseUrl = $this->siteUrl();

        $graph = [
            '@context' => 'https://schema.org',
            '@graph' => [
                [
                    '@type' => 'Organization',
                    '@id' => "{$baseUrl}/#organization",
                    'name' => $brand,
                    'url' => $baseUrl,
                ],
                [
                    '@type' => 'WebSite',
                    '@id' => "{$baseUrl}/#website",
                    'name' => $brand,
                    'url' => $baseUrl,
                    'publisher' => ['@id' => "{$baseUrl}/#organization"],
                ],
                [
                    '@type' => 'SoftwareApplication',
                    '@id' => "{$baseUrl}/#software",
                    'name' => $brand,
                    'applicationCategory' => 'BusinessApplication',
                    'operatingSystem' => 'Web',
                    'url' => $baseUrl,
                    'description' => $description,
                    'offers' => [
                        '@type' => 'Offer',
                        'price' => '0',
                        'priceCurrency' => $currency,
                        'description' => $this->interpolate(
                            $strings['trial_offer'] ?? '',
                            ['days' => (string) $trialDays]
                        ),
                    ],
                ],
            ],
        ];

        return (string) json_encode($graph, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    private function seoStrings(): array
    {
        $locale = App::getLocale();
        $strings = $this->loadSeoStrings($locale);

        if ($strings === [] && $locale !== 'en') {
            $strings = $this->loadSeoStrings('en');
        }

        return $strings;
    }

    private function loadSeoStrings(string $locale): array
    {
        if (array_key_exists($locale, $this->stringCache)) {
            return $this->stringCache[$locale];
        }

        $path = resource_path("js/locales/{$locale}/central.json");

        if (! is_file($path)) {
            return $this->stringCache[$locale] = [];
        }

        $decoded = json_decode((string) file_get_contents($path), true);

        if (! is_array($decoded)) {
            return $this->stringCache[$locale] = [];
        }

        $seo = $decoded['central']['seo'] ?? [];

        return $this->stringCache[$locale] = is_array($seo) ? $seo : [];
    }

    private function interpolate(string $value, array $replacements): string
    {
        foreach ($replacements as $key => $replacement) {
            $value = str_replace('{'.$key.'}', $replacement, $value);
        }

        return $value;
    }
}
