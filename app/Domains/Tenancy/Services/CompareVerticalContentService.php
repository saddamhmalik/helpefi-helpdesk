<?php

namespace App\Domains\Tenancy\Services;

use App\Domains\Platform\Services\MarketingPageContentService;
use App\Domains\Platform\Support\MarketingContentType;
use App\Domains\Tenancy\Support\CompareLandingDefinition;
use App\Domains\Tenancy\Support\CompareVerticalDefinition;
use App\Domains\Tenancy\Support\MarketingContentInterpolator;

class CompareVerticalContentService
{
    public function __construct(
        private MarketingPageContentService $pages,
        private MarketingContentInterpolator $interpolator,
    ) {
    }

    public function for(string $competitor, string $vertical): ?array
    {
        $compareContent = $this->pages->resolve(
            MarketingContentType::COMPARISON,
            'marketing_comparison_content',
            $competitor,
        );

        if ($compareContent === null) {
            $compareSlug = CompareLandingDefinition::slugFromComparison($competitor);
            if ($compareSlug !== null) {
                $compareContent = $this->pages->resolve(
                    MarketingContentType::COMPARISON,
                    'marketing_comparison_content',
                    $compareSlug,
                );
            }
        }

        $verticalContent = $this->pages->resolve(
            MarketingContentType::VERTICAL,
            'marketing_vertical_content',
            $vertical,
        );

        if ($compareContent === null || $verticalContent === null) {
            return null;
        }

        $competitorName = $compareContent['competitor_name']
            ?? ucfirst(str_replace('-', ' ', $competitor));

        $verticalName = $verticalContent['nav_label']
            ?? ucfirst(str_replace('-', ' ', $vertical));

        return $this->mergeContent($compareContent, $verticalContent, $competitorName, $verticalName, $competitor);
    }

