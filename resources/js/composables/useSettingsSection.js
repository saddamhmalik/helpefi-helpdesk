import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';

const parseUrl = (url) => {
    const normalized = url.startsWith('http') ? url : `http://local${url.startsWith('/') ? url : `/${url}`}`;
    const parsed = new URL(normalized);

    return {
        path: parsed.pathname,
        section: parsed.searchParams.get('section'),
    };
};

export function useSettingsSection({ defaultSection, sections }) {
    const page = usePage();

    const activeSection = computed(() => {
        const { section } = parseUrl(page.url);

        if (section && sections.includes(section)) {
            return section;
        }

        return defaultSection;
    });

    return { activeSection };
}

export function isSettingsNavActive(itemHref, currentUrl) {
    const item = parseUrl(itemHref);
    const current = parseUrl(currentUrl);

    if (current.path !== item.path) {
        if (item.path === '/settings/members' && /^\/settings\/members\/\d+/.test(current.path)) {
            return !item.section || item.section === 'members';
        }

        if (item.path === '/settings' && (current.path === '/admin' || current.path === '/settings')) {
            return true;
        }

        return false;
    }

    const itemSection = item.section ?? defaultSectionForPath(item.path);
    const currentSection = current.section ?? defaultSectionForPath(current.path);

    return itemSection === currentSection;
}

function defaultSectionForPath(path) {
    return {
        '/settings/profile': 'profile',
        '/settings/members': 'members',
        '/settings/tickets': 'general',
        '/settings/email': 'incoming',
        '/settings/integrations': 'webhooks',
        '/settings/billing': 'usage',
        '/settings/security': 'overview',
    }[path] ?? null;
}

export function settingsNavHref(path, section, defaultSection) {
    if (!section || section === defaultSection) {
        return path;
    }

    return `${path}?section=${section}`;
}
