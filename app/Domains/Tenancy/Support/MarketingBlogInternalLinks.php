<?php

namespace App\Domains\Tenancy\Support;

class MarketingBlogInternalLinks
{
    public static function forSlug(string $slug): array
    {
        return match ($slug) {
            'ai-helpdesk-software-guide' => [
                ['path' => '/shared-inbox', 'label' => 'Shared inbox'],
                ['path' => '/ai-agent', 'label' => 'AI Agent feature'],
                ['path' => '/sla-management', 'label' => 'SLA management'],
                ['path' => '/knowledge-base', 'label' => 'Knowledge base'],
                ['path' => '/pricing', 'label' => 'Pricing'],
                ['path' => '/compare/zendesk', 'label' => 'Helpefi vs Zendesk'],
                ['path' => '/compare/intercom', 'label' => 'Helpefi vs Intercom'],
                ['path' => '/compare/freshdesk', 'label' => 'Helpefi vs Freshdesk'],
            ],
            'bring-your-own-database-and-storage' => [
                ['path' => '/pricing', 'label' => 'Pricing — data residency add-on'],
                ['path' => '/features/data-residency', 'label' => 'Data residency feature'],
                ['path' => '/compare/zendesk', 'label' => 'Helpefi vs Zendesk'],
                ['path' => '/compare/freshdesk', 'label' => 'Helpefi vs Freshdesk'],
                ['path' => '/shared-inbox', 'label' => 'Shared inbox'],
                ['path' => '/sla-management', 'label' => 'SLA management'],
            ],
            'zendesk-pricing-alternatives-2026' => [
                ['path' => '/compare/zendesk', 'label' => 'Helpefi vs Zendesk'],
                ['path' => '/compare/freshdesk', 'label' => 'Helpefi vs Freshdesk'],
                ['path' => '/compare/intercom', 'label' => 'Helpefi vs Intercom'],
                ['path' => '/pricing', 'label' => 'Helpefi pricing'],
                ['path' => '/migrate/from-zendesk', 'label' => 'Migrate from Zendesk'],
                ['path' => '/ai-agent', 'label' => 'AI Agent features'],
                ['path' => '/shared-inbox', 'label' => 'Shared inbox'],
                ['path' => '/sla-management', 'label' => 'SLA management'],
            ],
            'freshdesk-vs-freshservice-do-you-need-both' => [
                ['path' => '/compare/freshdesk', 'label' => 'Helpefi vs Freshdesk'],
                ['path' => '/compare/freshservice', 'label' => 'Helpefi vs Freshservice'],
                ['path' => '/migrate/from-freshdesk', 'label' => 'Migrate from Freshdesk'],
                ['path' => '/migrate/from-freshservice', 'label' => 'Migrate from Freshservice'],
                ['path' => '/shared-inbox', 'label' => 'Shared inbox'],
                ['path' => '/sla-management', 'label' => 'SLA management'],
                ['path' => '/automation', 'label' => 'Automation'],
                ['path' => '/pricing', 'label' => 'Pricing'],
            ],
            'intercom-fin-pricing-explained' => [
                ['path' => '/compare/intercom', 'label' => 'Helpefi vs Intercom'],
                ['path' => '/compare/help-scout', 'label' => 'Helpefi vs Help Scout'],
                ['path' => '/migrate/from-intercom', 'label' => 'Migrate from Intercom'],
                ['path' => '/ai-agent', 'label' => 'AI Agent'],
                ['path' => '/live-chat', 'label' => 'Live chat'],
                ['path' => '/knowledge-base', 'label' => 'Knowledge base'],
                ['path' => '/pricing', 'label' => 'Pricing'],
            ],
            'helpdesk-for-shopify-stores' => [
                ['path' => '/shared-inbox', 'label' => 'Shared inbox'],
                ['path' => '/live-chat', 'label' => 'Live chat'],
                ['path' => '/knowledge-base', 'label' => 'Knowledge base'],
                ['path' => '/ai-agent', 'label' => 'AI Agent'],
                ['path' => '/sla-management', 'label' => 'SLA management'],
                ['path' => '/compare/help-scout', 'label' => 'Helpefi vs Help Scout'],
                ['path' => '/compare/front', 'label' => 'Helpefi vs Front'],
                ['path' => '/pricing', 'label' => 'Pricing'],
            ],

            // ── New comparison-topic supporting articles ──
            'freshservice-vs-helpefi' => [
                ['path' => '/compare/freshservice', 'label' => 'Helpefi vs Freshservice'],
                ['path' => '/compare/freshdesk', 'label' => 'Helpefi vs Freshdesk'],
                ['path' => '/migrate/from-freshservice', 'label' => 'Migrate from Freshservice'],
                ['path' => '/sla-management', 'label' => 'SLA management'],
                ['path' => '/shared-inbox', 'label' => 'Shared inbox'],
                ['path' => '/pricing', 'label' => 'Pricing'],
            ],
            'freshdesk-pricing-and-alternatives' => [
                ['path' => '/compare/freshdesk', 'label' => 'Helpefi vs Freshdesk'],
                ['path' => '/compare/freshservice', 'label' => 'Helpefi vs Freshservice'],
                ['path' => '/migrate/from-freshdesk', 'label' => 'Migrate from Freshdesk'],
                ['path' => '/shared-inbox', 'label' => 'Shared inbox'],
                ['path' => '/ai-agent', 'label' => 'AI Agent'],
                ['path' => '/sla-management', 'label' => 'SLA management'],
                ['path' => '/pricing', 'label' => 'Pricing'],
            ],
            'zoho-desk-pricing-guide' => [
                ['path' => '/compare/zoho-desk', 'label' => 'Helpefi vs Zoho Desk'],
                ['path' => '/shared-inbox', 'label' => 'Shared inbox'],
                ['path' => '/knowledge-base', 'label' => 'Knowledge base'],
                ['path' => '/customer-portal', 'label' => 'Customer portal'],
                ['path' => '/pricing', 'label' => 'Pricing'],
            ],
            'help-scout-vs-helpefi-guide' => [
                ['path' => '/compare/help-scout', 'label' => 'Helpefi vs Help Scout'],
                ['path' => '/migrate/from-help-scout', 'label' => 'Migrate from Help Scout'],
                ['path' => '/shared-inbox', 'label' => 'Shared inbox'],
                ['path' => '/knowledge-base', 'label' => 'Knowledge base'],
                ['path' => '/ai-agent', 'label' => 'AI Agent'],
                ['path' => '/sla-management', 'label' => 'SLA management'],
                ['path' => '/pricing', 'label' => 'Pricing'],
            ],
            'jira-service-management-alternatives' => [
                ['path' => '/compare/jira-service-management', 'label' => 'Helpefi vs Jira Service Management'],
                ['path' => '/compare/freshservice', 'label' => 'Helpefi vs Freshservice'],
                ['path' => '/sla-management', 'label' => 'SLA management'],
                ['path' => '/shared-inbox', 'label' => 'Shared inbox'],
                ['path' => '/pricing', 'label' => 'Pricing'],
            ],
            'intercom-alternative-for-support-teams' => [
                ['path' => '/compare/intercom', 'label' => 'Helpefi vs Intercom'],
                ['path' => '/migrate/from-intercom', 'label' => 'Migrate from Intercom'],
                ['path' => '/live-chat', 'label' => 'Live chat'],
                ['path' => '/ai-agent', 'label' => 'AI Agent'],
                ['path' => '/shared-inbox', 'label' => 'Shared inbox'],
                ['path' => '/sla-management', 'label' => 'SLA management'],
                ['path' => '/pricing', 'label' => 'Pricing'],
            ],

            // ── New feature-topic supporting articles ──
            'sla-management-best-practices' => [
                ['path' => '/sla-management', 'label' => 'SLA management feature'],
                ['path' => '/shared-inbox', 'label' => 'Shared inbox'],
                ['path' => '/automation', 'label' => 'Automation'],
                ['path' => '/analytics', 'label' => 'Analytics'],
                ['path' => '/ai-agent', 'label' => 'AI Agent'],
                ['path' => '/pricing', 'label' => 'Pricing'],
            ],
            'ai-helpdesk-adoption-guide' => [
                ['path' => '/ai-agent', 'label' => 'AI Agent feature'],
                ['path' => '/knowledge-base', 'label' => 'Knowledge base'],
                ['path' => '/sla-management', 'label' => 'SLA management'],
                ['path' => '/shared-inbox', 'label' => 'Shared inbox'],
                ['path' => '/compare/intercom', 'label' => 'Helpefi vs Intercom'],
                ['path' => '/pricing', 'label' => 'Pricing'],
            ],
            'customer-portal-best-practices' => [
                ['path' => '/customer-portal', 'label' => 'Customer portal feature'],
                ['path' => '/knowledge-base', 'label' => 'Knowledge base'],
                ['path' => '/live-chat', 'label' => 'Live chat'],
                ['path' => '/shared-inbox', 'label' => 'Shared inbox'],
                ['path' => '/ai-agent', 'label' => 'AI Agent'],
                ['path' => '/pricing', 'label' => 'Pricing'],
            ],
            'knowledge-base-strategy-guide' => [
                ['path' => '/knowledge-base', 'label' => 'Knowledge base feature'],
                ['path' => '/customer-portal', 'label' => 'Customer portal'],
                ['path' => '/ai-agent', 'label' => 'AI Agent'],
                ['path' => '/shared-inbox', 'label' => 'Shared inbox'],
                ['path' => '/compare/freshdesk', 'label' => 'Helpefi vs Freshdesk'],
                ['path' => '/pricing', 'label' => 'Pricing'],
            ],

            default => [],
        };
    }
}
