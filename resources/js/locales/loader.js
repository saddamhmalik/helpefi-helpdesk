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

export function loadLocaleMessages(locale) {
    const modules = import.meta.glob('./**/*.json', { eager: true });
    let messages = {};

    for (const [path, module] of Object.entries(modules)) {
        if (!path.startsWith(`./${locale}/`)) {
            continue;
        }

        messages = deepMerge(messages, module.default ?? module);
    }

    return messages;
}
