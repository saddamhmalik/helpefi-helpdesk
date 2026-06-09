import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { useAgentNavigation } from './useAgentNavigation.js';

const truncate = (value, length = 48) => {
    if (!value) {
        return '';
    }

    return value.length > length ? `${value.slice(0, length - 1)}…` : value;
};

const segmentLabel = (segment) => segment
    .split('-')
    .map((part) => part.charAt(0).toUpperCase() + part.slice(1))
    .join(' ');

export function useAgentBreadcrumbs() {
    const page = usePage();
    const { flatNavItems, settingsLabelForHref, settingsNavGroups } = useAgentNavigation();

    const navLabelFor = (href) => flatNavItems.value.find((item) => item.href === href)?.label ?? null;

    const settingsLabelForPath = (path, url) => {
        for (const group of settingsNavGroups.value) {
            for (const item of group.items) {
                const itemPath = item.href.split('?')[0];

                if (item.href === url || (itemPath === path && item.href.includes('?'))) {
                    if (item.href === url) {
                        return item.label;
                    }
                }
            }
        }

        const fullMatch = settingsNavGroups.value
            .flatMap((group) => group.items)
            .find((item) => item.href === url);

        if (fullMatch) {
            return fullMatch.label;
        }

        const segment = path.replace('/settings/', '').split('/')[0];

        return segmentLabel(segment);
    };

    const crumbs = computed(() => {
        const url = page.url.split('#')[0];
        const path = url.split('?')[0];
        const props = page.props;

        if (path === '/dashboard') {
            return [{ label: 'Dashboard' }];
        }

        if (path === '/admin' || path === '/settings') {
            return [{ label: 'Settings', href: '/settings' }];
        }

        if (path === '/workspace' || path.startsWith('/workspace/tickets/')) {
            const ticket = props.selectedTicket;
            const items = [{ label: navLabelFor('/workspace') ?? 'Inbox', href: '/workspace' }];

            if (ticket) {
                items.push({ label: truncate(`${ticket.number} · ${ticket.subject}`) });
            }

            return items;
        }

        if (path === '/tickets') {
            return [{ label: navLabelFor('/tickets') ?? 'Tickets' }];
        }

        if (path === '/tickets/create') {
            return [
                { label: navLabelFor('/tickets') ?? 'Tickets', href: '/tickets' },
                { label: 'New ticket' },
            ];
        }

        if (/^\/tickets\/\d+$/.test(path) && props.ticket) {
            return [
                { label: navLabelFor('/tickets') ?? 'Tickets', href: '/tickets' },
                { label: truncate(`${props.ticket.number} · ${props.ticket.subject}`) },
            ];
        }

        if (path === '/contacts') {
            return [{ label: navLabelFor('/contacts') ?? 'Customers' }];
        }

        if (path === '/contacts/create') {
            return [
                { label: navLabelFor('/contacts') ?? 'Customers', href: '/contacts' },
                { label: 'New customer' },
            ];
        }

        if (/^\/contacts\/\d+$/.test(path) && props.contact) {
            return [
                { label: navLabelFor('/contacts') ?? 'Customers', href: '/contacts' },
                { label: props.contact.name },
            ];
        }

        if (path === '/organizations') {
            return [{ label: navLabelFor('/organizations') ?? 'Organizations' }];
        }

        if (path === '/organizations/create') {
            return [
                { label: navLabelFor('/organizations') ?? 'Organizations', href: '/organizations' },
                { label: 'New organization' },
            ];
        }

        if (/^\/organizations\/\d+$/.test(path) && props.organization) {
            return [
                { label: navLabelFor('/organizations') ?? 'Organizations', href: '/organizations' },
                { label: props.organization.name },
            ];
        }

        if (path === '/assets') {
            return [{ label: navLabelFor('/assets') ?? 'Assets' }];
        }

        if (path === '/assets/create') {
            return [
                { label: navLabelFor('/assets') ?? 'Assets', href: '/assets' },
                { label: 'New asset' },
            ];
        }

        if (/^\/assets\/\d+$/.test(path) && props.asset) {
            return [
                { label: navLabelFor('/assets') ?? 'Assets', href: '/assets' },
                { label: props.asset.name },
            ];
        }

        if (path === '/knowledge') {
            return [{ label: navLabelFor('/knowledge') ?? 'Knowledge base' }];
        }

        if (path === '/knowledge/create') {
            return [
                { label: navLabelFor('/knowledge') ?? 'Knowledge base', href: '/knowledge' },
                { label: 'New article' },
            ];
        }

        if (/^\/knowledge\/\d+$/.test(path) && props.article) {
            return [
                { label: navLabelFor('/knowledge') ?? 'Knowledge base', href: '/knowledge' },
                { label: truncate(props.article.title) },
            ];
        }

        if (path === '/reports') {
            return [{ label: navLabelFor('/reports') ?? 'Reports' }];
        }

        if (path === '/notifications') {
            return [{ label: 'Notifications' }];
        }

        if (path.startsWith('/settings')) {
            const root = { label: 'Settings', href: '/settings' };
            const pageLabel = settingsLabelForPath(path, url);

            if (path === '/settings/profile' && !url.includes('section=')) {
                return [root, { label: pageLabel }];
            }

            const segments = path.replace('/settings/', '').split('/').filter(Boolean);
            const section = segments[0];
            const items = [root, { label: pageLabel, href: `/settings/${section}` }];

            if (segments.length > 1 && section === 'members') {
                items.push({ label: props.member?.name ?? segmentLabel(segments[1]) });
            } else if (segments.length > 1 && section === 'performance') {
                items.push({ label: props.user?.name ?? 'Performance' });
            }

            return items;
        }

        const matchedNav = flatNavItems.value.find((item) => path === item.href || path.startsWith(`${item.href}/`));

        if (matchedNav) {
            return [{ label: matchedNav.label, href: matchedNav.href }];
        }

        const fallback = path.split('/').filter(Boolean).map((segment) => segmentLabel(segment));

        return fallback.length ? [{ label: fallback.join(' / ') }] : [{ label: 'Helpdesk' }];
    });

    return { crumbs };
}
