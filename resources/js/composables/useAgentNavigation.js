import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import { settingsNavHref, isSettingsNavActive } from './useSettingsSection.js';
import { settingsNavIcon } from './settingsNavIcons.js';
import { SETTINGS_NAV_TREE } from './settingsNavigationTree.js';
import { prepareServiceDeskNavItems } from './serviceDeskNavigation.js';

export function useAgentNavigation() {
    const { t, te } = useI18n();
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

    const resolveItemHref = (item) => {
        if (item.href) {
            return item.href;
        }

        return settingsNavHref(item.path, item.section, item.defaultSection);
    };

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
        .map((item) => {
            const href = resolveItemHref(item);

            return {
                ...item,
                href,
                label: t(`settings.${item.key}`),
                description: t(`settings.descriptions.${item.key}`),
                icon: settingsNavIcon(href),
                locked: Boolean(item.feature && !hasFeature(item.feature)),
                lockedLabel: item.lockedLabel ?? t('common.enterprise'),
            };
        });

    const settingsNavGroups = computed(() => SETTINGS_NAV_TREE
        .map((group) => ({
            id: group.id,
            label: t(group.labelKey),
            items: prepareSettingsItems(group.items),
        }))
        .filter((group) => group.items.length > 0));

    const flatSettingsNavItems = computed(() => settingsNavGroups.value.flatMap((group) => group.items));

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

    const navSections = computed(() => {
        const sections = [
            {
                id: 'overview',
                label: t('nav.sections.overview'),
                items: filterItems([
                    { label: t('nav.dashboard'), href: '/dashboard', icon: 'M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6', description: t('nav.descriptions.dashboard') },
                ]),
            },
            {
                id: 'support',
                label: t('nav.sections.support'),
                items: filterItems([
                    { label: t('nav.inbox'), href: '/workspace', icon: 'M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4', feature: 'workspace', description: t('nav.descriptions.inbox') },
                    { label: t('nav.tickets'), href: '/tickets', icon: 'M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 110-4V7a2 2 0 00-2-2H5z', description: t('nav.descriptions.tickets') },
                ]),
            },
            {
                id: 'directory',
                label: t('nav.sections.directory'),
                items: filterItems([
                    { label: t('nav.customers'), href: '/contacts', icon: 'M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z', description: t('nav.descriptions.customers') },
                    { label: t('nav.agents'), href: '/settings/members', admin: true, icon: 'M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z', description: t('nav.descriptions.agents') },
                    { label: t('nav.organizations'), href: '/organizations', icon: 'M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4', description: t('nav.descriptions.organizations') },
                    { label: t('settings.teams_departments'), href: '/settings/workforce', admin: true, icon: 'M4 5a1 1 0 011-1h14a1 1 0 011 1v2a1 1 0 01-1 1H5a1 1 0 01-1-1V5zM4 13a1 1 0 011-1h6a1 1 0 011 1v6a1 1 0 01-1 1H5a1 1 0 01-1-1v-6zM16 13a1 1 0 011-1h2a1 1 0 011 1v6a1 1 0 01-1 1h-2a1 1 0 01-1-1v-6z', description: t('nav.descriptions.departments') },
                ]),
            },
            {
                id: 'insights',
                label: t('nav.sections.insights'),
                items: filterItems([
                    { label: t('nav.reports'), href: '/reports', icon: 'M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z', description: t('nav.descriptions.reports') },
                    { label: t('nav.growth_hub'), href: '/growth', admin: true, icon: 'M13 7h8m0 0v8m0-8l-8 8-4-4-6 6', description: t('nav.descriptions.growth_hub') },
                ]),
            },
        ];

        const serviceDeskItems = prepareServiceDeskNavItems({
            t,
            te,
            hasPermission,
            hasFeature,
        });

        if (serviceDeskItems.length) {
            sections.push({
                id: 'service_desk',
                label: t('nav.sections.service_desk'),
                items: serviceDeskItems,
            });
        }

        const manageItems = filterItems([
            { label: t('nav.assets'), href: '/assets', icon: 'M9 3v2m6-2v2M9 19v2m6-2v2M5 9H3m2 6H3m18-6h-2m2 6h-2M7 19h10a2 2 0 002-2V7a2 2 0 00-2-2H7a2 2 0 00-2 2v10a2 2 0 002 2zM9 9h6v6H9V9z', feature: 'assets', description: t('nav.descriptions.assets') },
        ]);

        if (manageItems.length) {
            sections.push({
                id: 'manage',
                label: t('nav.sections.manage'),
                items: manageItems,
            });
        }

        const contentItems = filterItems([
            { label: t('nav.how_to_use'), href: '/how-to', icon: 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4', description: t('nav.descriptions.how_to_use') },
            { label: t('nav.knowledge_base'), href: '/knowledge', icon: 'M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253', description: t('nav.descriptions.knowledge_base') },
        ]);

        if (contentItems.length) {
            sections.push({
                id: 'content',
                label: t('nav.sections.content'),
                items: contentItems,
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

    const mobileNav = computed(() => [
        ...flatNavItems.value,
        {
            label: t('common.settings'),
            href: settingsHref.value,
        },
    ]);

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

    const settingsBreadcrumbForUrl = (url) => {
        for (const group of settingsNavGroups.value) {
            const item = group.items.find((entry) => isSettingsNavActive(entry.href, url));

            if (item) {
                return {
                    group: group.label,
                    page: item.label,
                };
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
        mobileNav,
        homeHref,
        settingsHref,
        settingsLabelForHref,
        settingsBreadcrumbForUrl,
    };
}