    private function mergeContent(
        array $compare,
        array $vertical,
        string $competitorName,
        string $verticalName,
        string $competitorSlug,
    ): array {
        $verticalFeatures = array_map(fn (array $f) => [
            'title' => $f['title'] ?? '',
            'body' => $f['body'] ?? '',
        ], $vertical['features'] ?? []);

        $verticalPains = array_map(fn (array $p) => [
            'title' => ($p['title'] ?? ''),
            'body' => ($p['body'] ?? ''),
        ], $vertical['pains'] ?? []);

        $comparisonRows = $compare['rows'] ?? [];

        $mergedFaq = array_merge(
            array_slice($vertical['faq'] ?? [], 0, 3),
            array_slice($compare['faq'] ?? [], 0, 3),
        );

        $conclusionBody = "{$competitorName} works well for many teams, but if you're in the {$verticalName} industry, the choice is clearer. "
            . "You need a platform that understands ".strtolower($verticalName)."-specific workflows: "
            . $this->industryPainSummary($verticalPains)
            . ' Helpefi delivers those capabilities while offering better pricing, modern AI features, and a unified inbox that grows with you.';

        return [
            'competitor_name' => $competitorName,
            'nav_label' => $competitorName.' alternative for '.$verticalName,
            'badge' => ($compare['badge'] ?? $competitorName.' alternative').' for '.$verticalName,
            'hero_title' => 'The '.$competitorName.' alternative for '.$verticalName,
            'hero_highlight' => $vertical['hero_highlight'] ?? 'Built for your industry.',
            'hero_subtitle' => $vertical['hero_subtitle'] ?? 'Support your team with a helpdesk that understands your workflows.',
            'intro' => "If you're evaluating alternatives to {$competitorName} for your {$verticalName} team, you need a helpdesk that combines the power of a modern platform "
                . "with industry-specific workflows. Helpefi gives you the best of both worlds: a unified inbox, AI-powered automation, SLA management, "
                . "and a self-service portal — all tailored to the way {$verticalName} organizations operate. "
                . 'Stop juggling siloed tools and give your team a single platform that your customers will love.',
            'reasons' => $verticalPains,
            'rows' => $comparisonRows,
            'deep_dives' => [
                [
                    'title' => "Why {$verticalName} teams switch from {$competitorName}",
                    'body' => "{$competitorName} is a capable platform, but {$verticalName} organizations often hit limitations: "
                        . 'rigid pricing, limited industry-specific features, or a lack of customization. '
                        . 'Helpefi was built to address these gaps with flexible SLA policies, omnichannel support, AI-powered deflection, and optional ITSM — '
                        . "all at a predictable price. Teams in the {$verticalName} space particularly value our data residency options, "
                        . 'branded customer portals, and the ability to scale from a small team to enterprise without switching platforms.',
                ],
                [
                    'title' => "Key features for {$verticalName} support teams",
                    'body' => "Whether you're handling customer inquiries, partner requests, or internal IT tickets, Helpefi gives {$verticalName} teams "
                        . 'the tools they need: a shared inbox that unifies email, chat, SMS, and social; automation rules that route and prioritize '
                        . 'by urgency or topic; AI Copilot that drafts replies from your knowledge base; SLA policies that enforce response targets '
                        . 'by customer tier or issue type; and a branded self-service portal that deflects common questions before they become tickets.',
                ],
            ],
            'screenshots' => $compare['screenshots'] ?? [],
            'updated_at' => $compare['updated_at'] ?? null,
            'author' => $compare['author'] ?? null,
            'reviewer' => $compare['reviewer'] ?? null,
            'pros' => [
                'us_title' => 'Why Helpefi works for '.$verticalName,
                'them_title' => "What {$competitorName} does well",
                'us' => array_slice($verticalFeatures, 0, 4),
                'them' => $compare['pros']['them'] ?? [],
            ],
            'cons' => [
                'us_title' => 'Helpefi trade-offs',
                'them_title' => "Where {$competitorName} falls short for {$verticalName}",
                'us' => $compare['cons']['us'] ?? [],
                'them' => $compare['cons']['them'] ?? [],
            ],
            'who_them' => [
                'title' => "Who should stick with {$competitorName}",
                'paragraphs' => [
                    "{$competitorName} may still be the right choice if your team is deeply invested in its ecosystem, "
                        . 'needs very specific integrations that only they offer, or has custom workflows built on their platform. '
                        . "If you're not in the {$verticalName} vertical, their general-purpose features may suffice.",
                ],
            ],
            'who_us' => [
                'title' => "Why {$verticalName} teams choose Helpefi",
                'paragraphs' => [
                    "Helpefi is built for growing teams that need a modern, flexible helpdesk without enterprise complexity or pricing. "
                        . "{$verticalName} organizations choose us for our industry-aware features, transparent pricing, "
                        . 'AI-powered automation, and the ability to start small and scale to full ITSM without migrating platforms.',
                ],
            ],
            'use_cases' => [
                'title' => "Use cases: {$competitorName} vs Helpefi for {$verticalName}",
                'items' => [
                    [
                        'title' => 'High-volume support',
                        'body' => "Handle {$verticalName} support spikes with automation rules and AI deflection — without adding headcount.",
                    ],
                    [
                        'title' => 'Multi-channel customer service',
                        'body' => 'Unify email, chat, SMS, and social into one inbox with full customer history and context.',
                    ],
                    [
                        'title' => 'Partner and supplier portals',
                        'body' => 'Give your distributors and partners a branded self-service portal for ticket submission and tracking.',
                    ],
                ],
            ],
            'alternatives' => $compare['alternatives'] ?? [],
            'migration' => $compare['migration'] ?? [],
            'faq' => $mergedFaq,
            'conclusion' => [
                'title' => "{$competitorName} vs Helpefi: the verdict for {$verticalName}",
                'body' => $conclusionBody,
            ],
            'related_links' => $vertical['related_links'] ?? [],
            'external_references' => $compare['external_references'] ?? [],
            'cta_title' => "Try Helpefi for {$verticalName} — free for {$this->getTrialDays()} days",
            'cta_body' => "See why {$verticalName} teams are switching. No credit card required. Full platform access.",
        ];
    }

    private function industryPainSummary(array $pains): string
    {
        $summaries = array_map(fn (array $p) => $p['title'] ?? '', $pains);
        $filtered = array_filter($summaries);

        if (count($filtered) === 0) {
            return 'industry-specific workflows and compliance needs. ';
        }

        return implode(', ', $filtered).'. ';
    }

    private function getTrialDays(): string
    {
        try {
            return (string) app(\App\Domains\Tenancy\Services\CentralSettingsService::class)->trialDays();
        } catch (\Throwable) {
            return '14';
        }
    }
}
