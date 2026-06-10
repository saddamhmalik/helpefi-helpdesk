import { reactive, watchEffect } from 'vue';

export const settingsLayoutMeta = reactive({
    title: '',
    description: '',
    headTitle: '',
});

export function useSettingsLayoutMeta(source) {
    watchEffect(() => {
        const config = typeof source === 'function' ? source() : source;

        settingsLayoutMeta.title = config?.title ?? '';
        settingsLayoutMeta.description = config?.description ?? '';
        settingsLayoutMeta.headTitle = config?.headTitle ?? '';
    });
}
