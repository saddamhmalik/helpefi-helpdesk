import { computed, toValue } from 'vue';

export function formatMarketingTemplate(value, params = {}) {
    if (typeof value !== 'string') {
        return value ?? '';
    }

    return Object.entries(params).reduce(
        (result, [key, replacement]) => result.replaceAll(`{${key}}`, String(replacement ?? '')),
        value,
    );
}

function resolveSource(source) {
    let value = toValue(source);

    if (typeof value === 'function') {
        value = value();
    }

    return value;
}

export function useMarketingEnglish(brandSource, labelsSource = {}) {
    const platformName = computed(() => resolveSource(brandSource) || 'helpefi');
    const labels = computed(() => resolveSource(labelsSource) ?? {});

    const label = (key, params = {}) => formatMarketingTemplate(
        labels.value[key] ?? key,
        { brand: platformName.value, ...params },
    );

    return {
        platformName,
        labels,
        label,
    };
}

export function marketingHomeSection(homeContent, path) {
    return path.split('.').reduce((value, key) => (value && typeof value === 'object' ? value[key] : undefined), homeContent) ?? (
        Array.isArray(homeContent?.[path]) ? homeContent[path] : []
    );
}
