import { router } from '@inertiajs/vue3';
import { nextTick, onMounted, onUnmounted, watch } from 'vue';

const STORAGE_KEY = 'settings-nav-scroll-top';

export function useSettingsNavScroll(navRef, currentUrl) {
    const save = () => {
        const element = navRef.value;

        if (!element) {
            return;
        }

        sessionStorage.setItem(STORAGE_KEY, String(element.scrollTop));
    };

    const restore = () => {
        const element = navRef.value;

        if (!element) {
            return;
        }

        const stored = sessionStorage.getItem(STORAGE_KEY);

        if (stored === null) {
            return;
        }

        element.scrollTop = Number(stored);
    };

    const restoreAfterRender = () => {
        nextTick(() => {
            restore();
            requestAnimationFrame(() => {
                restore();
            });
        });
    };

    let removeRouterHook = null;

    onMounted(() => {
        restoreAfterRender();

        const element = navRef.value;
        element?.addEventListener('scroll', save, { passive: true });

        removeRouterHook = router.on('success', (event) => {
            const nextPath = event.detail.page.url.split('?')[0];

            if (!nextPath.startsWith('/settings') && nextPath !== '/admin') {
                return;
            }

            restoreAfterRender();
        });
    });

    onUnmounted(() => {
        save();
        navRef.value?.removeEventListener('scroll', save);
        removeRouterHook?.();
    });

    watch(currentUrl, () => {
        restoreAfterRender();
    });
}
