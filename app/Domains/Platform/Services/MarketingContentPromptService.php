<?php

namespace App\Domains\Platform\Services;

use App\Domains\Platform\Support\MarketingContentType;

class MarketingContentPromptService
{
    public function __construct(private MarketingContentCorpusService $corpus)
    {
    }

    public function systemPrompt(): string
    {
        return implode("\n", [
            'You are a senior B2B SaaS content strategist for Helpefi, a helpdesk and ITSM platform.',
            'Output ONLY valid JSON. No markdown fences, no commentary.',
            'Follow Google EEAT principles:',
            '- Experience: use concrete support-team workflows and realistic scenarios.',
            '- Expertise: accurate helpdesk/ITSM terminology; no vague marketing fluff.',
            '- Authoritativeness: specific product capabilities; honest comparisons.',
            '- Trustworthiness: acknowledge trade-offs; no exaggerated claims.',
            'Never duplicate or closely paraphrase existing site copy.',
            'Use {brand}, {days}, {trialDays}, {contactEmail} placeholders where appropriate.',
            'Brand voice: clear, confident, practical — not hype-driven.',
        ]);
    }

    public function userPrompt(string $contentType, string $title, string $brief, ?string $slug, array $context = []): string
    {
        $brand = (string) config('marketing_seo.organization.name', 'Helpefi');
        $lines = [
            'Content type: '.MarketingContentType::label($contentType),
            'Title: '.$title,
            'Brief: '.$brief,
            'Brand: '.$brand,
        ];

        if ($slug) {
            $lines[] = 'Target slug: '.$slug;
        }

        if (! empty($context['competitor'])) {
            $lines[] = 'Competitor: '.$context['competitor'];
        }

        if (! empty($context['industry'])) {
            $lines[] = 'Industry: '.$context['industry'];
        }

        $lines[] = '';
        $lines[] = 'Avoid overlapping with these existing page titles:';
        $lines[] = $this->existingTitlesSummary();
        $lines[] = '';
        $lines[] = 'Available internal link targets (pick 3-6 relevant ones):';
        $lines[] = json_encode(array_slice($this->corpus->internalLinkTargets(), 0, 40));
        $lines[] = '';
        $lines[] = $this->schemaInstructions($contentType);

        return implode("\n", $lines);
    }

    private function existingTitlesSummary(): string
    {
        return collect($this->corpus->allEntries())
            ->pluck('title')
            ->filter()
            ->unique()
            ->take(25)
            ->implode('; ');
    }

    private function schemaInstructions(string $contentType): string
    {
        $shared = [
            'Return strict JSON with these top-level keys:',
            'content, seo, schema_markup, internal_links',
            '',
            'seo keys: seo_title (<=60 chars), meta_description (<=160), keywords (comma-separated, <=10)',
            'schema_markup: JSON-LD object with @context and @type (WebPage or Article as appropriate), name, description',
            'internal_links: array of {type, slug, label, anchor_text, path?} — pick from available targets',
        ];

        $contentSchema = match ($contentType) {
            MarketingContentType::COMPARISON => [
                'content keys: nav_label, competitor_name, badge, hero_title, hero_highlight, hero_subtitle,',
                'reasons (3 items: title, body), rows (6-8 items: feature, us [bool|string], them [bool|string]),',
                'faq (3-5 items: q, a), cta_title, cta_body',
            ],
            MarketingContentType::VERTICAL => [
                'content keys: nav_label, badge, hero_title, hero_highlight, hero_subtitle,',
                'pains (3 items: title, body), features (3-4 items: title, body),',
                'faq (3-5 items: q, a), cta_title, cta_body',
            ],
            MarketingContentType::BLOG_OUTLINE => [
                'content keys: title, slug, excerpt, outline (array of sections with heading, bullets[]),',
                'suggested_categories[], suggested_tags[], reading_minutes_estimate',
            ],
            default => [
                'content keys: nav_label, badge, hero_title, hero_highlight, hero_subtitle,',
                'features (3-4 items: title, body), faq (3-5 items: q, a), cta_title, cta_body',
            ],
        };

        return implode("\n", array_merge($shared, [''], $contentSchema));
    }
}
