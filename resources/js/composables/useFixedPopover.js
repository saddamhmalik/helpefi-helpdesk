import { nextTick, onMounted, onUnmounted, ref, watch } from 'vue';

export function useFixedPopover(openRef, anchorRef, panelRef = null) {
    const panelStyle = ref({ visibility: 'hidden' });

    const updatePosition = () => {
        const anchor = anchorRef.value;

        if (!anchor) {
            return;
        }

        const rect = anchor.getBoundingClientRect();
        const panelWidth = Math.min(320, window.innerWidth - 16);
        const margin = 8;
        const isRtl = document.documentElement.dir === 'rtl';
        const panelHeight = panelRef?.value?.offsetHeight ?? 180;

        let left = isRtl ? rect.right - panelWidth : rect.left;
        left = Math.max(margin, Math.min(left, window.innerWidth - panelWidth - margin));

        const spaceBelow = window.innerHeight - rect.bottom - margin;
        const spaceAbove = rect.top - margin;
        const openAbove = spaceBelow < panelHeight && spaceAbove > spaceBelow;

        let top = openAbove ? rect.top - panelHeight - margin : rect.bottom + margin;
        top = Math.max(margin, Math.min(top, window.innerHeight - panelHeight - margin));

        const maxHeight = openAbove
            ? Math.max(120, rect.top - margin - margin)
            : Math.max(120, window.innerHeight - rect.bottom - margin - margin);

        panelStyle.value = {
            position: 'fixed',
            top: `${top}px`,
            left: `${left}px`,
            width: `${panelWidth}px`,
            maxHeight: `${maxHeight}px`,
            overflowY: 'auto',
            zIndex: 9999,
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
        await nextTick();
        updatePosition();
        requestAnimationFrame(updatePosition);
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
