import { createApp, h } from 'vue';
import { createInertiaApp } from '@inertiajs/vue3';
import AppToastContainer from './Components/AppToastContainer.vue';
import AppLocaleSync from './Components/AppLocaleSync.vue';
import { registerCsrfHandling } from './plugins/csrf.js';
import { registerFlashToasts, showInitialFlashToasts } from './plugins/flashToasts.js';
import { createMarketingI18n } from './plugins/marketingI18n.js';
import { syncDocumentLocale } from './plugins/i18n.js';
import { syncCsrfMeta } from './support/csrf.js';

const pages = import.meta.glob([
    './Pages/Central/Home.vue',
    './Pages/Central/Login.vue',
    './Pages/Central/Register.vue',
    './Pages/Central/Contact.vue',
    './Pages/Central/FeaturesIndex.vue',
    './Pages/Central/CompareIndex.vue',
    './Pages/Central/MigrateIndex.vue',
    './Pages/Central/IntegrationsIndex.vue',
    './Pages/Central/FeatureLanding.vue',
    './Pages/Central/IntegrationLanding.vue',
    './Pages/Central/VerticalLanding.vue',
    './Pages/Central/CompetitorComparison.vue',
    './Pages/Central/CompareLanding.vue',
    './Pages/Central/MigrateLanding.vue',
    './Pages/Central/MarketingStaticPage.vue',
    './Pages/Central/Blog/*.vue',
    './Pages/Error/*.vue',
]);

createInertiaApp({
    title: (title) => {
        const brand = 'Helpefi';
        const raw = String(title ?? '').trim();
        if (!raw) return brand;
        if (raw.toLowerCase() === brand.toLowerCase()) return brand;
        if (raw.toLowerCase().endsWith(`| ${brand.toLowerCase()}`)) return raw;
        try {
            if (window.location.pathname === '/' || window.location.pathname === '') return raw;
        } catch {}
        return `${raw} | ${brand}`;
    },
    resolve: async (name) => {
        const loader = pages[`./Pages/${name}.vue`];

        if (! loader) {
            throw new Error(`Marketing page not found: ${name}`);
        }

        const page = await loader();

        return page.default;
    },
    setup: async ({ el, App, props, plugin }) => {
        registerCsrfHandling();
        registerFlashToasts();
        syncCsrfMeta(props.initialPage.props.csrf_token);

        const initialLocale = props.initialPage.props.locale ?? 'en';
        const initialDirection = props.initialPage.props.direction ?? 'ltr';
        const i18n = await createMarketingI18n(initialLocale);

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

        const shell = document.getElementById('marketing-first-paint');
        if (shell && shell.dataset.swapHero !== 'true') {
            document.body.classList.remove('marketing-fp-pending');
            shell.remove();
        }
    },
    progress: {
        color: '#2563eb',
        showDelay: 200,
        includeCSS: false,
    },
});
