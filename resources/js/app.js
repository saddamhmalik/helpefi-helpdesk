import { createApp, h } from 'vue';
import { createInertiaApp } from '@inertiajs/vue3';
import AppToastContainer from './Components/AppToastContainer.vue';
import AppLocaleSync from './Components/AppLocaleSync.vue';
import SettingsLayout from './Layouts/SettingsLayout.vue';
import { registerCsrfHandling } from './plugins/csrf.js';
import { registerFlashToasts, showInitialFlashToasts } from './plugins/flashToasts.js';
import { createAppI18n, syncDocumentLocale } from './plugins/i18n.js';
import { syncCsrfMeta } from './support/csrf.js';

createInertiaApp({
    resolve: async (name) => {
        const pages = import.meta.glob('./Pages/**/*.vue');
        const page = await pages[`./Pages/${name}.vue`]();

        if (name.startsWith('Settings/') && !page.default.layout) {
            page.default.layout = (h, pageComponent) => h(SettingsLayout, () => pageComponent);
        }

        return page.default;
    },
    setup({ el, App, props, plugin }) {
        registerCsrfHandling();
        registerFlashToasts();
        syncCsrfMeta(props.initialPage.props.csrf_token);

        const initialLocale = props.initialPage.props.locale ?? 'en';
        const initialDirection = props.initialPage.props.direction ?? 'ltr';
        const i18n = createAppI18n(initialLocale);

        syncDocumentLocale(initialLocale, initialDirection);

        createApp({
            render: () => h('div', [
                h(App, props),
                h(AppLocaleSync),
                h(AppToastContainer),
            ]),
        })
            .use(plugin)
            .use(i18n)
            .mount(el);

        showInitialFlashToasts(props.initialPage.props);
    },
    progress: {
        color: '#2563eb',
        showDelay: 120,
        includeCSS: true,
    },
});
