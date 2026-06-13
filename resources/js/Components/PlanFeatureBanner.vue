<script setup>
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { useCurrency } from '../composables/useCurrency.js';
import { usePlanFeature } from '../composables/usePlanFeature.js';

const props = defineProps({
    feature: { type: String, required: true },
});

const { t } = useI18n();
const { hasFeature, requiredPlanName, featureLabel, addon, isAddonFeature, billing } = usePlanFeature(props.feature);
const { formatPrice } = useCurrency(() => billing.value?.currency ?? addon.value?.currency);

const billingHref = computed(() => {
    if (isAddonFeature.value) {
        return '/settings/billing?section=addons';
    }

    return '/settings/billing?section=plans';
});

const message = computed(() => {
    if (hasFeature.value) {
        return '';
    }

    if (isAddonFeature.value) {
        if (billing.value?.on_trial) {
            return t('settings_billing.addon_feature_trial_hint', { feature: featureLabel.value });
        }

        return t('settings_billing.addon_feature_hint', {
            feature: featureLabel.value,
            price: addon.value?.price_monthly ? `${formatPrice(addon.value.price_monthly)}/mo` : '',
        });
    }

    return t('settings_billing.plan_feature_hint', {
        feature: featureLabel.value,
        plan: requiredPlanName.value,
    });
});

const actionLabel = computed(() => {
    if (isAddonFeature.value) {
        return billing.value?.on_trial
            ? t('settings_billing.enable_for_trial')
            : t('settings_billing.view_addons');
    }

    return t('settings_billing.upgrade_plan');
});
</script>

<template>
    <div
        v-if="!hasFeature"
        class="mb-6 rounded-xl border border-amber-200 dark:border-amber-900/60 bg-amber-50 dark:bg-amber-950/40 px-4 py-3 text-sm text-amber-950 dark:text-amber-200"
    >
        <p>{{ message }}</p>
        <Link
            :href="billingHref"
            class="mt-2 inline-flex font-medium text-amber-900 dark:text-amber-200 underline decoration-amber-400 underline-offset-2 hover:text-amber-950 dark:hover:text-amber-100"
        >
            {{ actionLabel }}
        </Link>
    </div>
</template>
