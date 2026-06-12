import { createI18n } from 'vue-i18n';
import { loadLocaleMessages } from '../locales/loader.js';

const loadedLocales = new Set();

let appI18n = null;

export function getAppI18n() {
    return appI18n;
}

export async function ensureLocaleMessages(locale) {
    if (loadedLocales.has(locale) || !appI18n) {
        return;
    }

    const messages = await loadLocaleMessages(locale);

    appI18n.global.setLocaleMessage(locale, messages);
    loadedLocales.add(locale);
}

export async function createAppI18n(locale = 'en') {
    const messages = {};
    const localesToLoad = locale === 'en' ? ['en'] : ['en', locale];

    await Promise.all(localesToLoad.map(async (code) => {
        messages[code] = await loadLocaleMessages(code);
        loadedLocales.add(code);
    }));

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
