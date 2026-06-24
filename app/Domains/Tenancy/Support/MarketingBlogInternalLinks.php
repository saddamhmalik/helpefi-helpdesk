<?php

namespace App\Domains\Tenancy\Support;

class MarketingBlogInternalLinks
{
    public static function forSlug(string $slug): array
    {
        return match ($slug) {
            'zendesk-pricing-alternatives-2026' => [
                ['path' => '/vs/zendesk', 'label_key' => 'central.comparisons.zendesk.nav_label'],
                ['path' => '/features/ai', 'label_key' => 'central.feature_pages.ai.nav_label'],
                ['path' => '/pricing', 'label_key' => 'central.static_pages.pricing.nav_label'],
            ],
            'freshdesk-vs-freshservice-do-you-need-both' => [
                ['path' => '/vs/freshdesk', 'label_key' => 'central.comparisons.freshdesk.nav_label'],
                ['path' => '/vs/freshservice', 'label_key' => 'central.comparisons.freshservice.nav_label'],
                ['path' => '/for/it-teams', 'label_key' => 'central.verticals.it-teams.nav_label'],
            ],
            'intercom-fin-pricing-explained' => [
                ['path' => '/vs/intercom', 'label_key' => 'central.comparisons.intercom.nav_label'],
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
}
