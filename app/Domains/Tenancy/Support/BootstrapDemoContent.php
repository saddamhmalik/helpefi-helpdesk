<?php

namespace App\Domains\Tenancy\Support;

final class BootstrapDemoContent
{
    public const DEMO_TICKET_NUMBERS = ['HD-00001'];

    public const DEMO_INBOX_ADDRESS = 'support@helpdesk.test';

    public const DEMO_ORGANIZATION_NAMES = ['Acme Inc'];

    public const DEMO_ORGANIZATION_DOMAINS = ['example.com'];

    public const DEMO_CONTACT_EMAILS = ['customer@example.com'];

    public const DEMO_USER_EMAILS = ['customer@example.com'];

    public const DEMO_TAG_SLUGS = ['vip', 'enterprise', 'trial'];

    public const DEMO_ASSET_TAGS = ['AST-00001', 'AST-00002', 'AST-00003'];

    public const DEMO_SERVICE_CATEGORY_SLUGS = ['it-support', 'hr-services'];

    public const DEMO_KNOWLEDGE_CATEGORY_SLUGS = [
        'product-documentation',
        'agent-training',
        'customer-help',
    ];

    public const DEMO_KNOWLEDGE_COLLECTION_SLUGS = [
        'product-guide',
        'agent-handbook',
        'customer-self-service',
    ];

    public const DEMO_KNOWLEDGE_ARTICLE_SLUGS = [
        'helpdesk-platform-overview',
        'getting-started-for-agents',
        'tickets-workspace-and-sla',
        'contacts-and-knowledge-base',
        'email-channels-and-automation',
        'ai-service-catalog-and-assets',
        'reports-notifications-and-csat',
        'administration-guide',
        'rest-api-overview',
        'how-to-submit-a-support-request',
        'how-to-track-your-ticket',
        'customer-portal-account',
        'welcome-to-helpdesk',
    ];
}
