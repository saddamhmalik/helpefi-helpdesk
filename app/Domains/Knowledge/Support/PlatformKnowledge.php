<?php

namespace App\Domains\Knowledge\Support;

final class PlatformKnowledge
{
    public const HANDBOOK_COLLECTION_SLUG = 'how-to-use-helpdesk';

    public const HANDBOOK_SECTIONS = [
        [
            'slug' => 'handbook-getting-started',
            'name' => 'Getting started',
            'sort_order' => 1,
        ],
        [
            'slug' => 'handbook-workspace-setup',
            'name' => 'Workspace setup',
            'sort_order' => 2,
        ],
        [
            'slug' => 'handbook-email-and-channels',
            'name' => 'Email and channels',
            'sort_order' => 3,
        ],
        [
            'slug' => 'handbook-daily-agent-work',
            'name' => 'Daily agent work',
            'sort_order' => 4,
        ],
        [
            'slug' => 'handbook-team-and-automation',
            'name' => 'Team and automation',
            'sort_order' => 5,
        ],
        [
            'slug' => 'handbook-customer-portal',
            'name' => 'Customer portal',
            'sort_order' => 6,
        ],
        [
            'slug' => 'handbook-go-live',
            'name' => 'Go live',
            'sort_order' => 7,
        ],
        [
            'slug' => 'handbook-troubleshooting',
            'name' => 'Help and troubleshooting',
            'sort_order' => 8,
        ],
    ];

    public const HANDBOOK_ARTICLE_SLUGS = [
        'handbook-start-here',
        'handbook-complete-setup-wizard',
        'handbook-timezone-and-business-hours',
        'handbook-personal-timezone',
        'handbook-brands-and-custom-domain',
        'handbook-connect-email-oauth',
        'handbook-connect-email-microsoft-oauth',
        'handbook-connect-email-imap',
        'handbook-email-forwarding-webhook',
        'handbook-outbound-email-smtp',
        'handbook-chat-widget',
        'handbook-invite-team',
        'handbook-roles-and-permissions',
        'handbook-auto-assignment',
        'handbook-sla-policies',
        'handbook-automation-rules',
        'handbook-macros-and-notifications',
        'handbook-first-ticket',
        'handbook-reply-and-internal-notes',
        'handbook-ticket-statuses-priorities',
        'handbook-workspace-inbox',
        'handbook-search-and-filter-tickets',
        'handbook-customers-and-organizations',
        'handbook-ai-copilot',
        'handbook-publish-help-center',
        'handbook-reports-and-csat',
        'handbook-billing-and-plans',
        'handbook-remove-demo-data',
        'handbook-troubleshooting-email',
        'handbook-troubleshooting-tickets-sla',
        'handbook-frequently-asked-questions',
    ];
}
