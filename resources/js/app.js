import { createApp, h } from 'vue';
import { createInertiaApp, router } from '@inertiajs/vue3';
import AppToastContainer from './Components/AppToastContainer.vue';
import { registerFlashToasts, showInitialFlashToasts } from './plugins/flashToasts.js';

function csrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
}

router.on('before', (event) => {
    const token = csrfToken();

    if (! token || ! event.detail?.visit) {
        return;
    }

    event.detail.visit.headers = {
        ...(event.detail.visit.headers ?? {}),
        'X-CSRF-TOKEN': token,
        'X-Requested-With': 'XMLHttpRequest',
    };
});

createInertiaApp({
    resolve: async (name) => {
        const pages = import.meta.glob('./Pages/**/*.vue');
        const page = await pages[`./Pages/${name}.vue`]();

        return page.default;
    },
    setup({ el, App, props, plugin }) {
        registerFlashToasts();

        createApp({
            render: () => h('div', [
                h(App, props),
                h(AppToastContainer),
            ]),
        })
            .use(plugin)
            .mount(el);

        showInitialFlashToasts(props.initialPage.props);
    },
    progress: {
        color: '#2563eb',
    },
});
