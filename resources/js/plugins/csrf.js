import { router } from '@inertiajs/vue3';
import { isCsrfExpiredResponse, reloadOnCsrfExpiry, syncCsrfMeta } from '../support/csrf.js';

export function registerCsrfHandling() {
    router.on('success', (event) => {
        const token = event.detail.page?.props?.csrf_token;

        if (token) {
            syncCsrfMeta(token);
        }
    });

    router.on('httpException', (event) => {
        if (isCsrfExpiredResponse(event.detail?.response)) {
            event.preventDefault();
            reloadOnCsrfExpiry();
        }
    });
}
