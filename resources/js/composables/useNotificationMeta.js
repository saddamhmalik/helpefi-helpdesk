const metaByType = {
    ticket_assigned: {
        labelKey: 'components.notification_types.ticket_assigned',
        badgeClass: 'bg-blue-100 text-blue-700',
        iconClass: 'text-blue-600',
        icon: 'assignment',
    },
    customer_reply: {
        labelKey: 'components.notification_types.customer_reply',
        badgeClass: 'bg-emerald-100 text-emerald-700',
        iconClass: 'text-emerald-600',
        icon: 'reply',
    },
    sla_breach: {
        labelKey: 'components.notification_types.sla_breach',
        badgeClass: 'bg-red-100 text-red-700',
        iconClass: 'text-red-600',
        icon: 'alert',
    },
    approval_pending: {
        labelKey: 'components.notification_types.approval_pending',
        badgeClass: 'bg-amber-100 text-amber-800',
        iconClass: 'text-amber-600',
        icon: 'approval',
    },
    approval_decided: {
        labelKey: 'components.notification_types.approval_decided',
        badgeClass: 'bg-violet-100 text-violet-700',
        iconClass: 'text-violet-600',
        icon: 'approval',
    },
    platform_notice: {
        labelKey: 'components.notification_types.platform_notice',
        badgeClass: 'bg-slate-100 text-slate-700',
        iconClass: 'text-slate-600',
        icon: 'notice',
    },
};

const fallbackMeta = {
    labelKey: 'components.notifications',
    badgeClass: 'bg-slate-100 text-slate-700',
    iconClass: 'text-slate-500',
    icon: 'bell',
};

export function notificationMeta(type) {
    return metaByType[type] ?? fallbackMeta;
}

export function notificationFilterTypes() {
    return Object.keys(metaByType);
}

export function formatRelativeTime(value, locale = undefined) {
    if (!value) {
        return '';
    }

    const date = new Date(value);
    const diffMs = date.getTime() - Date.now();
    const absMs = Math.abs(diffMs);
    const rtf = new Intl.RelativeTimeFormat(locale, { numeric: 'auto' });

    const units = [
        ['year', 1000 * 60 * 60 * 24 * 365],
        ['month', 1000 * 60 * 60 * 24 * 30],
        ['week', 1000 * 60 * 60 * 24 * 7],
        ['day', 1000 * 60 * 60 * 24],
        ['hour', 1000 * 60 * 60],
        ['minute', 1000 * 60],
        ['second', 1000],
    ];

    for (const [unit, size] of units) {
        if (absMs >= size || unit === 'second') {
            const amount = Math.round(diffMs / size);

            return rtf.format(amount, unit);
        }
    }

    return '';
}

export function formatUnreadCount(count) {
    if (count > 99) {
        return '99+';
    }

    return String(count);
}
