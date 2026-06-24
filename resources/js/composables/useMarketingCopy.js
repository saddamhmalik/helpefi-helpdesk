import { computed, unref } from 'vue';
import { useI18n } from 'vue-i18n';

function localizeTree(value, params) {
    if (Array.isArray(value)) {
        return value.map((item) => localizeTree(item, params));
    }

    if (value && typeof value === 'object') {
        return Object.fromEntries(
            Object.entries(value).map(([key, nested]) => [key, localizeTree(nested, params)]),
        );
    }

    if (typeof value !== 'string') {
        return value;
    }

    return Object.entries(params).reduce(
        (result, [key, replacement]) => result.replaceAll(`{${key}}`, String(replacement)),
        value,
    );
}

export function useMarketingCopy(trialDaysSource = 14) {
    const { t, tm } = useI18n();

    const platformName = computed(() => t('app.name'));

    const trialDays = computed(() => {
        const value = unref(trialDaysSource);

        return value ?? 14;
    });

    const copyParams = computed(() => ({
        brand: platformName.value,
        days: String(trialDays.value),
        trialDays: String(trialDays.value),
    }));

    const brandParams = computed(() => ({ brand: platformName.value }));

    const interpolate = (value, params = copyParams.value) => localizeTree(value, params);

    const localize = (value) => localizeTree(value, copyParams.value);

    const localizedMessages = (messageKey, params = copyParams.value) => {
        const raw = tm(unref(messageKey));

        return localizeTree(raw, params);
    };

    const resolveField = (path, params) => {
        const value = tm(path);

        if (typeof value === 'boolean' || typeof value === 'number') {
            return value;
        }

        return t(path, params);
    };

    const localizedList = (messageKey, fields, params = copyParams.value) => {
        const key = unref(messageKey);
        const items = tm(key);

        if (!Array.isArray(items)) {
            return [];
        }

        return items.map((_, index) => Object.fromEntries(
            fields.map((field) => [
                field,
                resolveField(`${key}.${index}.${field}`, params),
            ]),
        ));
    };

    const createLocalizedSection = (messageKeySource) => computed(() => {
        const key = unref(messageKeySource);
        const params = copyParams.value;
        const raw = tm(key);

        if (Array.isArray(raw)) {
            return raw.map((item, index) => {
                if (typeof item === 'string') {
                    return resolveField(`${key}.${index}`, params);
                }

                if (item && typeof item === 'object' && !Array.isArray(item)) {
                    return Object.fromEntries(
                        Object.keys(item).map((field) => [
                            field,
                            resolveField(`${key}.${index}.${field}`, params),
                        ]),
                    );
                }

                return localizeTree(item, params);
            });
        }

        if (raw && typeof raw === 'object') {
            return localizeTree(raw, params);
        }

        return raw;
    });

    return {
        platformName,
        trialDays,
        copyParams,
        brandParams,
        interpolate,
        localize,
        localizedMessages,
        localizedList,
        createLocalizedSection,
    };
}
