import { createI18n } from 'vue-i18n';
import { loadLocaleMessages } from '../locales/loader.js';

const messages = {
    en: loadLocaleMessages('en'),
    ar: loadLocaleMessages('ar'),
    es: loadLocaleMessages('es'),
    fr: loadLocaleMessages('fr'),
    de: loadLocaleMessages('de'),
};

let appI18n = null;

export function getAppI18n() {
    return appI18n;
}

export function createAppI18n(locale = 'en') {
    appI18n = createI18n({
        legacy: false,
        globalInjection: true,
        locale,
        fallbackLocale: 'en',
        messages,
    });

    return appI18n;
}

export function syncDocumentLocale(locale, direction = 'ltr') {
    document.documentElement.lang = locale;
    document.documentElement.dir = direction;
}
