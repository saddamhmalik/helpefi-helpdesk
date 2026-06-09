import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { settingsNavHref } from './useSettingsSection.js';

export function useAgentNavigation() {
    const page = usePage();
    const user = computed(() => page.props.auth.user);
    const isAdmin = computed(() => user.value?.is_admin);
    const billing = computed(() => page.props.billing);

    const hasPermission = (permission) => {
        if (isAdmin.value) {
            return true;
        }

        return user.value?.permissions?.includes(permission) ?? false;
    };

    const hasFeature = (feature) => billing.value?.features?.includes(feature) ?? true;

    const prepareSettingsItems = (items) => items
        .filter((item) => {
            if (item.admin && !isAdmin.value) {
                return false;
            }

            if (item.permission && !hasPermission(item.permission)) {
                return false;
            }

            if (item.feature && !hasFeature(item.feature) && !item.showWhenLocked) {
                return false;
            }

            if (item.requiresPaidPlan && billing.value?.on_trial) {
                return false;
            }

            return true;
        })
        .map((item) => ({
            ...item,
            locked: Boolean(item.feature && !hasFeature(item.feature)),
            lockedLabel: item.lockedLabel ?? 'Enterprise',
        }));

    const filterItems = (items) => items.filter((item) => {
        if (item.admin && !isAdmin.value) {
            return false;
        }

        if (item.permission && !hasPermission(item.permission)) {
            return false;
        }

        if (item.feature && !hasFeature(item.feature)) {
            return false;
        }

        if (item.requiresPaidPlan && billing.value?.on_trial) {
            return false;
        }

        return true;
    });

    const settingsNavGroups = computed(() => [
        {
            id: 'settings-workspace',
            label: 'Workspace',
            items: prepareSettingsItems([
                { label: 'Overview', href: '/settings', admin: true, description: 'Settings home and search' },
                { label: 'Custom domain', href: '/settings/custom-domain', admin: true, feature: 'custom_domain', showWhenLocked: true, description: 'Branded URL like support.yourcompany.com' },
                { label: 'Brands', href: '/settings/brands', admin: true, description: 'Customer portals and mailboxes' },
                { label: 'Usage & billing', href: settingsNavHref('/settings/billing', 'usage', 'usage'), admin: true, description: 'Plan usage and limits' },
                { label: 'Change plan', href: settingsNavHref('/settings/billing', 'plans', 'usage'), admin: true, description: 'Upgrade or downgrade' },
            ]),
        },
        {
            id: 'settings-personal',
            label: 'Personal',
            items: prepareSettingsItems([
                { label: 'Profile', href: '/settings/profile', description: 'Name and email' },
                { label: 'Password', href: settingsNavHref('/settings/profile', 'password', 'profile'), description: 'Sign-in password' },
                { label: 'Two-factor auth', href: settingsNavHref('/settings/profile', 'security', 'profile'), description: 'Authenticator app' },
                { label: 'Macros', href: '/settings/macros', description: 'Canned responses' },
                { label: 'Notifications', href: '/settings/notifications', admin: true, description: 'Agent alerts' },
            ]),
        },
        {
            id: 'settings-team',
            label: 'Team',
            items: prepareSettingsItems([
                { label: 'Agents', href: '/settings/members', admin: true, description: 'Team members and roles' },
                { label: 'Invitations', href: settingsNavHref('/settings/members', 'invitations', 'members'), admin: true, description: 'Pending invites' },
                { label: 'Teams & departments', href: '/settings/workforce', admin: true, description: 'Org structure' },
                { label: 'Skills', href: '/settings/skills', admin: true, description: 'Routing skills' },
                { label: 'Roles & permissions', href: '/settings/roles', admin: true, description: 'Access control' },
            ]),
        },
        {
            id: 'settings-tickets',
            label: 'Tickets & SLA',
            items: prepareSettingsItems([
                { label: 'SLA & business hours', href: '/settings/sla', admin: true, description: 'Targets, timezone, and hours' },
                { label: 'Auto-assignment', href: '/settings/assignment', admin: true, description: 'Routing rules' },
                { label: 'CSAT surveys', href: '/settings/csat', admin: true, description: 'Satisfaction ratings' },
                { label: 'Ticket numbering', href: settingsNavHref('/settings/tickets', 'general', 'general'), admin: true, description: 'Prefix and defaults' },
                { label: 'Email auto-reply', href: settingsNavHref('/settings/tickets', 'email', 'general'), admin: true, description: 'First response email' },
                { label: 'Customer fields', href: settingsNavHref('/settings/tickets', 'contact_fields', 'general'), admin: true, description: 'Contact custom fields' },
                { label: 'Ticket fields', href: settingsNavHref('/settings/tickets', 'ticket_fields', 'general'), admin: true, description: 'Ticket custom fields' },
                { label: 'Agent fields', href: settingsNavHref('/settings/tickets', 'user_fields', 'general'), admin: true, description: 'Team member fields' },
            ]),
        },
        {
            id: 'settings-channels',
            label: 'Channels',
            items: prepareSettingsItems([
                { label: 'Incoming email', href: settingsNavHref('/settings/email', 'incoming', 'incoming'), admin: true, feature: 'channels', description: 'Support mailboxes' },
                { label: 'Outgoing email', href: settingsNavHref('/settings/email', 'outgoing', 'incoming'), admin: true, feature: 'channels', description: 'SMTP and delivery' },
                { label: 'Advanced email policies', href: settingsNavHref('/settings/email', 'advanced', 'incoming'), admin: true, feature: 'channels', description: 'Threading and auto-reply' },
                { label: 'Ticket sources', href: '/settings/channels', admin: true, feature: 'channels', description: 'Web, email, chat' },
            ]),
        },
        {
            id: 'settings-workflow',
            label: 'Workflow',
            items: prepareSettingsItems([
                { label: 'Automation rules', href: '/settings/automation', admin: true, feature: 'automation', description: 'Triggers and actions' },
                { label: 'Webhooks', href: settingsNavHref('/settings/integrations', 'webhooks', 'webhooks'), admin: true, feature: 'integrations', description: 'Outbound events' },
                { label: 'Slack', href: settingsNavHref('/settings/integrations', 'slack', 'webhooks'), admin: true, feature: 'integrations', description: 'Channel notifications' },
                { label: 'Jira', href: settingsNavHref('/settings/integrations', 'jira', 'webhooks'), admin: true, feature: 'integrations', description: 'Issue sync' },
                { label: 'Linear', href: settingsNavHref('/settings/integrations', 'linear', 'webhooks'), admin: true, feature: 'integrations', description: 'Issue sync' },
            ]),
        },
        {
            id: 'settings-security',
            label: 'Security',
            items: prepareSettingsItems([
                { label: 'Security overview', href: settingsNavHref('/settings/security', 'overview', 'overview'), admin: true, description: 'MFA adoption' },
                { label: 'Security policy', href: settingsNavHref('/settings/security', 'policy', 'overview'), admin: true, description: 'MFA requirements' },
                { label: 'Data retention', href: settingsNavHref('/settings/security', 'audit', 'overview'), admin: true, description: 'Purge policies' },
                { label: 'Audit logs', href: '/settings/audit-logs', permission: 'audit.view', description: 'Activity history' },
            ]),
        },
        {
            id: 'settings-product',
            label: 'Product',
            items: prepareSettingsItems([
                { label: 'Plan features', href: settingsNavHref('/settings/billing', 'features', 'usage'), admin: true, description: 'Included capabilities' },
                { label: 'AI assistant', href: '/settings/ai', admin: true, feature: 'ai', description: 'Model and features' },
                { label: 'Service catalog', href: '/settings/service-catalog', admin: true, feature: 'service_catalog', description: 'Portal requests' },
            ]),
        },
    ].filter((group) => group.items.length > 0));

    const flatSettingsNavItems = computed(() => settingsNavGroups.value.flatMap((group) => group.items));

    const navSections = computed(() => {
        const sections = [
            {
                id: 'overview',
                label: 'Overview',
                items: filterItems([
                    { label: 'Dashboard', href: '/dashboard', icon: 'M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6', description: 'Metrics and overview' },
                ]),
            },
            {
                id: 'support',
                label: 'Support',
                items: filterItems([
                    { label: 'Inbox', href: '/workspace', icon: 'M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4', feature: 'workspace', description: 'Reply from the queue' },
                    { label: 'Tickets', href: '/tickets', icon: 'M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 110-4V7a2 2 0 00-2-2H5z', description: 'All support requests' },
                ]),
            },
            {
                id: 'directory',
                label: 'Directory',
                items: filterItems([
                    { label: 'Customers', href: '/contacts', icon: 'M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z', description: 'Customer profiles' },
                    { label: 'Organizations', href: '/organizations', icon: 'M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4', description: 'Companies and accounts' },
                ]),
            },
            {
                id: 'content',
                label: 'Content',
                items: filterItems([
                    { label: 'Knowledge base', href: '/knowledge', icon: 'M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253', description: 'Help articles' },
                ]),
            },
            {
                id: 'insights',
                label: 'Insights',
                items: filterItems([
                    { label: 'Reports', href: '/reports', icon: 'M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z', description: 'Analytics and exports' },
                ]),
            },
        ];

        const manageItems = filterItems([
            { label: 'Assets', href: '/assets', icon: 'M9 3v2m6-2v2M9 19v2m6-2v2M5 9H3m2 6H3m18-6h-2m2 6h-2M7 19h10a2 2 0 002-2V7a2 2 0 00-2-2H7a2 2 0 00-2 2v10a2 2 0 002 2zM9 9h6v6H9V9z', feature: 'assets', description: 'CMDB, discovery, and warranty' },
        ]);

        if (manageItems.length) {
            sections.push({
                id: 'manage',
                label: 'Manage',
                items: manageItems,
            });
        }

        return sections.filter((section) => section.items?.length > 0);
    });

    const flatNavItems = computed(() => navSections.value.flatMap((section) => section.items ?? []));

    const mainNav = computed(() => flatNavItems.value);

    const settingsHref = computed(() => {
        if (isAdmin.value) {
            return '/settings';
        }

        return '/settings/profile';
    });

    const homeHref = computed(() => '/dashboard');

    const settingsLabelForHref = (href) => {
        for (const group of settingsNavGroups.value) {
            const match = group.items.find((item) => item.href === href);

            if (match) {
                return match.label;
            }
        }

        return null;
    };

    return {
        user,
        isAdmin,
        hasFeature,
        settingsNavGroups,
        flatSettingsNavItems,
        navSections,
        flatNavItems,
        mainNav,
        homeHref,
        settingsHref,
        settingsLabelForHref,
    };
}
