import { ref, watch } from 'vue';

const resolveDefaultOpen = (value, total) => {
    if (value === null || value === undefined) {
        return new Set();
    }

    if (Array.isArray(value)) {
        return new Set(value.filter((index) => Number.isInteger(index) && index >= 0 && index < total));
    }

    if (Number.isInteger(value) && value >= 0 && value < total) {
        return new Set([value]);
    }

    return new Set();
};

export function useFaqAccordion({ count, allowMultiple, defaultOpen }) {
    const openIndices = ref(new Set());
    const headerRefs = ref([]);

    watch([count, defaultOpen], () => {
        openIndices.value = resolveDefaultOpen(defaultOpen.value, count.value);
        headerRefs.value = [];
    }, { immediate: true });

    const isOpen = (index) => openIndices.value.has(index);

    const toggle = (index) => {
        const next = new Set(openIndices.value);

        if (next.has(index)) {
            next.delete(index);
        } else {
            if (!allowMultiple.value) {
                next.clear();
            }
            next.add(index);
        }

        openIndices.value = next;
    };

    const setHeaderRef = (index, element) => {
        if (element) {
            headerRefs.value[index] = element;
        }
    };

    const focusHeader = (index) => {
        const total = count.value;
        if (!total) {
            return;
        }

        const normalized = ((index % total) + total) % total;
        headerRefs.value[normalized]?.focus();
    };

    const onHeaderKeydown = (index, event) => {
        const total = count.value;
        if (!total) {
            return;
        }

        if (event.key === 'ArrowDown') {
            event.preventDefault();
            focusHeader(index + 1);
            return;
        }

        if (event.key === 'ArrowUp') {
            event.preventDefault();
            focusHeader(index - 1);
            return;
        }

        if (event.key === 'Home') {
            event.preventDefault();
            focusHeader(0);
            return;
        }

        if (event.key === 'End') {
            event.preventDefault();
            focusHeader(total - 1);
        }
    };

    return {
        isOpen,
        toggle,
        setHeaderRef,
        onHeaderKeydown,
    };
}
