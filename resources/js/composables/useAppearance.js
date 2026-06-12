import { router, usePage } from '@inertiajs/vue3';
import { onMounted, onUnmounted, watch } from 'vue';

export const APPEARANCE_MODES = ['light', 'dark', 'system'];

export function appearanceStorageKey(tenantId, userId) {
    if (tenantId && userId) {
        return `appearance:${tenantId}:${userId}`;
    }

    return 'appearance:guest';
}

export function readStoredAppearance(tenantId, userId) {
    try {
        return localStorage.getItem(appearanceStorageKey(tenantId, userId));
    } catch {
        return null;
    }
}

export function writeStoredAppearance(tenantId, userId, appearance) {
    try {
        localStorage.setItem(appearanceStorageKey(tenantId, userId), appearance);

        return true;
    } catch {
        return false;
    }
}

export function isDarkAppearance(appearance) {
    if (appearance === 'dark') {
        return true;
    }

    if (appearance === 'light') {
        return false;
    }

    return window.matchMedia('(prefers-color-scheme: dark)').matches;
}

export function applyAppearance(appearance) {
    const dark = isDarkAppearance(appearance);

    document.documentElement.classList.toggle('dark', dark);

    const meta = document.querySelector('meta[name="theme-color"]');

    if (meta) {
        meta.setAttribute('content', dark ? '#0f172a' : '#0066CC');
    }
}

export function useAppearance() {
    const page = usePage();
    let mediaQuery = null;

    const currentAppearance = () => {
        const fromPage = page.props.appearance ?? page.props.auth?.user?.appearance;

        if (fromPage) {
            return fromPage;
        }

        const stored = readStoredAppearance(page.props.tenantId, page.props.auth?.user?.id);

        return stored ?? 'system';
    };

    const syncAppearance = () => {
        const appearance = currentAppearance();

        applyAppearance(appearance);
        writeStoredAppearance(page.props.tenantId, page.props.auth?.user?.id, appearance);
    };

    const onSystemChange = () => {
        if (currentAppearance() === 'system') {
            applyAppearance('system');
        }
    };

    watch(
        () => [page.props.appearance, page.props.auth?.user?.appearance, page.props.tenantId, page.props.auth?.user?.id],
        syncAppearance,
        { immediate: true },
    );

    onMounted(() => {
        mediaQuery = window.matchMedia('(prefers-color-scheme: dark)');
        mediaQuery.addEventListener('change', onSystemChange);
    });

    onUnmounted(() => {
        mediaQuery?.removeEventListener('change', onSystemChange);
    });

    router.on('navigate', () => {
        syncAppearance();
    });
}
