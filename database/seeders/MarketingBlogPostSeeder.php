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
                'related_slugs' => ['bring-your-own-database-and-storage'],
                'seo_title' => 'How to Choose AI Helpdesk Software',
                'seo_description' => 'Evaluate AI customer support tools: deflection, Copilot, security, and pricing. A practical guide from Helpefi.',
            ],
            [
                'slug' => 'bring-your-own-database-and-storage',
                'title' => 'Bring Your Own Database and Storage (BYOD & BYOS): the complete enterprise guide',
                'excerpt' => 'How to keep helpdesk data and files in your own AWS RDS, Aurora, S3, or Cloudflare R2 — eligibility, setup, migration, security, and troubleshooting for helpefi BYOD and BYOS.',
                'body' => implode("\n\n", require __DIR__.'/content/bring-your-own-database-and-storage.php'),
                'status' => MarketingBlogPost::STATUS_PUBLISHED,
                'published_at' => '2026-06-19 00:00:00',
                'reading_minutes' => 14,
                'related_slugs' => ['ai-helpdesk-software-guide'],
                'seo_title' => 'BYOD & BYOS Guide: Bring Your Own Database and Storage',
                'seo_description' => 'Complete guide to helpefi BYOD and BYOS — AWS RDS, Aurora, S3, Cloudflare R2, data residency, migration, security groups, and enterprise setup.',
            ],
        ];
    }
}
