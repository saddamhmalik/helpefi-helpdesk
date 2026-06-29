<?php

namespace App\Domains\Tenancy\Services;

use App\Domains\Tenancy\Support\MarketingBlogDefinition;
use App\Domains\Tenancy\Support\MarketingStaticPageDefinition;
use App\Domains\Tenancy\Support\MarketingFeatureDefinition;
use App\Domains\Tenancy\Support\MarketingSeoContext;
use App\Domains\Tenancy\Support\VerticalLandingDefinition;
use App\Domains\Tenancy\Support\CompareLandingDefinition;
use App\Domains\Tenancy\Support\MigrateLandingDefinition;
use Illuminate\Http\Request;

class SchemaService
{
    private array $centralCache = [];

    public function __construct(
        private CentralSeoService $seo,
        private CentralSettingsService $settings,
        private MarketingJsonLd $jsonLd,
    ) {
    }

    public function forRequest(Request $request): ?array
    {
        if (! MarketingSeoContext::isMarketingRequest($request)) {
            return null;
        }

        $brand = config('app.name', 'Helpefi');
        $baseUrl = rtrim($this->seo->siteUrl(), '/');
        $canonical = $baseUrl.'/'.ltrim('/'.ltrim($request->path(), '/'), '/');
        $canonical = rtrim($canonical, '/');
        $canonical = $canonical === '' ? $baseUrl : $canonical;

        $socialUrls = collect($this->settings->socialLinks())
            ->pluck('url')
            ->filter(fn ($url) => is_string($url) && $url !== '')
            ->values()
            ->all();

        $email = config('marketing_seo.organization.contact_email');

        $graph = [
            $this->jsonLd->organization($brand, $baseUrl, $socialUrls, is_string($email) ? $email : null),
            $this->jsonLd->website($brand, $baseUrl),
            $this->softwareApplicationNode($brand, $baseUrl),
        ];

        if ($request->routeIs('central.static.pricing')) {
            $product = $this->productNode($brand, $baseUrl, $canonical);

            if ($product !== null) {
                $graph[] = $product;
            }
        }

        if ($request->routeIs('central.blog.show')) {
            $slug = (string) $request->route('slug');
            $post = MarketingBlogDefinition::find($slug);

            if (is_array($post)) {
                $title = (string) ($post['title'] ?? $brand);
                $description = (string) ($post['excerpt'] ?? '');
                $image = $post['og_image'] ?? null;

                $graph[] = $this->jsonLd->article(
                    $title,
                    $description,
                    $canonical,
                    $baseUrl,
                    $brand,
                    (string) ($post['published_at'] ?? now()->toDateString()),
                    isset($post['updated_at']) ? (string) $post['updated_at'] : null,
                    is_string($image) ? $this->absoluteUrl($image, $baseUrl) : null,
                );

                $graph[] = $this->jsonLd->breadcrumbList([
                    ['name' => $brand, 'url' => $baseUrl],
                    ['name' => 'Blog', 'url' => $baseUrl.'/blog'],
                    ['name' => $title, 'url' => $canonical],
                ]);
            }
        }

        if ($request->routeIs('central.blog.index')) {
            $graph[] = $this->jsonLd->breadcrumbList([
                ['name' => $brand, 'url' => $baseUrl],
                ['name' => 'Blog', 'url' => $canonical],
            ]);
        }

        if ($request->routeIs('central.features.index')) {
            $graph[] = $this->jsonLd->breadcrumbList([
                ['name' => $brand, 'url' => $baseUrl],
                ['name' => 'Features', 'url' => $canonical],
            ]);
        }

        if ($request->routeIs('central.feature')) {
            $slug = (string) $request->route('feature');
            $faq = $this->faqForFeatureSlug($slug);

            if ($faq !== null) {
                $graph[] = $faq;
            }

            $definition = MarketingFeatureDefinition::find($slug);
            $featureLabel = is_array($definition) ? (string) ($definition['nav_label'] ?? $definition['name'] ?? '') : '';

            if ($featureLabel !== '') {
                $graph[] = $this->jsonLd->breadcrumbList([
                    ['name' => $brand, 'url' => $baseUrl],
                    ['name' => 'Features', 'url' => $baseUrl.'/features'],
                    ['name' => $featureLabel, 'url' => $canonical],
                ]);
            }
        }

        if ($request->routeIs('central.vertical')) {
            $slug = (string) $request->route('vertical');
            $definition = VerticalLandingDefinition::find($slug);
            $label = is_array($definition) ? (string) ($definition['nav_label'] ?? $definition['badge'] ?? $definition['name'] ?? '') : '';

            if ($label !== '') {
                $graph[] = $this->jsonLd->breadcrumbList([
                    ['name' => $brand, 'url' => $baseUrl],
                    ['name' => 'Industries', 'url' => $baseUrl.'/industries'],
                    ['name' => $label, 'url' => $canonical],
                ]);
            }
        }

        if ($request->routeIs('central.compare')) {
            $slug = (string) $request->route('competitor');
            $definition = CompareLandingDefinition::find($slug);
            $label = is_array($definition) ? (string) ($definition['competitor_name'] ?? $definition['nav_label'] ?? $definition['slug'] ?? '') : '';
            $label = $label !== '' ? $label : $slug;

            $graph[] = $this->jsonLd->breadcrumbList([
                ['name' => $brand, 'url' => $baseUrl],
                ['name' => 'Compare', 'url' => $baseUrl.'/#compare'],
                ['name' => $label, 'url' => $canonical],
            ]);
        }

        if ($request->routeIs('central.migrate')) {
            $slug = (string) $request->route('source');
            $definition = MigrateLandingDefinition::find($slug);
            $label = is_array($definition) ? (string) ($definition['source_name'] ?? $definition['nav_label'] ?? $definition['slug'] ?? '') : '';
            $label = $label !== '' ? $label : $slug;

            $graph[] = $this->jsonLd->breadcrumbList([
                ['name' => $brand, 'url' => $baseUrl],
                ['name' => 'Migrate', 'url' => $baseUrl.'/#migrate'],
                ['name' => $label, 'url' => $canonical],
            ]);
        }

        if ($request->routeIs('central.static.*')) {
            $slug = str_replace('central.static.', '', (string) $request->route()?->getName());
            $definition = MarketingStaticPageDefinition::find($slug);
            $label = is_array($definition) ? (string) ($definition['nav_label'] ?? $definition['title'] ?? $definition['slug'] ?? '') : '';
            $label = $label !== '' ? $label : ucfirst(str_replace('-', ' ', $slug));

            if ($label !== '') {
                $graph[] = $this->jsonLd->breadcrumbList([
                    ['name' => $brand, 'url' => $baseUrl],
                    ['name' => $label, 'url' => $canonical],
                ]);
            }
        }

        return [
            '@context' => 'https://schema.org',
            '@graph' => $graph,
        ];
    }

