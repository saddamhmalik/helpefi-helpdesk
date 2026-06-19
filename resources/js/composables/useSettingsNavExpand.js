import { computed, onMounted, ref, watch } from 'vue';
import { isSettingsNavActive } from './useSettingsSection.js';

const STORAGE_KEY = 'settings-nav-expanded';

export function useSettingsNavExpand(groups, currentUrl, query) {
    const expanded = ref(new Set());

    const activeGroupId = computed(() => {
        for (const group of groups.value) {
            if (group.items.some((item) => isSettingsNavActive(item.href, currentUrl.value))) {
                return group.id;
            }
        }

        return null;
    });

    const loadStored = () => {
        try {
            const stored = JSON.parse(localStorage.getItem(STORAGE_KEY) || '[]');

            if (Array.isArray(stored)) {
                expanded.value = new Set(stored);
            }
        } catch {
            expanded.value = new Set();
        }
    };

    const persist = () => {
        localStorage.setItem(STORAGE_KEY, JSON.stringify([...expanded.value]));
    };

    const toggle = (groupId) => {
        const next = new Set(expanded.value);

        if (next.has(groupId)) {
            next.delete(groupId);
        } else {
            next.add(groupId);
        }

        expanded.value = next;
        persist();
    };

    const isExpanded = (groupId) => {
        if (query.value.trim()) {
            return true;
        }

        if (expanded.value.has(groupId)) {
            return true;
        }

        return activeGroupId.value === groupId;
    };

    watch(activeGroupId, (groupId) => {
        if (!groupId || query.value.trim()) {
            return;
        }

        const next = new Set(expanded.value);
        next.add(groupId);
        expanded.value = next;
    });

    onMounted(loadStored);

    return {
        activeGroupId,
        isExpanded,
        toggle,
    };
}
