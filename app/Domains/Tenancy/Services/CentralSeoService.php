<?php

namespace App\Domains\Tenancy\Services;

class CentralSeoService
{
    public function shared(): array
    {
        return [
            'siteUrl' => $this->siteUrl(),
            'siteName' => config('app.name', 'Helpdesk'),
            'ogImage' => $this->ogImageUrl(),
        ];
    }

    public function siteUrl(): string
    {
        return rtrim((string) config('app.url'), '/');
    }

    public function ogImageUrl(): ?string
    {
        $configured = config('tenancy.central_og_image');

        if (is_string($configured) && $configured !== '') {
            return $configured;
        }

        return $this->siteUrl().'/og-image.svg';
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
}
