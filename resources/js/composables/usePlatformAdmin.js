import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';

export const adminInputClass = 'w-full rounded-xl border border-slate-200 px-3.5 py-2.5 text-sm text-slate-900 placeholder:text-slate-400 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20';

export function usePlatformAdmin() {
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
            label: 'Overview',
            items: [
                { label: 'Dashboard', href: '/admin/dashboard', permission: null, match: (url) => url === '/admin/dashboard' || url === '/admin' },
            ],
        });

        const workspaceItems = [];

        if (can('tenants.view')) {
            workspaceItems.push({
                label: 'Workspaces',
                href: '/admin/tenants',
                permission: 'tenants.view',
                match: (url) => url.startsWith('/admin/tenants'),
            });
        }

        if (can('subscriptions.view')) {
            workspaceItems.push({
                label: 'Subscriptions',
                href: '/admin/subscriptions',
                permission: 'subscriptions.view',
                match: (url) => url.startsWith('/admin/subscriptions'),
            });
        }

        if (can('payments.view')) {
            workspaceItems.push({
                label: 'Payments',
                href: '/admin/payments',
                permission: 'payments.view',
                match: (url) => url.startsWith('/admin/payments'),
            });
        }

        if (workspaceItems.length) {
            groups.push({ label: 'Workspaces', items: workspaceItems });
        }

        const platformItems = [];

        if (can('settings.view')) {
            platformItems.push({
                label: 'Platform settings',
                href: '/admin/settings',
                permission: 'settings.view',
                match: (url) => url.startsWith('/admin/settings'),
            });
        }

        if (can('emails.view')) {
            platformItems.push({
                label: 'Email templates',
                href: '/admin/emails',
                permission: 'emails.view',
                match: (url) => url.startsWith('/admin/emails'),
            });
        }

        if (can('backups.view')) {
            platformItems.push({
                label: 'Backups',
                href: '/admin/backups',
                permission: 'backups.view',
                match: (url) => url.startsWith('/admin/backups'),
            });
        }

        if (can('audit.view')) {
            platformItems.push({
                label: 'Audit logs',
                href: '/admin/audit-logs',
                permission: 'audit.view',
                match: (url) => url.startsWith('/admin/audit-logs'),
            });
        }

        if (platformItems.length) {
            groups.push({ label: 'Platform', items: platformItems });
        }

        const accessItems = [];

        if (can('users.view')) {
            accessItems.push({
                label: 'Users',
                href: '/admin/users',
                permission: 'users.view',
                match: (url) => url.startsWith('/admin/users'),
            });
        }

        if (can('roles.view')) {
            accessItems.push({
                label: 'Roles & permissions',
                href: '/admin/roles',
                permission: 'roles.view',
                match: (url) => url.startsWith('/admin/roles'),
            });
        }

        if (accessItems.length) {
            groups.push({ label: 'Access control', items: accessItems });
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
