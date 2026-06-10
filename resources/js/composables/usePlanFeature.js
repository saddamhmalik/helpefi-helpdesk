import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';

const PLAN_ORDER = ['starter', 'professional', 'enterprise'];

const FEATURE_I18N_KEYS = {
    automation: 'settings_billing.feature_labels.automation',
    service_catalog: 'settings_billing.feature_labels.service_catalog',
    channels: 'settings_billing.feature_labels.channels',
    sla: 'settings_billing.feature_labels.sla',
    workspace: 'settings_billing.feature_labels.workspace',
    ai: 'settings_billing.feature_labels.ai',
    integrations: 'settings_billing.feature_labels.integrations',
    assets: 'settings_billing.feature_labels.assets',
    custom_domain: 'settings_billing.feature_labels.custom_domain',
    sso: 'settings_billing.feature_labels.sso',
    service_desk: 'settings_billing.feature_labels.service_desk',
};

export function usePlanFeature(feature) {
    const { t, te } = useI18n();
    const billing = computed(() => usePage().props.billing ?? null);

    const hasFeature = computed(() => {
        if (!billing.value?.features) {
            return true;
        }

        return billing.value.features.includes(feature);
    });

    const requiredPlan = computed(() => {
        const plans = billing.value?.available_plans ?? [];

        for (const slug of PLAN_ORDER) {
            const plan = plans.find((item) => item.slug === slug);

            if (plan?.features?.includes(feature)) {
                return plan;
            }
        }

        return plans.find((item) => item.slug === 'enterprise') ?? null;
    });

    const featureLabel = computed(() => {
        const key = FEATURE_I18N_KEYS[feature];

        if (key && te(key)) {
            return t(key);
        }

        return feature.replace(/_/g, ' ');
    });

    const requiredPlanName = computed(() => requiredPlan.value?.name ?? 'Professional');

    const addon = computed(() => {
        if (feature !== 'service_desk') {
            return null;
        }

        return billing.value?.available_addons?.find((item) => item.key === 'service_desk') ?? null;
    });

    const isAddonFeature = computed(() => feature === 'service_desk');

    return {
        billing,
        hasFeature,
        requiredPlan,
        requiredPlanName,
        featureLabel,
        addon,
        isAddonFeature,
    };
}

export function usePlanLimit(limitKey) {
    const billing = computed(() => usePage().props.billing ?? null);

    const limit = computed(() => {
        const raw = billing.value?.limits?.[limitKey];

        if (raw === 'unlimited' || raw === null || raw === undefined) {
            return null;
        }

        return Number(raw);
    });

    const used = computed(() => {
        if (limitKey === 'agents') {
            return (billing.value?.usage?.agents ?? 0) + (billing.value?.usage?.pending_invites ?? 0);
        }

        if (limitKey === 'tickets_monthly') {
            return billing.value?.usage?.tickets_monthly ?? 0;
        }

        return 0;
    });

    const atLimit = computed(() => limit.value !== null && used.value >= limit.value);

    const nearLimit = computed(() => {
        if (limit.value === null || limit.value <= 0) {
            return false;
        }

        return used.value / limit.value >= 0.8;
    });

    return {
        billing,
        limit,
        used,
        atLimit,
        nearLimit,
    };
}
