import { reactive, watchEffect } from 'vue';

export const settingsLayoutMeta = reactive({
    title: '',
    description: '',
    headTitle: '',
    hidePageHeader: false,
});

export function useSettingsLayoutMeta(source) {
    watchEffect(() => {
        const config = typeof source === 'function' ? source() : source;

        settingsLayoutMeta.title = config?.title ?? '';
        settingsLayoutMeta.description = config?.description ?? '';
        settingsLayoutMeta.headTitle = config?.headTitle ?? '';
        settingsLayoutMeta.hidePageHeader = config?.hidePageHeader ?? false;
    });
}
