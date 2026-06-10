import { computed } from 'vue';

export function useSettingsNavFilter(settingsNavGroups, query) {
    const filteredGroups = computed(() => {
        const term = query.value.trim().toLowerCase();

        if (!term) {
            return settingsNavGroups.value;
        }

        return settingsNavGroups.value
            .map((group) => ({
                ...group,
                items: group.items.filter((item) => (
                    item.label.toLowerCase().includes(term)
                    || item.description?.toLowerCase().includes(term)
                    || group.label.toLowerCase().includes(term)
                )),
            }))
            .filter((group) => group.items.length > 0);
    });

    const resultCount = computed(() => filteredGroups.value.reduce(
        (count, group) => count + group.items.length,
        0,
    ));

    return { filteredGroups, resultCount };
}
