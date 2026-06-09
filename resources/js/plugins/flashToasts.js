import { router, usePage } from '@inertiajs/vue3';
import { collectErrorMessages, useToast } from '../composables/useToast.js';

const skipFlashKeys = new Set([
    'two_factor_setup',
    'recovery_codes',
    'webhook_secret',
]);

function showFromProps(props) {
    const toast = useToast();
    const flash = props?.flash ?? {};

    if (flash.success) {
        toast.success(flash.success);
    }

    if (flash.error) {
        toast.error(flash.error);
    }

    if (flash.invite_url) {
        toast.info('Invitation created. Copy the link shown on the page.');
    }

    collectErrorMessages(props?.errors).forEach((message) => {
        toast.error(message);
    });
}

export function registerFlashToasts() {
    router.on('success', (event) => {
        showFromProps(event.detail.page.props);
    });

    router.on('invalid', () => {
        setTimeout(() => {
            showFromProps(usePage().props);
        }, 0);
    });
}

export function showInitialFlashToasts(props) {
    showFromProps(props);
}
