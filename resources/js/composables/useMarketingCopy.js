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

    const localizedList = (messageKey, fields, params = copyParams.value) => {
        const key = unref(messageKey);
        const items = tm(key);

        if (!Array.isArray(items)) {
            return [];
        }

        return items.map((_, index) => Object.fromEntries(
            fields.map((field) => [
                field,
                t(`${key}.${index}.${field}`, params),
            ]),
        ));
    };

    const createLocalizedSection = (messageKeySource) => computed(() => {
        const params = copyParams.value;
        const raw = tm(unref(messageKeySource));

        if (Array.isArray(raw)) {
            return localizeTree(raw, params);
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
