<?php

namespace App\Domains\Tenancy\Support;

class MarketingBlogInternalLinks
{
    public static function forSlug(string $slug): array
    {
        return match ($slug) {
            'zendesk-pricing-alternatives-2026' => [
                self::compareLink('zendesk'),
                self::featureLink('ai'),
                ['path' => '/pricing', 'label' => (string) (config('marketing_static_content.pricing.nav_label') ?? 'Pricing')],
            ],
            'freshdesk-vs-freshservice-do-you-need-both' => [
                self::compareLink('freshdesk'),
                self::compareLink('freshservice'),
                self::verticalLink('saas'),
            ],
            'intercom-fin-pricing-explained' => [
                self::compareLink('intercom'),
                self::featureLink('live-chat'),
                self::featureLink('ai'),
            ],
            'helpdesk-for-shopify-stores' => [
                self::verticalLink('ecommerce'),
                self::featureLink('integrations'),
                self::featureLink('knowledge-base'),
            ],
            default => [],
        };
    }

    private static function compareLink(string $slug): array
    {
        $content = config("marketing_comparison_content.{$slug}");

        return [
            'path' => CompareLandingDefinition::path($slug),
            'label' => (string) ($content['nav_label'] ?? $slug),
        ];
    }

    private static function featureLink(string $slug): array
    {
        $content = config("marketing_feature_content.{$slug}");

        return [
            'path' => MarketingFeatureDefinition::path($slug),
            'label' => (string) ($content['nav_label'] ?? $slug),
        ];
    }

    private static function verticalLink(string $slug): array
    {
        $content = config("marketing_vertical_content.{$slug}");

        return [
            'path' => VerticalLandingDefinition::path($slug),
            'label' => (string) ($content['nav_label'] ?? $slug),
        ];
    }
}
