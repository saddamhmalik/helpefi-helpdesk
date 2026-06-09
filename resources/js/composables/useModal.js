import { onUnmounted, watch } from 'vue';

export function useBodyScrollLock(openRef) {
    watch(openRef, (open) => {
        document.body.style.overflow = open ? 'hidden' : '';
    }, { immediate: true });

    onUnmounted(() => {
        document.body.style.overflow = '';
    });
}

export function useEscapeKey(openRef, onClose) {
    const handler = (event) => {
        if (event.key === 'Escape' && openRef.value) {
            onClose();
        }
    };

    watch(openRef, (open) => {
        if (open) {
            window.addEventListener('keydown', handler);
        } else {
            window.removeEventListener('keydown', handler);
        }
    }, { immediate: true });

    onUnmounted(() => {
        window.removeEventListener('keydown', handler);
    });
}
