import { createI18n } from 'vue-i18n';
import central from '../locales/en/central.json';
import layouts from '../locales/en/layouts.json';
import errors from '../locales/en/errors.json';
import auth from '../locales/en/auth.json';
import components from '../locales/en/components.json';

export async function createMarketingI18n(locale = 'en') {
    const messages = {
        en: {
            central,
            layouts,
            errors,
            auth,
            components,
            common: {
                title: 'Title',
                edit: 'Edit',
                cancel: 'Cancel',
            },
            profile: {
                email: 'Email',
            },
            settings: {
                password: 'Password',
            },
        },
    };

    return createI18n({
        legacy: false,
        globalInjection: true,
        locale: locale === 'en' ? 'en' : locale,
        fallbackLocale: 'en',
        messages,
    });
}
