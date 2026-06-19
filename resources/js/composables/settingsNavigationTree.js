export function sectionEntry(key, path, section, defaultSection, options = {}) {
    return {
        key,
        path,
        section,
        defaultSection,
        ...options,
    };
}

export const SETTINGS_NAV_TREE = [
    {
        id: 'settings-workspace',
        labelKey: 'settings.groups.workspace',
        items: [
            { key: 'overview', href: '/settings', admin: true },
            { key: 'custom_domain', href: '/settings/custom-domain', admin: true, feature: 'custom_domain', showWhenLocked: true },
            { key: 'brands', href: '/settings/brands', admin: true },
            sectionEntry('usage_billing', '/settings/billing', 'usage', 'usage', { admin: true }),
            sectionEntry('change_plan', '/settings/billing', 'plans', 'usage', { admin: true }),
            sectionEntry('addons', '/settings/billing', 'addons', 'usage', { admin: true }),
            sectionEntry('payment_history', '/settings/billing', 'payments', 'usage', { admin: true }),
            sectionEntry('plan_features', '/settings/billing', 'features', 'usage', { admin: true }),
            { key: 'platform_feedback', href: '/settings/platform-feedback', admin: true },
        ],
    },
    {
        id: 'settings-personal',
        labelKey: 'settings.groups.personal',
        items: [
            sectionEntry('profile', '/settings/profile', 'profile', 'profile'),
            sectionEntry('password', '/settings/profile', 'password', 'profile'),
            sectionEntry('two_factor', '/settings/profile', 'security', 'profile'),
            { key: 'notifications', href: '/settings/notifications', admin: true },
        ],
    },
    {
        id: 'settings-customers',
        labelKey: 'settings.groups.customers',
        items: [
            sectionEntry('customer_fields', '/settings/tickets', 'contact_fields', 'general', { admin: true }),
        ],
    },
    {
        id: 'settings-tickets',
        labelKey: 'settings.groups.tickets',
        items: [
            sectionEntry('ticket_numbering', '/settings/tickets', 'general', 'general', { admin: true }),
            { key: 'ticket_statuses', href: '/settings/ticket-statuses', admin: true },
            { key: 'sla_business_hours', href: '/settings/sla', admin: true },
            { key: 'auto_assignment', href: '/settings/assignment', admin: true },
            { key: 'csat_surveys', href: '/settings/csat', admin: true },
            { key: 'macros', href: '/settings/macros' },
            { key: 'automation_rules', href: '/settings/automation', admin: true, feature: 'automation' },
            sectionEntry('ticket_fields', '/settings/tickets', 'ticket_fields', 'general', { admin: true }),
            sectionEntry('external_issues', '/settings/tickets', 'external_issues', 'general', { admin: true }),
            sectionEntry('jira', '/settings/integrations', 'jira', 'webhooks', { admin: true, feature: 'integrations' }),
            sectionEntry('linear', '/settings/integrations', 'linear', 'webhooks', { admin: true, feature: 'integrations' }),
        ],
    },
    {
        id: 'settings-channels',
        labelKey: 'settings.groups.channels',
        items: [
            sectionEntry('incoming_email', '/settings/email', 'incoming', 'incoming', { admin: true, feature: 'channels' }),
            sectionEntry('outgoing_email', '/settings/email', 'outgoing', 'incoming', { admin: true, feature: 'channels' }),
            sectionEntry('advanced_email', '/settings/email', 'advanced', 'incoming', { admin: true, feature: 'channels' }),
            sectionEntry('email_auto_reply', '/settings/tickets', 'email', 'general', { admin: true, feature: 'channels' }),
            { key: 'email_templates', href: '/settings/email-templates', admin: true, feature: 'channels' },
            { key: 'ticket_sources', href: '/settings/channels', admin: true, feature: 'channels' },
            { key: 'whatsapp_sms', href: '/settings/messaging', admin: true, feature: 'channels' },
        ],
    },
    {
        id: 'settings-integrations',
        labelKey: 'settings.groups.integrations',
        items: [
            sectionEntry('webhooks', '/settings/integrations', 'webhooks', 'webhooks', { admin: true, feature: 'integrations' }),
            sectionEntry('slack', '/settings/integrations', 'slack', 'webhooks', { admin: true, feature: 'integrations' }),
            sectionEntry('shopify', '/settings/integrations', 'shopify', 'webhooks', { admin: true, feature: 'integrations' }),
            sectionEntry('hubspot', '/settings/integrations', 'hubspot', 'webhooks', { admin: true, feature: 'integrations' }),
            sectionEntry('salesforce', '/settings/integrations', 'salesforce', 'webhooks', { admin: true, feature: 'integrations' }),
            sectionEntry('microsoft_teams', '/settings/integrations', 'teams', 'webhooks', { admin: true, feature: 'integrations' }),
            sectionEntry('zapier', '/settings/integrations', 'zapier', 'webhooks', { admin: true, feature: 'integrations' }),
        ],
    },
    {
        id: 'settings-team',
        labelKey: 'settings.groups.team',
        items: [
            sectionEntry('agents', '/settings/members', 'members', 'members', { admin: true }),
            sectionEntry('invitations', '/settings/members', 'invitations', 'members', { admin: true }),
            { key: 'teams_departments', href: '/settings/workforce', admin: true },
            { key: 'skills', href: '/settings/skills', admin: true },
            { key: 'roles_permissions', href: '/settings/roles', admin: true },
            sectionEntry('agent_fields', '/settings/tickets', 'user_fields', 'general', { admin: true }),
        ],
    },
    {
        id: 'settings-security',
        labelKey: 'settings.groups.security',
        items: [
            sectionEntry('security_overview', '/settings/security', 'overview', 'overview', { admin: true }),
            sectionEntry('security_policy', '/settings/security', 'policy', 'overview', { admin: true }),
            sectionEntry('single_sign_on', '/settings/security', 'sso', 'overview', { admin: true }),
            sectionEntry('data_retention', '/settings/security', 'audit', 'overview', { admin: true }),
            { key: 'audit_logs', href: '/settings/audit-logs', permission: 'audit.view' },
        ],
    },
    {
        id: 'settings-product',
        labelKey: 'settings.groups.product',
        items: [
            { key: 'ai_assistant', href: '/settings/ai', admin: true, feature: 'ai' },
            { key: 'service_catalog', href: '/settings/service-catalog', admin: true, feature: 'service_catalog' },
            { key: 'service_desk', href: '/service-desk', admin: true, feature: 'service_desk', showWhenLocked: true },
            { key: 'change_approvals', href: '/settings/service-desk/approvals', admin: true, feature: 'service_desk' },
            { key: 'api_docs', href: '/api/docs', admin: true },
        ],
    },
];
