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
            // ── Existing posts (updated related_slugs for better cross-linking) ──
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
                'related_slugs' => ['ai-helpdesk-adoption-guide', 'intercom-fin-pricing-explained', 'sla-management-best-practices'],
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
                'related_slugs' => ['zendesk-pricing-alternatives-2026', 'jira-service-management-alternatives'],
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
                'related_slugs' => ['freshdesk-pricing-and-alternatives', 'intercom-alternative-for-support-teams', 'ai-helpdesk-adoption-guide'],
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
                'related_slugs' => ['freshservice-vs-helpefi', 'freshdesk-pricing-and-alternatives', 'sla-management-best-practices'],
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
                'related_slugs' => ['intercom-alternative-for-support-teams', 'ai-helpdesk-adoption-guide', 'zendesk-pricing-alternatives-2026'],
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
                'related_slugs' => ['sla-management-best-practices', 'customer-portal-best-practices', 'ai-helpdesk-software-guide'],
                'seo_title' => 'Helpdesk for Shopify Stores',
                'seo_description' => 'Shopify customer support software with order context in tickets, AI deflection, live chat, and SLA automation. Helpefi e-commerce guide.',
            ],

            // ── Comparison topic supporting articles ──
            [
                'slug' => 'freshservice-vs-helpefi',
                'title' => 'Freshservice vs Helpefi: comparing ITSM and customer support platforms',
                'excerpt' => 'Compare Freshservice ITSM pricing, features, and limitations against Helpefi\'s unified support-and-ITSM platform for blended teams.',
                'body' => implode("\n\n", require __DIR__.'/content/freshservice-vs-helpefi.php'),
                'status' => MarketingBlogPost::STATUS_PUBLISHED,
                'published_at' => '2026-06-20 10:00:00',
                'reading_minutes' => 10,
                'related_slugs' => ['freshdesk-vs-freshservice-do-you-need-both', 'jira-service-management-alternatives', 'sla-management-best-practices'],
                'seo_title' => 'Freshservice vs Helpefi: ITSM and Customer Support Compared',
                'seo_description' => 'Compare Freshservice ITSM pricing, ITIL capabilities, and limitations for blended support teams. See how Helpefi unifies customer support and ITSM in one platform.',
            ],
            [
                'slug' => 'freshdesk-pricing-and-alternatives',
                'title' => 'Freshdesk pricing and alternatives in 2026',
                'excerpt' => 'Freshdesk pricing breakdown, feature limitations at scale, and when a unified helpdesk alternative delivers better value for growing teams.',
                'body' => implode("\n\n", require __DIR__.'/content/freshdesk-pricing-and-alternatives.php'),
                'status' => MarketingBlogPost::STATUS_PUBLISHED,
                'published_at' => '2026-06-22 10:00:00',
                'reading_minutes' => 10,
                'related_slugs' => ['freshdesk-vs-freshservice-do-you-need-both', 'freshservice-vs-helpefi', 'zendesk-pricing-alternatives-2026'],
                'seo_title' => 'Freshdesk Pricing & Alternatives 2026',
                'seo_description' => 'Freshdesk pricing breakdown, omnichannel and AI limitations, and when a unified helpdesk like Helpefi delivers better value for growing support operations.',
            ],
            [
                'slug' => 'zoho-desk-pricing-guide',
                'title' => 'Zoho Desk pricing guide: features, limits, and alternatives',
                'excerpt' => 'Understand Zoho Desk pricing, Zia AI costs, ecosystem lock-in, and when a platform-agnostic helpdesk like Helpefi is a better choice.',
                'body' => implode("\n\n", require __DIR__.'/content/zoho-desk-pricing-guide.php'),
                'status' => MarketingBlogPost::STATUS_PUBLISHED,
                'published_at' => '2026-06-24 10:00:00',
                'reading_minutes' => 9,
                'related_slugs' => ['freshdesk-pricing-and-alternatives', 'help-scout-vs-helpefi-guide', 'customer-portal-best-practices'],
                'seo_title' => 'Zoho Desk Pricing Guide: Features, Limits, and Alternatives',
                'seo_description' => 'Zoho Desk pricing breakdown, Zia AI add-on costs, ecosystem lock-in, and when a platform-agnostic helpdesk alternative delivers better value.',
            ],
            [
                'slug' => 'help-scout-vs-helpefi-guide',
                'title' => 'Help Scout vs Helpefi: comparing simplicity and scale',
                'excerpt' => 'Evaluate Help Scout for growing teams: simplicity strengths, scaling limitations, and when a more complete helpdesk like Helpefi is the better fit.',
                'body' => implode("\n\n", require __DIR__.'/content/help-scout-vs-helpefi-guide.php'),
                'status' => MarketingBlogPost::STATUS_PUBLISHED,
                'published_at' => '2026-06-26 10:00:00',
                'reading_minutes' => 10,
                'related_slugs' => ['zoho-desk-pricing-guide', 'knowledge-base-strategy-guide', 'customer-portal-best-practices'],
                'seo_title' => 'Help Scout vs Helpefi: Comparing Helpdesk Options for Growing Teams',
                'seo_description' => 'Compare Help Scout and Helpefi for growing support teams. Evaluate simplicity, scaling limitations, SLA enforcement, AI capabilities, and multi-brand support.',
            ],
            [
                'slug' => 'jira-service-management-alternatives',
                'title' => 'Jira Service Management alternatives for customer support teams',
                'excerpt' => 'When Jira Service Management is overkill for customer support — and how unified helpdesk platforms compare for blended support and IT operations.',
                'body' => implode("\n\n", require __DIR__.'/content/jira-service-management-alternatives.php'),
                'status' => MarketingBlogPost::STATUS_PUBLISHED,
                'published_at' => '2026-06-28 10:00:00',
                'reading_minutes' => 10,
                'related_slugs' => ['freshservice-vs-helpefi', 'freshdesk-vs-freshservice-do-you-need-both', 'sla-management-best-practices'],
                'seo_title' => 'Jira Service Management Alternatives for Customer Support',
                'seo_description' => 'Compare Jira Service Management with unified helpdesk alternatives for blended customer support and IT service desk operations. Evaluate pricing, ITSM, and multi-brand support.',
            ],
            [
                'slug' => 'intercom-alternative-for-support-teams',
                'title' => 'Intercom alternative for support teams that need a real helpdesk',
                'excerpt' => 'When Intercom\'s messaging-first approach falls short for ticket-centric support, and how helpdesk-first platforms compare for SLA enforcement and team scalability.',
                'body' => implode("\n\n", require __DIR__.'/content/intercom-alternative-for-support-teams.php'),
                'status' => MarketingBlogPost::STATUS_PUBLISHED,
                'published_at' => '2026-06-30 10:00:00',
                'reading_minutes' => 9,
                'related_slugs' => ['intercom-fin-pricing-explained', 'zendesk-pricing-alternatives-2026', 'ai-helpdesk-adoption-guide'],
                'seo_title' => 'Intercom Alternative for Support Teams: Helpdesk-First Comparison',
                'seo_description' => 'Compare Intercom with helpdesk-first alternatives for support teams. Evaluate SLA enforcement, ticket management, AI pricing, and shared inbox capabilities.',
            ],

            // ── Feature topic supporting articles ──
            [
                'slug' => 'sla-management-best-practices',
                'title' => 'SLA management best practices for modern support teams',
                'excerpt' => 'Design SLA policies that match staffing, configure business hours and escalations, and report compliance stakeholders trust.',
                'body' => implode("\n\n", require __DIR__.'/content/sla-management-best-practices.php'),
                'status' => MarketingBlogPost::STATUS_PUBLISHED,
                'published_at' => '2026-07-02 10:00:00',
                'reading_minutes' => 11,
                'related_slugs' => ['ai-helpdesk-adoption-guide', 'helpdesk-for-shopify-stores', 'freshservice-vs-helpefi'],
                'seo_title' => 'SLA Management Best Practices for Support Teams',
                'seo_description' => 'SLA management best practices: design policies that match staffing, configure business hours and escalations, and report compliance stakeholders trust. Helpefi guide.',
            ],
            [
                'slug' => 'ai-helpdesk-adoption-guide',
                'title' => 'AI helpdesk adoption guide: deploying Copilot, deflection, and smart routing',
                'excerpt' => 'A practical guide to deploying AI in your helpdesk — Copilot for agents, deflection on portal and chat, measuring impact, and scaling across your operation.',
                'body' => implode("\n\n", require __DIR__.'/content/ai-helpdesk-adoption-guide.php'),
                'status' => MarketingBlogPost::STATUS_PUBLISHED,
                'published_at' => '2026-07-04 10:00:00',
                'reading_minutes' => 10,
                'related_slugs' => ['ai-helpdesk-software-guide', 'sla-management-best-practices', 'knowledge-base-strategy-guide'],
                'seo_title' => 'AI Helpdesk Adoption Guide: Copilot, Deflection, and Smart Routing',
                'seo_description' => 'Practical AI helpdesk adoption guide: deploy Copilot for agents, set up deflection on portal and chat, measure impact honestly, and scale AI across your support operation.',
            ],
            [
                'slug' => 'customer-portal-best-practices',
                'title' => 'Customer portal best practices: deflection, branding, and self-service',
                'excerpt' => 'Design a customer portal that deflects tickets, captures structured submissions, and builds brand trust through custom domains and multi-brand support.',
                'body' => implode("\n\n", require __DIR__.'/content/customer-portal-best-practices.php'),
                'status' => MarketingBlogPost::STATUS_PUBLISHED,
                'published_at' => '2026-07-06 10:00:00',
                'reading_minutes' => 10,
                'related_slugs' => ['knowledge-base-strategy-guide', 'help-scout-vs-helpefi-guide', 'helpdesk-for-shopify-stores'],
                'seo_title' => 'Customer Portal Best Practices: Deflection, Branding, and Self-Service',
                'seo_description' => 'Customer portal best practices: knowledge base integration, ticket submission design, custom domains, multi-brand portal strategy, and AI deflection.',
            ],
            [
                'slug' => 'knowledge-base-strategy-guide',
                'title' => 'Knowledge base strategy: writing, organizing, and measuring for deflection',
                'excerpt' => 'Build a knowledge base that deflects tickets, powers AI Copilot, and scales with your team. Covers article writing, collections, analytics, and content maintenance.',
                'body' => implode("\n\n", require __DIR__.'/content/knowledge-base-strategy-guide.php'),
                'status' => MarketingBlogPost::STATUS_PUBLISHED,
                'published_at' => '2026-07-08 10:00:00',
                'reading_minutes' => 11,
                'related_slugs' => ['customer-portal-best-practices', 'ai-helpdesk-adoption-guide', 'help-scout-vs-helpefi-guide'],
                'seo_title' => 'Knowledge Base Strategy: Writing, Organizing, and Measuring for Deflection',
                'seo_description' => 'Knowledge base strategy guide: write articles that deflect tickets, organize with collections, measure performance with analytics, and integrate with AI Copilot.',
            ],
        ];
    }
}
