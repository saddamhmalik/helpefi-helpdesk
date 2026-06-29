<?php

namespace App\Domains\Platform\Services;

class CompetitorComparisonService
{
    public function forSlug(string $slug): ?array
    {
        $key = $this->normalizeSlug($slug);
        $competitors = $this->competitors();

        $competitor = $competitors[$key] ?? null;

        if (! $competitor) {
            return null;
        }

        return [
            'competitor' => [
                'slug' => $key,
                'name' => $competitor['name'],
                'weaknesses' => $competitor['weaknesses'],
            ],
            'helpefi' => [
                'name' => 'helpefi',
                'advantages' => [
                    [
                        'title' => 'True database isolation',
                        'body' => 'Database-per-tenant by default so each workspace has clean isolation, safer upgrades, and predictable performance.',
                    ],
                    [
                        'title' => 'Grounded AI Agent Copilot, natively included',
                        'body' => 'AI assistance that’s designed for support workflows and can stay anchored to your helpdesk data and policies.',
                    ],
                    [
                        'title' => 'Optional ITIL Service Desk (ITSM)',
                        'body' => 'Add ITIL-style service desk workflows when you need them—without forcing every team into an ITSM SKU.',
                    ],
                ],
            ],
            'matrix' => [
                'headline' => "Helpefi vs {$competitor['name']}",
                'rows' => $competitor['rows'],
            ],
        ];
    }

    private function normalizeSlug(string $slug): string
    {
        $slug = strtolower(trim($slug));

        return match ($slug) {
            'helpscout', 'help-scout', 'help_scout', 'help-scouts' => 'help-scout',
            'zoho', 'zoho-desk', 'zohodesk', 'zoho_desk' => 'zoho-desk',
            'zendesk-vs-helpefi' => 'zendesk',
            'freshdesk-vs-helpefi' => 'freshdesk',
            'intercom-vs-helpefi' => 'intercom',
            'front-vs-helpefi' => 'front',
            'help-scout-vs-helpefi' => 'help-scout',
            default => $slug,
        };
    }

    private function competitors(): array
    {
        return [
            'zendesk' => [
                'name' => 'Zendesk',
                'weaknesses' => [
                    'Aggressive per-seat pricing can compound quickly as teams grow.',
                    'Core functionality often expands through add-ons and tier jumps.',
                    'AI capabilities commonly land behind higher tiers or add-on SKUs.',
                ],
                'rows' => $this->defaultRows('Zendesk', [
                    'pricing_model' => 'Per-seat pricing tends to scale steeply',
                    'ai_included' => 'Often gated by tier / add-on',
                ]),
            ],
            'freshdesk' => [
                'name' => 'Freshdesk',
                'weaknesses' => [
                    'Feature fragmentation across products can add operational overhead.',
                    'AI and automation often depend on tier or add-on packaging.',
                    'Cross-team scaling can increase per-agent costs.',
                ],
                'rows' => $this->defaultRows('Freshdesk', [
                    'pricing_model' => 'Per-agent pricing with tiered limits',
                    'ai_included' => 'Commonly tied to higher tiers',
                ]),
            ],
            'freshservice' => [
                'name' => 'Freshservice',
                'weaknesses' => [
                    'ITSM-first packaging can be heavier than what support teams need.',
                    'Per-agent pricing plus module upgrades can inflate TCO.',
                    'AI features frequently sit behind premium plans.',
                ],
                'rows' => $this->defaultRows('Freshservice', [
                    'itil_itsm' => 'ITSM-first (good for IT, heavier for support)',
                    'pricing_model' => 'Per-agent pricing plus tier upgrades',
                    'ai_included' => 'Typically higher tier',
                ]),
            ],
            'zoho-desk' => [
                'name' => 'Zoho Desk',
                'weaknesses' => [
                    'Ecosystem-driven complexity can make implementation and ownership harder.',
                    'Advanced automation and AI are commonly tied to upper plans.',
                    'Teams often outgrow limits and need plan upgrades.',
                ],
                'rows' => $this->defaultRows('Zoho Desk', [
                    'pricing_model' => 'Per-agent pricing; advanced features often in upper tiers',
                    'ai_included' => 'Often add-on / higher tier',
                ]),
            ],
            'help-scout' => [
                'name' => 'Help Scout',
                'weaknesses' => [
                    'Pricing can rise quickly as inboxes, agents, and add-ons expand.',
                    'Advanced capabilities may require higher plans.',
                    'Not designed for teams that need a full service-desk style workflow.',
                ],
                'rows' => $this->defaultRows('Help Scout', [
                    'itil_itsm' => 'Not an ITSM / service desk product',
                    'pricing_model' => 'Per-agent with limits; add-ons as you scale',
                    'ai_included' => 'Varies by plan / add-on',
                ]),
            ],
            'intercom' => [
                'name' => 'Intercom',
                'weaknesses' => [
                    'Per-resolution AI pricing can make costs unpredictable at scale.',
                    'Ticketing and SLA depth lag behind helpdesk-first platforms.',
                    'Campaign and messaging features add complexity support teams may not need.',
                ],
                'rows' => $this->defaultRows('Intercom', [
                    'pricing_model' => 'Per-seat plus usage-based AI fees',
                    'ai_included' => 'Fin AI priced per resolution',
                ]),
            ],
            'front' => [
                'name' => 'Front',
                'weaknesses' => [
                    'Shared inbox focus can limit SLA, ITSM, and service catalog depth.',
                    'Per-seat pricing climbs as teams and inboxes grow.',
                    'Built for email collaboration more than full helpdesk operations.',
                ],
                'rows' => $this->defaultRows('Front', [
                    'itil_itsm' => 'Not an ITSM platform',
                    'pricing_model' => 'Per-seat with tiered limits',
                    'ai_included' => 'Add-on / higher tier',
                ]),
            ],
        ];
    }

    private function defaultRows(string $competitorName, array $overrides = []): array
    {
        $base = [
            [
                'capability' => 'Database isolation (tenant security boundary)',
                'helpefi' => 'Database-per-tenant (true isolation)',
                'competitor' => 'Shared database / shared infrastructure model',
            ],
            [
                'capability' => 'AI Agent Copilot',
                'helpefi' => 'Included natively',
                'competitor' => 'Often gated by tier / add-on',
            ],
            [
                'capability' => 'Pricing predictability',
                'helpefi' => 'Designed to avoid surprise add-ons',
                'competitor' => 'Per-seat pricing that can climb with scale',
            ],
            [
                'capability' => 'ITIL Service Desk (ITSM)',
                'helpefi' => 'Optional add-on when you need it',
                'competitor' => 'Separate product / higher tiers',
            ],
            [
                'capability' => 'Go-live time',
                'helpefi' => 'Live in under 2 minutes',
                'competitor' => "Depends on {$competitorName} plan and setup complexity",
            ],
        ];

        $replacements = [
            'pricing_model' => fn (array $row) => $row['capability'] === 'Pricing predictability',
            'ai_included' => fn (array $row) => $row['capability'] === 'AI Agent Copilot',
            'itil_itsm' => fn (array $row) => $row['capability'] === 'ITIL Service Desk (ITSM)',
        ];

        foreach ($overrides as $key => $value) {
            $matcher = $replacements[$key] ?? null;
            if (! $matcher) {
                continue;
            }

            foreach ($base as $index => $row) {
                if ($matcher($row)) {
                    $base[$index] = [
                        ...$row,
                        'competitor' => $value,
                    ];
                }
            }
        }

        return $base;
    }
}

