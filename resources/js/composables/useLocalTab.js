import { onMounted, ref, watch } from 'vue';

export function useLocalTab(storageKey, defaultTab, tabs = []) {
    const activeTab = ref(defaultTab);

    onMounted(() => {
        const stored = sessionStorage.getItem(storageKey);

        if (stored && tabs.some((tab) => tab.id === stored)) {
            activeTab.value = stored;
        }
    });

    watch(activeTab, (value) => {
        sessionStorage.setItem(storageKey, value);
    });

    return activeTab;
}
