import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
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
    const { t } = useI18n();
    const page = usePage();
    const { flatNavItems, settingsNavGroups } = useAgentNavigation();

    const navLabelFor = (href) => flatNavItems.value.find((item) => item.href === href)?.label ?? null;

    const navOr = (href, key) => navLabelFor(href) ?? t(key);

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
            return [{ label: t('nav.dashboard') }];
        }

        if (path === '/admin' || path === '/settings') {
            return [{ label: t('common.settings'), href: '/settings' }];
        }

        if (path === '/workspace' || path.startsWith('/workspace/tickets/')) {
            const ticket = props.selectedTicket;
            const items = [{ label: navOr('/workspace', 'nav.inbox'), href: '/workspace' }];

            if (ticket) {
                items.push({ label: truncate(`${ticket.number} · ${ticket.subject}`) });
            }

            return items;
        }

        if (path === '/tickets') {
            return [{ label: navOr('/tickets', 'nav.tickets') }];
        }

        if (path === '/tickets/create') {
            return [
                { label: navOr('/tickets', 'nav.tickets'), href: '/tickets' },
                { label: t('common.new_ticket') },
            ];
        }

        if (/^\/tickets\/\d+$/.test(path) && props.ticket) {
            return [
                { label: navOr('/tickets', 'nav.tickets'), href: '/tickets' },
                { label: truncate(`${props.ticket.number} · ${props.ticket.subject}`) },
            ];
        }

        if (path === '/contacts') {
            return [{ label: navOr('/contacts', 'nav.customers') }];
        }

        if (path === '/contacts/create') {
            return [
                { label: navOr('/contacts', 'nav.customers'), href: '/contacts' },
                { label: t('components.new_customer') },
            ];
        }

        if (/^\/contacts\/\d+$/.test(path) && props.contact) {
            return [
                { label: navOr('/contacts', 'nav.customers'), href: '/contacts' },
                { label: props.contact.name },
            ];
        }

        if (path === '/organizations') {
            return [{ label: navOr('/organizations', 'nav.organizations') }];
        }

        if (path === '/organizations/create') {
            return [
                { label: navOr('/organizations', 'nav.organizations'), href: '/organizations' },
                { label: t('components.new_organization') },
            ];
        }

        if (/^\/organizations\/\d+$/.test(path) && props.organization) {
            return [
                { label: navOr('/organizations', 'nav.organizations'), href: '/organizations' },
                { label: props.organization.name },
            ];
        }

        if (path === '/assets') {
            return [{ label: navOr('/assets', 'nav.assets') }];
        }

        if (path === '/assets/types') {
            return [
                { label: navOr('/assets', 'nav.assets'), href: '/assets' },
                { label: t('components.assets_types') },
            ];
        }

        if (path === '/assets/discovery') {
            return [
                { label: navOr('/assets', 'nav.assets'), href: '/assets' },
                { label: t('components.assets_discovery') },
            ];
        }

        if (/^\/assets\/discovery\/scans\/\d+$/.test(path)) {
            return [
                { label: navOr('/assets', 'nav.assets'), href: '/assets' },
                { label: t('components.assets_discovery'), href: '/assets/discovery' },
                { label: t('components.scan_results') },
            ];
        }

        if (path === '/assets/create') {
            return [
                { label: navOr('/assets', 'nav.assets'), href: '/assets' },
                { label: t('components.new_asset') },
            ];
        }

        if (/^\/assets\/\d+$/.test(path) && props.asset) {
            return [
                { label: navOr('/assets', 'nav.assets'), href: '/assets' },
                { label: props.asset.name },
            ];
        }

        if (path === '/knowledge') {
            return [{ label: navOr('/knowledge', 'nav.knowledge_base') }];
        }

        if (path === '/knowledge/create') {
            return [
                { label: navOr('/knowledge', 'nav.knowledge_base'), href: '/knowledge' },
                { label: t('components.new_article') },
            ];
        }

        if (/^\/knowledge\/\d+$/.test(path) && props.article) {
            return [
                { label: navOr('/knowledge', 'nav.knowledge_base'), href: '/knowledge' },
                { label: truncate(props.article.title) },
            ];
        }

        if (path === '/reports') {
            return [{ label: navOr('/reports', 'nav.reports') }];
        }

        if (path === '/notifications') {
            return [{ label: t('common.notifications') }];
        }

        if (path.startsWith('/settings')) {
            const root = { label: t('common.settings'), href: '/settings' };
            const pageLabel = settingsLabelForPath(path, url);

            if (path === '/settings/profile' && !url.includes('section=')) {
                return [root, { label: pageLabel }];
            }

            const segments = path.replace('/settings/', '').split('/').filter(Boolean);
            const section = segments[0];
            const matchingNavItem = settingsNavGroups.value
                .flatMap((group) => group.items)
                .find((item) => item.href === url);
            const pageHref = matchingNavItem?.href ?? `/settings/${segments.join('/')}`;
            const items = [root, { label: pageLabel, href: pageHref }];

            if (segments.length > 1 && section === 'members') {
                items.push({ label: props.member?.name ?? segmentLabel(segments[1]) });
            } else if (segments.length > 1 && section === 'performance') {
                items.push({ label: props.user?.name ?? t('components.performance') });
            }

            return items;
        }

        const matchedNav = flatNavItems.value.find((item) => path === item.href || path.startsWith(`${item.href}/`));

        if (matchedNav) {
            return [{ label: matchedNav.label, href: matchedNav.href }];
        }

        const fallback = path.split('/').filter(Boolean).map((segment) => segmentLabel(segment));

        return fallback.length ? [{ label: fallback.join(' / ') }] : [{ label: t('app.name') }];
    });

    return { crumbs };
}
