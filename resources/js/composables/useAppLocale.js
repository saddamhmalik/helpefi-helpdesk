import { router, usePage } from '@inertiajs/vue3';
import { watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { syncDocumentLocale } from '../plugins/i18n.js';

export function useAppLocale() {
    const page = usePage();
    const { locale } = useI18n();

    const applyLocale = () => {
        const nextLocale = page.props.locale ?? 'en';
        const direction = page.props.direction ?? 'ltr';

        if (locale.value !== nextLocale) {
            locale.value = nextLocale;
        }

        syncDocumentLocale(nextLocale, direction);
    };

    watch(
        () => [page.props.locale, page.props.direction],
        applyLocale,
        { immediate: true },
    );

    router.on('navigate', () => {
        applyLocale();
    });
}
