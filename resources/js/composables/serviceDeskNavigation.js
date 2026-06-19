export const SERVICE_DESK_NAV_ITEMS = [
    {
        key: 'service_desk_overview',
        href: '/service-desk',
        exact: true,
        permission: 'service-desk.view',
        icon: 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z',
    },
    {
        key: 'service_desk_approvals',
        href: '/service-desk/approvals',
        permission: 'service-desk.approve',
        icon: 'M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z',
    },
    {
        key: 'service_desk_calendar',
        href: '/service-desk/changes/calendar',
        permission: 'service-desk.view',
        icon: 'M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z',
    },
    {
        key: 'service_desk_major_incidents',
        href: '/service-desk/major-incidents',
        permission: 'service-desk.manage',
        icon: 'M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z',
    },
    {
        key: 'service_desk_incidents',
        href: '/service-desk/queues/incident',
        queueType: 'incident',
        permission: 'service-desk.view',
        icon: 'M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z',
    },
    {
        key: 'service_desk_requests',
        href: '/service-desk/queues/service_request',
        queueType: 'service_request',
        permission: 'service-desk.view',
        icon: 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2',
    },
    {
        key: 'service_desk_changes',
        href: '/service-desk/queues/change',
        queueType: 'change',
        permission: 'service-desk.view',
        icon: 'M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15',
    },
    {
        key: 'service_desk_problems',
        href: '/service-desk/queues/problem',
        queueType: 'problem',
        permission: 'service-desk.view',
        icon: 'M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z',
    },
];

export function prepareServiceDeskNavItems({ t, te, hasPermission, hasFeature }) {
    if (!hasFeature('service_desk')) {
        return [];
    }

    return SERVICE_DESK_NAV_ITEMS
        .filter((item) => !item.permission || hasPermission(item.permission))
        .map((item) => {
            const componentKey = `components.${item.key}`;
            const navKey = `nav.${item.key}`;
            const descriptionKey = `nav.descriptions.${item.key}`;

            return {
                ...item,
                label: te?.(componentKey) ? t(componentKey) : t(navKey),
                description: te?.(descriptionKey) ? t(descriptionKey) : '',
            };
        });
}

export function isServiceDeskNavActive(href, url, exact = false) {
    const path = url.split('?')[0];

    if (exact) {
        return path === href;
    }

    return path === href || path.startsWith(`${href}/`);
}
