<?php

namespace App\Domains\Tenancy\Support;

class MarketingBlogInternalLinks
{
    public static function forSlug(string $slug): array
    {
        return match ($slug) {
            'zendesk-pricing-alternatives-2026' => [
                self::compareLink('zendesk'),
                ['path' => '/features/ai', 'label_key' => 'central.feature_pages.ai.nav_label'],
                ['path' => '/pricing', 'label_key' => 'central.static_pages.pricing.nav_label'],
            ],
            'freshdesk-vs-freshservice-do-you-need-both' => [
                self::compareLink('freshdesk'),
                self::compareLink('freshservice'),
                ['path' => '/for/it-teams', 'label_key' => 'central.verticals.it-teams.nav_label'],
            ],
            'intercom-fin-pricing-explained' => [
                self::compareLink('intercom'),
                ['path' => '/features/live-chat', 'label_key' => 'central.feature_pages.live-chat.nav_label'],
                ['path' => '/features/ai', 'label_key' => 'central.feature_pages.ai.nav_label'],
            ],
            'helpdesk-for-shopify-stores' => [
                ['path' => '/for/ecommerce', 'label_key' => 'central.verticals.ecommerce.nav_label'],
                ['path' => '/features/integrations', 'label_key' => 'central.feature_pages.integrations.nav_label'],
                ['path' => '/features/knowledge-base', 'label_key' => 'central.feature_pages.knowledge-base.nav_label'],
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
}
