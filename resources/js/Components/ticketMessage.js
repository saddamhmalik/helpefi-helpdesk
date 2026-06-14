import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';

export function messageAuthor(message, t = null) {
    if (message.user?.name) {
        return message.user.name;
    }

    if (message.contact?.email) {
        return message.contact.name
            ? `${message.contact.name} <${message.contact.email}>`
            : message.contact.email;
    }

    return message.contact?.name || (t ? t('components.system') : 'System');
}

export function messageSide(message, currentUserId = null) {
    if (message.user_id) {
        return 'agent';
    }

    if (message.contact_id) {
        return 'requester';
    }

    return 'agent';
}

export function messageAvatar(message) {
    if (message.user?.name || message.user?.email) {
        return {
            name: message.user.name,
            email: message.user.email,
            imageUrl: message.user.avatar_url ?? null,
        };
    }

    return {
        name: message.contact?.name || '',
        email: message.contact?.email || '',
        imageUrl: null,
    };
}

export function avatarLabel(name, email = '') {
    const source = (name || email || '?').trim();

    if (!source) {
        return '?';
    }

    if (!name && email.includes('@')) {
        return email.charAt(0).toUpperCase();
    }

    const parts = source.split(/\s+/).filter(Boolean);

    if (parts.length === 1) {
        return parts[0].slice(0, 2).toUpperCase();
    }

    return `${parts[0].charAt(0)}${parts[parts.length - 1].charAt(0)}`.toUpperCase();
}

export function avatarColor(seed) {
    const palette = [
        '#2563eb',
        '#7c3aed',
        '#db2777',
        '#ea580c',
        '#059669',
        '#0891b2',
        '#4f46e5',
        '#be123c',
    ];

    let hash = 0;

    for (let index = 0; index < seed.length; index += 1) {
        hash = seed.charCodeAt(index) + ((hash << 5) - hash);
    }

    return palette[Math.abs(hash) % palette.length];
}

export function formatRelativeTime(value, locale = undefined) {
    const date = new Date(value);
    const diffMs = date.getTime() - Date.now();
    const absMs = Math.abs(diffMs);
    const rtf = new Intl.RelativeTimeFormat(locale, { numeric: 'auto' });

    if (absMs < 60_000) {
        return rtf.format(Math.round(diffMs / 1000), 'second');
    }

    if (absMs < 3_600_000) {
        return rtf.format(Math.round(diffMs / 60_000), 'minute');
    }

    if (absMs < 86_400_000) {
        return rtf.format(Math.round(diffMs / 3_600_000), 'hour');
    }

    if (absMs < 604_800_000) {
        return rtf.format(Math.round(diffMs / 86_400_000), 'day');
    }

    return date.toLocaleString(locale);
}

export function messagePlainText(body) {
    return (body ?? '')
        .replace(/<br\s*\/?>/gi, '\n')
        .replace(/<\/p>/gi, '\n\n')
        .replace(/<[^>]+>/g, '')
        .replace(/&nbsp;/g, ' ')
        .trim();
}

export function useTicketMessage() {
    const { t, locale } = useI18n();
    const page = usePage();
    const currentLocale = computed(() => page.props.locale ?? locale.value ?? 'en');

    return {
        messageAuthor: (message) => messageAuthor(message, t),
        formatRelativeTime: (value) => formatRelativeTime(value, currentLocale.value),
        messageSide,
        messageAvatar,
        messagePlainText,
        avatarLabel,
        avatarColor,
    };
}
