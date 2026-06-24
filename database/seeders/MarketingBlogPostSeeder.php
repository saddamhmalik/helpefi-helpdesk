<?php

namespace Database\Seeders;

use App\Domains\Platform\Models\MarketingBlogPost;
use Illuminate\Database\Seeder;

class MarketingBlogPostSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->posts() as $post) {
            MarketingBlogPost::query()->updateOrCreate(
                ['slug' => $post['slug']],
                $post,
            );
        }
    }

    private function posts(): array
    {
        return [
            [
                'slug' => 'ai-helpdesk-software-guide',
                'title' => 'How to choose AI helpdesk software in 2026',
                'excerpt' => 'A practical guide to evaluating AI customer support tools — deflection, Copilot, security, and total cost of ownership.',
                'body' => implode("\n\n", [
                    'AI helpdesk software has moved from novelty to expectation. Support leaders now evaluate platforms on how well AI reduces handle time, deflects repetitive tickets, and keeps agents in control.',
                    'Start with outcomes: measure what percentage of tickets can be deflected with your existing knowledge base, and how much agent time Copilot saves on draft replies.',
                    'Look for semantic search across articles and past tickets, not just keyword matching. The best AI customer support tools ground answers in your approved content.',
                    'Security matters. Confirm data retention policies, whether models train on your tickets, and how SSO and audit logs fit your compliance requirements.',
                    'Finally, compare total cost: base plan, AI add-ons, per-seat pricing, and whether ITSM or multi-brand support requires a separate product. Helpefi combines helpdesk, AI, and optional Service Desk in one platform with transparent pricing.',
                ]),
                'status' => MarketingBlogPost::STATUS_PUBLISHED,
                'published_at' => '2026-01-10 00:00:00',
                'reading_minutes' => 9,
                'related_slugs' => ['bring-your-own-database-and-storage', 'intercom-fin-pricing-explained'],
                'seo_title' => 'How to Choose AI Helpdesk Software',
                'seo_description' => 'Evaluate AI customer support tools: deflection, Copilot, security, and pricing. A practical guide from Helpefi.',
            ],
            [
                'slug' => 'bring-your-own-database-and-storage',
                'title' => 'Bring Your Own Database and Storage (BYOD & BYOS): the complete enterprise guide',
                'excerpt' => 'How to keep helpdesk data and files in your own AWS RDS, Aurora, S3, or Cloudflare R2 — eligibility, setup, migration, security, and troubleshooting for Helpefi BYOD and BYOS.',
                'body' => implode("\n\n", require __DIR__.'/content/bring-your-own-database-and-storage.php'),
                'status' => MarketingBlogPost::STATUS_PUBLISHED,
                'published_at' => '2026-06-19 00:00:00',
                'reading_minutes' => 14,
                'related_slugs' => ['ai-helpdesk-software-guide', 'zendesk-pricing-alternatives-2026'],
                'seo_title' => 'BYOD & BYOS Guide: Bring Your Own Database and Storage',
                'seo_description' => 'Complete guide to Helpefi BYOD and BYOS — AWS RDS, Aurora, S3, Cloudflare R2, data residency, migration, security groups, and enterprise setup.',
            ],
            [
                'slug' => 'zendesk-pricing-alternatives-2026',
                'title' => 'Zendesk pricing and alternatives in 2026: a support leader\'s guide',
                'excerpt' => 'Understand Zendesk Suite and Advanced AI costs, hidden fees, and when a unified helpdesk alternative delivers better TCO for mid-market teams.',
                'body' => implode("\n\n", require __DIR__.'/content/zendesk-pricing-alternatives-2026.php'),
                'status' => MarketingBlogPost::STATUS_PUBLISHED,
                'published_at' => '2026-06-01 00:00:00',
                'reading_minutes' => 11,
                'related_slugs' => ['ai-helpdesk-software-guide', 'freshdesk-vs-freshservice-do-you-need-both'],
                'seo_title' => 'Zendesk Pricing & Alternatives 2026',
                'seo_description' => 'Zendesk pricing breakdown, Advanced AI costs, and modern helpdesk alternatives with SLA, KB, and optional ITSM. Compare Helpefi free trial.',
            ],
            [
                'slug' => 'freshdesk-vs-freshservice-do-you-need-both',
                'title' => 'Freshdesk vs Freshservice: do you need both?',
                'excerpt' => 'When customer support and ITSM require two Freshworks products — and how unified helpdesk platforms with optional Service Desk ITSM compare.',
                'body' => implode("\n\n", require __DIR__.'/content/freshdesk-vs-freshservice-do-you-need-both.php'),
                'status' => MarketingBlogPost::STATUS_PUBLISHED,
                'published_at' => '2026-06-05 00:00:00',
                'reading_minutes' => 10,
                'related_slugs' => ['zendesk-pricing-alternatives-2026', 'helpdesk-for-shopify-stores'],
                'seo_title' => 'Freshdesk vs Freshservice: One Platform or Two?',
                'seo_description' => 'Compare Freshdesk and Freshservice for support and IT teams. Learn when one helpdesk with optional ITSM replaces two Freshworks contracts.',
            ],
            [
                'slug' => 'intercom-fin-pricing-explained',
                'title' => 'Intercom Fin pricing explained: seats, resolutions, and alternatives',
                'excerpt' => 'How Intercom Fin AI pricing works in 2026, what it costs at scale, and when a helpdesk-first platform with flat AI pricing is a better fit.',
                'body' => implode("\n\n", require __DIR__.'/content/intercom-fin-pricing-explained.php'),
                'status' => MarketingBlogPost::STATUS_PUBLISHED,
                'published_at' => '2026-06-10 00:00:00',
                'reading_minutes' => 9,
                'related_slugs' => ['ai-helpdesk-software-guide', 'zendesk-pricing-alternatives-2026'],
                'seo_title' => 'Intercom Fin Pricing Explained',
                'seo_description' => 'Intercom Fin AI resolution pricing vs flat AI Copilot add-ons. Compare Helpefi as an Intercom alternative for SLA-driven support teams.',
            ],
            [
                'slug' => 'helpdesk-for-shopify-stores',
                'title' => 'Helpdesk for Shopify stores: WISMO, deflection, and SLA at scale',
                'excerpt' => 'How D2C and Shopify brands unify order context, live chat, AI deflection, and SLAs in one customer support platform.',
                'body' => implode("\n\n", require __DIR__.'/content/helpdesk-for-shopify-stores.php'),
                'status' => MarketingBlogPost::STATUS_PUBLISHED,
                'published_at' => '2026-06-15 00:00:00',
                'reading_minutes' => 8,
                'related_slugs' => ['freshdesk-vs-freshservice-do-you-need-both', 'ai-helpdesk-software-guide'],
                'seo_title' => 'Helpdesk for Shopify Stores',
                'seo_description' => 'Shopify customer support software with order context in tickets, AI deflection, live chat, and SLA automation. Helpefi e-commerce guide.',
            ],
        ];
    }
}
