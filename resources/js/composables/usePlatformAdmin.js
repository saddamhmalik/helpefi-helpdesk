import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';

export const adminInputClass = 'agent-input w-full rounded-xl px-3.5 py-2.5 text-sm';

export function usePlatformAdmin() {
    const { t } = useI18n();
    const page = usePage();

    const user = computed(() => page.props.platformAuth?.user ?? null);
    const permissions = computed(() => user.value?.permissions ?? page.props.platformAuth?.permissions ?? []);

    const can = (permission) => {
        if (!permission) {
            return true;
        }

        if (user.value?.roles?.includes('super_admin')) {
            return true;
        }

        return permissions.value.includes('*') || permissions.value.includes(permission);
    };

    const navGroups = computed(() => {
        const groups = [];

        groups.push({
            label: t('layouts.admin.nav_overview'),
            items: [
                { label: t('layouts.admin.nav_dashboard'), href: '/admin/dashboard', permission: null, match: (url) => url === '/admin/dashboard' || url === '/admin' },
            ],
        });

        const workspaceItems = [];

        if (can('tenants.view')) {
            workspaceItems.push({
                label: t('layouts.admin.nav_workspaces'),
                href: '/admin/tenants',
                permission: 'tenants.view',
                match: (url) => url.startsWith('/admin/tenants'),
            });
        }

        if (can('subscriptions.view')) {
            workspaceItems.push({
                label: t('layouts.admin.nav_subscriptions'),
                href: '/admin/subscriptions',
                permission: 'subscriptions.view',
                match: (url) => url.startsWith('/admin/subscriptions'),
            });
        }

        if (can('payments.view')) {
            workspaceItems.push({
                label: t('layouts.admin.nav_payments'),
                href: '/admin/payments',
                permission: 'payments.view',
                match: (url) => url.startsWith('/admin/payments'),
            });
        }

        if (workspaceItems.length) {
            groups.push({ label: t('layouts.admin.nav_workspaces_group'), items: workspaceItems });
        }

        const platformItems = [];

        if (can('settings.view')) {
            platformItems.push({
                label: t('layouts.admin.nav_platform_settings'),
                href: '/admin/settings',
                permission: 'settings.view',
                match: (url) => url.startsWith('/admin/settings'),
            });
        }

        if (can('emails.view')) {
            platformItems.push({
                label: t('layouts.admin.nav_email_templates'),
                href: '/admin/emails',
                permission: 'emails.view',
                match: (url) => url.startsWith('/admin/emails'),
            });
        }

        if (can('notices.view')) {
            platformItems.push({
                label: t('layouts.admin.nav_notices'),
                href: '/admin/notices',
                permission: 'notices.view',
                match: (url) => url.startsWith('/admin/notices'),
            });
        }

        if (can('backups.view')) {
            platformItems.push({
                label: t('layouts.admin.nav_backups'),
                href: '/admin/backups',
                permission: 'backups.view',
                match: (url) => url.startsWith('/admin/backups'),
            });
        }

        if (can('feedback.view')) {
            platformItems.push({
                label: t('layouts.admin.nav_feedback'),
                href: '/admin/feedback',
                permission: 'feedback.view',
                match: (url) => url.startsWith('/admin/feedback'),
            });
        }

        if (can('audit.view')) {
            platformItems.push({
                label: t('layouts.admin.nav_audit_logs'),
                href: '/admin/audit-logs',
                permission: 'audit.view',
                match: (url) => url.startsWith('/admin/audit-logs'),
            });
        }

        if (can('observability.view')) {
            platformItems.push({
                label: t('layouts.admin.nav_observability'),
                href: '/admin/observability',
                permission: 'observability.view',
                external: true,
                match: (url) => url.startsWith('/admin/observability') || url.startsWith('/telescope'),
            });
        }

        if (platformItems.length) {
            groups.push({ label: t('layouts.admin.nav_platform_group'), items: platformItems });
        }

        const accessItems = [];

        if (can('users.view')) {
            accessItems.push({
                label: t('layouts.admin.nav_users'),
                href: '/admin/users',
                permission: 'users.view',
                match: (url) => url.startsWith('/admin/users'),
            });
        }

        if (can('roles.view')) {
            accessItems.push({
                label: t('layouts.admin.nav_roles'),
                href: '/admin/roles',
                permission: 'roles.view',
                match: (url) => url.startsWith('/admin/roles'),
            });
        }

        if (accessItems.length) {
            groups.push({ label: t('layouts.admin.nav_access_group'), items: accessItems });
        }

        return groups;
    });

    const quickLinks = computed(() => navGroups.value.flatMap((group) => group.items));

    return {
        user,
        permissions,
        can,
        navGroups,
        quickLinks,
    };
}
