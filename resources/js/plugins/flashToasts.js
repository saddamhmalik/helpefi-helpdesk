import { router, usePage } from '@inertiajs/vue3';
import { collectErrorMessages, useToast } from '../composables/useToast.js';
import { getAppI18n } from './i18n.js';

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
        const i18n = getAppI18n();
        toast.info(i18n.global.t('components.invitation_created_toast'));
    }

    collectErrorMessages(props?.errors).forEach((message) => {
        toast.error(message);
    });
}

function isPartialVisit(visit) {
    return Boolean(visit?.only?.length || visit?.except?.length);
}

export function registerFlashToasts() {
    router.on('success', (event) => {
        if (isPartialVisit(event.detail.visit)) {
            return;
        }

        showFromProps(event.detail.page.props);
    });

    router.on('httpException', (event) => {
        if (event.detail?.response?.status === 419) {
            return;
        }

        setTimeout(() => {
            showFromProps(usePage().props);
        }, 0);
    });
}

export function showInitialFlashToasts(props) {
    showFromProps(props);
}