    private function softwareApplicationNode(string $brand, string $baseUrl): array
    {
        $currency = (string) ($this->settings->currency() ?: 'USD');
        $trialDays = (int) ($this->settings->trialDays() ?: 14);
        $trialOffer = "{$trialDays}-day free trial";

        return $this->jsonLd->softwareApplication(
            $brand,
            'Helpefi is a secure, AI-native helpdesk & ITSM platform with true database isolation.',
            $baseUrl,
            $currency,
            $trialOffer,
        );
    }

    private function productNode(string $brand, string $baseUrl, string $pricingUrl): ?array
    {
        $plans = $this->settings->plansForDisplay();
        $currency = (string) ($this->settings->currency() ?: 'USD');

        if (! is_array($plans) || $plans === []) {
            return null;
        }

        $offers = collect($plans)
            ->filter(fn ($plan) => is_array($plan) && empty($plan['custom_pricing']))
            ->map(function (array $plan) use ($currency, $pricingUrl) {
                $price = $plan['price_monthly'] ?? null;

                if ($price === null || $price === '' || ! is_numeric($price)) {
                    return null;
                }

                $name = (string) ($plan['name'] ?? $plan['slug'] ?? 'Plan');

                return [
                    '@type' => 'Offer',
                    'name' => $name,
                    'url' => $pricingUrl,
                    'price' => (string) $price,
                    'priceCurrency' => $currency,
                    'availability' => 'https://schema.org/InStock',
                ];
            })
            ->filter()
            ->values()
            ->all();

        return [
            '@type' => 'Product',
            '@id' => "{$baseUrl}/#product",
            'name' => $brand,
            'brand' => [
                '@type' => 'Brand',
                'name' => $brand,
            ],
            'url' => $pricingUrl,
            'offers' => $offers,
        ];
    }

    private function faqForFeatureSlug(string $slug): ?array
    {
        $faqs = $this->centralSection("feature_pages.{$slug}.faq");

        if (! is_array($faqs) || $faqs === []) {
            return null;
        }

        $items = collect($faqs)
            ->map(fn ($faq) => is_array($faq) ? [
                'q' => (string) ($faq['q'] ?? $faq['question'] ?? ''),
                'a' => (string) ($faq['a'] ?? $faq['answer'] ?? ''),
            ] : null)
            ->filter(fn (?array $faq) => is_array($faq) && $faq['q'] !== '' && $faq['a'] !== '')
            ->values()
            ->all();

        return $this->jsonLd->faqPage($items);
    }

    private function centralSection(string $path): mixed
    {
        $central = $this->centralJson();

        return data_get($central, 'central.'.$path);
    }

    private function centralJson(): array
    {
        if ($this->centralCache !== []) {
            return $this->centralCache;
        }

        $path = resource_path('js/locales/en/central.json');

        if (! is_file($path)) {
            return $this->centralCache = [];
        }

        $decoded = json_decode((string) file_get_contents($path), true);

        return $this->centralCache = is_array($decoded) ? $decoded : [];
    }

    private function absoluteUrl(string $url, string $origin): string
    {
        $raw = trim($url);
        if ($raw === '') {
            return '';
        }
        if (preg_match('/^https?:\/\//i', $raw)) {
            return $raw;
        }
        if (str_starts_with($raw, '//')) {
            return "https:{$raw}";
        }
        if (str_starts_with($raw, '/')) {
            return $origin.$raw;
        }

        return "{$origin}/{$raw}";
    }
}

