import { onMounted, onUnmounted } from 'vue';

const isEditableTarget = (target) => {
    if (!(target instanceof HTMLElement)) {
        return false;
    }

    const tag = target.tagName;

    if (tag === 'INPUT' || tag === 'TEXTAREA' || tag === 'SELECT') {
        return true;
    }

    return target.isContentEditable;
};

export function useWorkspaceQueueKeyboard({
    enabled,
    items,
    selectedId,
    onSelect,
}) {
    const handleKeydown = (event) => {
        if (!enabled.value || isEditableTarget(event.target)) {
            return;
        }

        if (event.metaKey || event.ctrlKey || event.altKey) {
            return;
        }

        const list = items.value ?? [];

        if (!list.length) {
            return;
        }

        const currentIndex = list.findIndex((item) => item.id === selectedId.value);

        if (event.key === 'j' || event.key === 'ArrowDown') {
            event.preventDefault();
            const nextIndex = currentIndex < list.length - 1 ? currentIndex + 1 : 0;
            onSelect(list[nextIndex].id);

            return;
        }

        if (event.key === 'k' || event.key === 'ArrowUp') {
            event.preventDefault();
            const previousIndex = currentIndex > 0 ? currentIndex - 1 : list.length - 1;
            onSelect(list[previousIndex].id);
        }
    };

    onMounted(() => {
        window.addEventListener('keydown', handleKeydown);
    });

    onUnmounted(() => {
        window.removeEventListener('keydown', handleKeydown);
    });
}
