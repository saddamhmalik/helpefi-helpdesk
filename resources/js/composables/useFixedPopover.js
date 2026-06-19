import { nextTick, onMounted, onUnmounted, ref, watch } from 'vue';

export function useFixedPopover(openRef, anchorRef) {
    const panelStyle = ref({ visibility: 'hidden' });

    const updatePosition = () => {
        const anchor = anchorRef.value;

        if (!anchor) {
            return;
        }

        const rect = anchor.getBoundingClientRect();
        const panelWidth = Math.min(352, window.innerWidth - 16);
        const margin = 8;
        const isRtl = document.documentElement.dir === 'rtl';
        let left = isRtl ? rect.left : rect.right - panelWidth;

        left = Math.max(margin, Math.min(left, window.innerWidth - panelWidth - margin));

        const top = rect.bottom + margin;
        const maxHeight = window.innerHeight - top - margin;

        panelStyle.value = {
            position: 'fixed',
            top: `${top}px`,
            left: `${left}px`,
            width: `${panelWidth}px`,
            maxHeight: `${Math.max(160, maxHeight)}px`,
            overflowY: 'auto',
            zIndex: 100,
            visibility: 'visible',
        };
    };

    const onViewportChange = () => {
        if (openRef.value) {
            updatePosition();
        }
    };

    watch(openRef, async (open) => {
        if (!open) {
            panelStyle.value = { visibility: 'hidden' };

            return;
        }

        await nextTick();
        updatePosition();
    });

    onMounted(() => {
        window.addEventListener('resize', onViewportChange);
        window.addEventListener('scroll', onViewportChange, true);
    });

    onUnmounted(() => {
        window.removeEventListener('resize', onViewportChange);
        window.removeEventListener('scroll', onViewportChange, true);
    });

    return {
        panelStyle,
        updatePosition,
    };
}
