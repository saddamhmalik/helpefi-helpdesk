<?php

namespace App\Domains\Tenancy\Services;

class MarketingJsonLd
{
    public function encode(array $graph): string
    {
        return (string) json_encode([
            '@context' => 'https://schema.org',
            '@graph' => $graph,
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    public function organization(string $brand, string $baseUrl, array $sameAs = [], ?string $email = null): array
    {
        $node = [
            '@type' => 'Organization',
            '@id' => "{$baseUrl}/#organization",
            'name' => $brand,
            'url' => $baseUrl,
        ];

        $logo = config('marketing_seo.organization.logo_path');

        if (is_string($logo) && $logo !== '') {
            $node['logo'] = str_starts_with($logo, 'http') ? $logo : $baseUrl.$logo;
        }

        if ($sameAs !== []) {
            $node['sameAs'] = array_values($sameAs);
        }

        if (is_string($email) && $email !== '') {
            $node['contactPoint'] = [[
                '@type' => 'ContactPoint',
                'contactType' => config('marketing_seo.organization.contact_type', 'customer support'),
                'email' => $email,
                'availableLanguage' => ['English'],
            ]];
        }

        $parentName = config('marketing_seo.organization.parent_company_name');
        $parentUrl = config('marketing_seo.organization.parent_company_url');

        if (is_string($parentName) && $parentName !== '' && is_string($parentUrl) && $parentUrl !== '') {
            $node['parentOrganization'] = [
                '@type' => 'Organization',
                'name' => $parentName,
                'url' => $parentUrl,
            ];
        }

        return $node;
    }

    public function website(string $brand, string $baseUrl): array
    {
        return [
            '@type' => 'WebSite',
            '@id' => "{$baseUrl}/#website",
            'name' => $brand,
            'url' => $baseUrl,
            'inLanguage' => 'en',
            'publisher' => ['@id' => "{$baseUrl}/#organization"],
        ];
    }

    public function softwareApplication(string $brand, string $description, string $baseUrl, string $currency, string $trialOffer): array
    {
        return [
            '@type' => 'SoftwareApplication',
            '@id' => "{$baseUrl}/#software",
            'name' => $brand,
            'applicationCategory' => 'BusinessApplication',
            'applicationSubCategory' => 'Help Desk Software',
            'operatingSystem' => 'Web',
            'url' => $baseUrl,
            'description' => $description,
            'offers' => [
                '@type' => 'Offer',
                'price' => '0',
                'priceCurrency' => $currency,
                'description' => $trialOffer,
            ],
        ];
    }

    public function webPage(string $title, string $description, string $canonical, string $baseUrl): array
    {
        return [
            '@type' => 'WebPage',
            '@id' => "{$canonical}#webpage",
            'url' => $canonical,
            'name' => $title,
            'description' => $description,
            'inLanguage' => 'en',
            'isPartOf' => ['@id' => "{$baseUrl}/#website"],
            'about' => ['@id' => "{$baseUrl}/#software"],
        ];
    }

    public function breadcrumbList(array $items): array
    {
        return [
            '@type' => 'BreadcrumbList',
            'itemListElement' => collect($items)
                ->values()
                ->map(fn (array $item, int $index) => [
                    '@type' => 'ListItem',
                    'position' => $index + 1,
                    'name' => $item['name'],
                    'item' => $item['url'],
                ])
                ->all(),
        ];
    }

    public function faqPage(array $faqs): ?array
    {
        if ($faqs === []) {
            return null;
        }

        return [
            '@type' => 'FAQPage',
            'mainEntity' => collect($faqs)
                ->map(fn (array $faq) => [
                    '@type' => 'Question',
                    'name' => $faq['q'],
                    'acceptedAnswer' => [
                        '@type' => 'Answer',
                        'text' => $faq['a'],
                    ],
                ])
                ->all(),
        ];
    }

    public function article(
        string $headline,
        string $description,
        string $canonical,
        string $baseUrl,
        string $brand,
        string $publishedAt,
        ?string $updatedAt,
        ?string $imageUrl,
    ): array {
        $node = [
            '@type' => 'BlogPosting',
            '@id' => "{$canonical}#article",
            'headline' => $headline,
            'description' => $description,
            'url' => $canonical,
            'datePublished' => $publishedAt,
            'dateModified' => $updatedAt ?: $publishedAt,
            'inLanguage' => 'en',
            'author' => [
                '@type' => 'Organization',
                'name' => $brand,
            ],
            'publisher' => ['@id' => "{$baseUrl}/#organization"],
            'isPartOf' => ['@id' => "{$baseUrl}/#website"],
            'mainEntityOfPage' => ['@id' => "{$canonical}#webpage"],
        ];

        if (is_string($imageUrl) && $imageUrl !== '') {
            $node['image'] = [$imageUrl];
        }

        return $node;
    }
}
