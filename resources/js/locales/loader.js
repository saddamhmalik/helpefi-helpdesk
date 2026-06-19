function deepMerge(target, source) {
    const result = { ...target };

    for (const key of Object.keys(source)) {
        if (
            source[key]
            && typeof source[key] === 'object'
            && !Array.isArray(source[key])
            && target[key]
            && typeof target[key] === 'object'
            && !Array.isArray(target[key])
        ) {
            result[key] = deepMerge(target[key], source[key]);
        } else {
            result[key] = source[key];
        }
    }

    return result;
}

const localeModules = import.meta.glob('./**/*.json');

async function loadLocaleFiles(locale) {
    let messages = {};

    const loaders = Object.entries(localeModules)
        .filter(([path]) => path.startsWith(`./${locale}/`))
        .map(([, loader]) => loader);

    const modules = await Promise.all(loaders.map((loader) => loader()));

    for (const module of modules) {
        messages = deepMerge(messages, module.default ?? module);
    }

    return messages;
}

export async function loadLocaleMessages(locale) {
    return loadLocaleFiles(locale);
}
