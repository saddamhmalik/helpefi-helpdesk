<?php

namespace Database\Seeders;

use App\Domains\Platform\Models\MarketingBlogPost;
use Illuminate\Database\Seeder;

class MarketingBlogPostSeeder extends Seeder
{
    public function run(): void
    {
        if (MarketingBlogPost::query()->exists()) {
            return;
        }

        MarketingBlogPost::query()->create([
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
            'seo_title' => 'How to Choose AI Helpdesk Software',
            'seo_description' => 'Evaluate AI customer support tools: deflection, Copilot, security, and pricing. A practical guide from Helpefi.',
        ]);
    }
}
