<script setup>
import { computed } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import SettingsPage from '../../Components/SettingsPage.vue';
import SettingsSectionNav from '../../Components/SettingsSectionNav.vue';
import { useSettingsSection } from '../../composables/useSettingsSection.js';
import { useCurrency } from '../../composables/useCurrency.js';
import { useBillingInterval } from '../../composables/useBillingInterval.js';
import { useI18n } from 'vue-i18n';
import { useDateTime } from '../../composables/useDateTime.js';

const props = defineProps({
    billing: Object,
});

const { formatDateTime, formatDate } = useDateTime();

const { t } = useI18n();

const billingSections = computed(() => ['usage', 'plans', 'addons', 'features']);

const { activeSection } = useSettingsSection({
    defaultSection: 'usage',
    sections: billingSections.value,
});

const sectionTabs = computed(() => [
    { id: 'usage', label: t('settings.usage_billing') },
    { id: 'plans', label: t('settings.change_plan') },
    { id: 'addons', label: t('settings.addons') },
    { id: 'features', label: t('settings.plan_features') },
]);

const form = useForm({
    plan: props.billing.plan?.slug ?? props.billing.available_plans[0]?.slug ?? 'starter',
});

const { billingInterval, intervalSuffix, planPrice, stripeReadyForInterval } = useBillingInterval();

const checkoutSuccess = computed(() => {
    const params = new URLSearchParams(window.location.search);

    return params.get('checkout') === 'success';
});

const checkoutCancelled = computed(() => {
    const params = new URLSearchParams(window.location.search);

    return params.get('checkout') === 'cancelled';
});

const selectedPlan = computed(() => (
    props.billing.available_plans.find((plan) => plan.slug === form.plan)
));

const stripePlansReady = computed(() => (
    props.billing.available_plans.some((plan) => plan.stripe_ready_monthly || plan.stripe_ready_yearly || plan.stripe_ready)
));

const selectedPlanStripeReady = computed(() => {
    if (!selectedPlan.value) {
        return false;
    }

    return stripeReadyForInterval(selectedPlan.value);
});

const currentPlanSuffix = computed(() => {
    if (props.billing.plan?.billing_interval === 'year') {
        return '/yr';
    }

    return '/mo';
});

const savePlan = () => {
    if (props.billing.stripe_enabled) {
        if (!selectedPlanStripeReady.value) {
            form.setError('plan', 'This plan is not configured for Stripe billing at the selected billing interval yet. Ask your platform admin to add Stripe price IDs.');
            return;
        }

        window.location.href = `/settings/billing/checkout?plan=${encodeURIComponent(form.plan)}&interval=${encodeURIComponent(billingInterval.value)}`;
        return;
    }

    form.transform((data) => ({
        ...data,
        interval: billingInterval.value,
    })).put('/settings/billing/plan', { preserveScroll: true });
};

const openPortal = () => {
    window.location.href = '/settings/billing/portal';
};

const usagePercent = (key) => {
    const limit = props.billing.limits[key];
    const used = props.billing.usage[key === 'agents' ? 'agents' : 'tickets_monthly'];
    if (limit === 'unlimited') {
        return 0;
    }
    return Math.min(100, Math.round((used / limit) * 100));
};

const hasFeature = (feature) => props.billing.features.includes(feature);

const billingFeatures = [
    'automation',
    'service_catalog',
    'service_desk',
    'ai',
    'integrations',
    'assets',
    'channels',
    'sla',
    'workspace',
    'custom_domain',
    'sso',
];

const featureLabel = (feature) => {
    const key = `settings_billing.feature_labels.${feature}`;

    return t(key, feature.replace(/_/g, ' '));
};

const purchaseAddon = (key) => {
    router.post(`/settings/billing/addons/${key}`, {}, { preserveScroll: true });
};

const cancelAddon = (key) => {
    router.delete(`/settings/billing/addons/${key}`, { preserveScroll: true });
};

const addonPurchaseDisabled = (addon) => {
    if (addon.active) {
        return true;
    }

    if (props.billing.on_trial) {
        return false;
    }

    return props.billing.stripe_enabled && !addon.stripe_ready;
};

const addonStatusLabel = (addon) => {
    if (addon.trial_access) {
        return 'Trial access';
    }

    return 'Active';
};

const { formatPrice } = useCurrency(() => props.billing.currency);
</script>

<template>
    <SettingsPage
        :title="$t('settings.billing')"
        :description="$t('settings_billing.manage_your_workspace_subscription_billing_is_handled_on_the_central_p')"
    >
        <SettingsSectionNav
            path="/settings/billing"
            default-section="usage"
            :sections="sectionTabs"
            :active-section="activeSection"
        />

        <div v-if="checkoutSuccess" class="mb-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
            Payment received. Your plan will update shortly once Stripe confirms the subscription.
        </div>
        <div v-if="checkoutCancelled" class="mb-4 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
            Checkout was cancelled. No changes were made to your subscription.
        </div>
        <div v-if="billing.stripe_enabled && !stripePlansReady" class="mb-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
            Stripe is connected but no plans have price IDs configured yet. Add Stripe price IDs in platform admin → Settings.
        </div>

        <div v-show="activeSection === 'usage'" class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
                    <h2 class="text-lg font-medium text-slate-900">{{ $t('settings_billing.current_plan') }}</h2>
                    <div v-if="billing.on_trial" class="mt-3 rounded-lg border border-blue-200 bg-blue-50 px-3 py-2 text-sm text-blue-900">
                        Free trial · {{ billing.trial_days_remaining }} day{{ billing.trial_days_remaining === 1 ? '' : 's' }} remaining
                        <span v-if="billing.trial_ends_at" class="text-blue-700"> (ends {{ formatDate(billing.trial_ends_at) }})</span>
                    </div>
                    <div class="mt-4 flex items-baseline gap-2">
                        <span class="text-3xl font-semibold text-slate-900">{{ billing.plan.name }}</span>
                        <span v-if="billing.on_trial" class="text-sm text-slate-500">{{ $t('settings_billing.full_access_during_trial') }}</span>
                        <span v-else-if="billing.plan.slug" class="text-sm text-slate-500">{{ formatPrice(billing.plan.price, currentPlanSuffix) }}</span>
                    </div>
                    <p class="mt-2 text-sm text-slate-600">
                        Status:
                        <span class="font-medium capitalize">{{ billing.status.replace('_', ' ') }}</span>
                    </p>
                    <p v-if="billing.renews_at" class="mt-1 text-sm text-slate-500">
                        Renews {{ formatDate(billing.renews_at) }}
                    </p>

                    <div class="mt-6 space-y-4">
                        <div>
                            <div class="mb-1 flex justify-between text-sm">
                                <span class="text-slate-700">{{ $t('settings_billing.team_members') }}</span>
                                <span class="text-slate-500">
                                    {{ billing.usage.agents }}
                                    <template v-if="billing.usage.pending_invites"> (+{{ billing.usage.pending_invites }} pending)</template>
                                    / {{ billing.limits.agents }}
                                </span>
                            </div>
                            <div class="h-2 overflow-hidden rounded-full bg-slate-100">
                                <div class="h-full rounded-full bg-blue-600 transition-all" :style="{ width: usagePercent('agents') + '%' }" />
                            </div>
                        </div>
                        <div>
                            <div class="mb-1 flex justify-between text-sm">
                                <span class="text-slate-700">{{ $t('settings_billing.tickets_this_month') }}</span>
                                <span class="text-slate-500">{{ billing.usage.tickets_monthly }} / {{ billing.limits.tickets_monthly }}</span>
                            </div>
                            <div class="h-2 overflow-hidden rounded-full bg-slate-100">
                                <div class="h-full rounded-full bg-emerald-600 transition-all" :style="{ width: usagePercent('tickets_monthly') + '%' }" />
                            </div>
                        </div>
                    </div>
                </div>

        <div v-show="activeSection === 'plans'" class="max-w-2xl rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
                    <h2 class="text-lg font-medium text-slate-900">
                        {{ billing.on_trial ? 'Your plan options' : billing.trial_expired ? 'Choose a plan' : 'Change plan' }}
                    </h2>
                    <p class="mt-1 text-sm text-slate-500">
                        <template v-if="billing.on_trial">
                            You are on the free trial. Paid plans become available when the trial ends.
                        </template>
                        <template v-else-if="billing.stripe_enabled">
                            {{ billing.trial_expired ? 'Your trial has ended. Complete checkout to restore full access.' : 'Plan changes are processed through Stripe checkout.' }}
                        </template>
                        <template v-else>
                            {{ billing.trial_expired ? 'Your trial has ended. Select a plan to restore full access.' : 'Simulated plan switch for local development (Stripe not configured).' }}
                        </template>
                    </p>

                    <p v-if="form.errors.plan" class="mt-3 rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">
                        {{ form.errors.plan }}
                    </p>

                    <div v-if="!billing.on_trial" class="mt-4 inline-flex rounded-lg border border-slate-200 bg-slate-50 p-1">
                        <button
                            type="button"
                            class="rounded-md px-3 py-1.5 text-sm font-medium transition"
                            :class="billingInterval === 'month' ? 'bg-white text-slate-900 shadow-sm' : 'text-slate-600 hover:text-slate-900'"
                            @click="billingInterval = 'month'"
                        >{{ $t('settings_billing.monthly') }}</button>
                        <button
                            type="button"
                            class="rounded-md px-3 py-1.5 text-sm font-medium transition"
                            :class="billingInterval === 'year' ? 'bg-white text-slate-900 shadow-sm' : 'text-slate-600 hover:text-slate-900'"
                            @click="billingInterval = 'year'"
                        >{{ $t('settings_billing.yearly') }}</button>
                    </div>

                    <div class="mt-4 space-y-3">
                        <div
                            class="flex items-start gap-3 rounded-lg border p-4"
                            :class="billing.on_trial ? 'border-blue-500 bg-blue-50' : 'border-slate-200 bg-slate-50'"
                        >
                            <div class="mt-1 flex h-4 w-4 shrink-0 items-center justify-center rounded-full border-2" :class="billing.on_trial ? 'border-blue-600' : 'border-slate-300'">
                                <span v-if="billing.on_trial" class="h-2 w-2 rounded-full bg-blue-600" />
                            </div>
                            <div class="flex-1">
                                <div class="flex flex-wrap items-center gap-2">
                                    <span class="font-medium text-slate-900">{{ $t('settings_billing.free_trial') }}</span>
                                    <span
                                        v-if="billing.on_trial"
                                        class="rounded-full bg-blue-100 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-blue-700"
                                    >
                                        {{ $t('settings_billing.current') }}
                                    </span>
                                    <span
                                        v-else
                                        class="rounded-full bg-slate-200 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-slate-600"
                                    >
                                        {{ $t('settings_billing.new_workspaces') }}
                                    </span>
                                </div>
                                <p class="mt-1 text-sm text-slate-600">
                                    Free for {{ billing.trial_offer.days }} days · {{ billing.trial_offer.plan_name }} access
                                </p>
                                <p class="mt-1 text-xs text-slate-500">
                                    {{ billing.trial_offer.limits.agents }} agents · {{ billing.trial_offer.limits.tickets_monthly }} tickets/mo
                                </p>
                                <p v-if="billing.on_trial && billing.trial_days_remaining != null" class="mt-2 text-xs font-medium text-blue-700">
                                    {{ billing.trial_days_remaining }} day{{ billing.trial_days_remaining === 1 ? '' : 's' }} remaining
                                    <span v-if="billing.trial_ends_at">· ends {{ formatDate(billing.trial_ends_at) }}</span>
                                </p>
                                <p v-else-if="!billing.on_trial" class="mt-2 text-xs text-slate-500">
                                    {{ $t('settings_billing.every_new_workspace_starts_here_before_choosing_a_paid_plan') }}
                                </p>
                                <p v-if="billing.trial_offer.features.length" class="mt-2 text-xs text-slate-600">
                                    Includes: {{ billing.trial_offer.features.join(', ') }}
                                </p>
                            </div>
                        </div>

                        <form class="space-y-3" @submit.prevent="savePlan">
                            <label
                                v-for="plan in billing.available_plans"
                                :key="plan.slug"
                                class="flex items-start gap-3 rounded-lg border p-4 transition"
                                :class="[
                                    billing.on_trial ? 'cursor-not-allowed border-slate-200 bg-slate-50 opacity-70' : 'cursor-pointer',
                                    !billing.on_trial && form.plan === plan.slug ? 'border-blue-500 bg-blue-50' : !billing.on_trial ? 'border-slate-200 hover:border-slate-300' : '',
                                ]"
                            >
                                <input
                                    v-model="form.plan"
                                    type="radio"
                                    :value="plan.slug"
                                    class="mt-1"
                                    :disabled="billing.on_trial"
                                />
                                <div class="flex-1">
                                    <div class="flex items-baseline justify-between gap-3">
                                        <span class="font-medium text-slate-900">{{ plan.name }}</span>
                                        <span class="text-sm text-slate-500">{{ formatPrice(planPrice(plan), intervalSuffix) }}</span>
                                    </div>
                                    <p class="mt-1 text-xs text-slate-500">
                                        {{ plan.limits.agents }} agents · {{ plan.limits.tickets_monthly }} tickets/mo
                                    </p>
                                    <p v-if="billing.on_trial" class="mt-2 text-xs text-slate-500">
                                        {{ $t('settings_billing.available_after_your_trial_ends') }}
                                    </p>
                                    <p v-if="billing.stripe_enabled && !stripeReadyForInterval(plan)" class="mt-2 text-xs text-amber-700">
                                        Stripe price not configured for this plan ({{ billingInterval }})
                                    </p>
                                    <p v-if="plan.features.length" class="mt-2 text-xs text-slate-600">
                                        Includes: {{ plan.features.join(', ') }}
                                    </p>
                                </div>
                            </label>

                            <button
                                v-if="!billing.on_trial"
                                type="submit"
                                class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-60"
                                :disabled="form.processing || (billing.stripe_enabled && !selectedPlanStripeReady)"
                            >
                                {{ billing.stripe_enabled ? (billing.trial_expired ? 'Continue to checkout' : 'Change plan via Stripe') : (billing.trial_expired ? 'Activate plan' : 'Update plan') }}
                            </button>
                        </form>
                    </div>

                    <button
                        v-if="billing.stripe_enabled && billing.has_stripe_subscription"
                        type="button"
                        class="mt-4 text-sm font-medium text-blue-600 hover:text-blue-700"
                        @click="openPortal"
                    >{{ $t('settings_billing.manage_payment_method_invoices') }}</button>
                </div>

        <div v-show="activeSection === 'addons'" class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
            <h2 class="text-lg font-medium text-slate-900">Paid add-ons</h2>
            <p class="mt-1 text-sm text-slate-500">
                Purchase optional modules on top of your base plan.
                <span v-if="billing.on_trial" class="block mt-1 text-blue-700">
                    You can try add-ons during your free trial. After the trial ends, you will need an active paid plan and a paid add-on subscription to keep using them.
                </span>
            </p>

            <div v-if="!billing.available_addons?.length" class="mt-4 text-sm text-slate-500">No add-ons are available right now.</div>

            <div v-else class="mt-4 space-y-4">
                <div
                    v-for="addon in billing.available_addons"
                    :key="addon.key"
                    class="rounded-xl border p-4"
                    :class="addon.active ? 'border-emerald-200 bg-emerald-50/40' : 'border-slate-200'"
                >
                    <div class="flex flex-wrap items-start justify-between gap-4">
                        <div>
                            <p class="font-medium text-slate-900">{{ addon.name }}</p>
                            <p class="mt-1 text-sm text-slate-600">{{ addon.description }}</p>
                            <p class="mt-2 text-sm font-semibold text-slate-800">{{ formatPrice(addon.price_monthly) }}/mo</p>
                        </div>
                        <div class="flex items-center gap-2">
                            <span
                                v-if="addon.active"
                                class="rounded-full px-2.5 py-1 text-xs font-semibold"
                                :class="addon.trial_access ? 'bg-blue-100 text-blue-800' : 'bg-emerald-100 text-emerald-800'"
                            >
                                {{ addonStatusLabel(addon) }}
                            </span>
                            <button
                                v-if="addon.active"
                                type="button"
                                class="rounded-lg border border-slate-300 px-3 py-1.5 text-sm text-slate-700 hover:bg-white"
                                @click="cancelAddon(addon.key)"
                            >
                                Cancel add-on
                            </button>
                            <button
                                v-else
                                type="button"
                                class="rounded-lg bg-blue-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-60"
                                :disabled="addonPurchaseDisabled(addon)"
                                @click="purchaseAddon(addon.key)"
                            >
                                {{ billing.on_trial ? 'Enable for trial' : 'Add to subscription' }}
                            </button>
                        </div>
                    </div>
                    <p v-if="addon.active && billing.on_trial" class="mt-3 text-xs text-blue-700">
                        Included during your free trial. After your trial ends, this add-on costs {{ formatPrice(addon.price_monthly) }}/mo on top of your paid plan.
                    </p>
                    <p v-else-if="billing.on_trial && !addon.active" class="mt-3 text-xs text-slate-500">
                        Try it free during your trial. After your trial ends, you will need a paid plan plus {{ formatPrice(addon.price_monthly) }}/mo for this add-on.
                    </p>
                    <p v-else-if="billing.stripe_enabled && !addon.stripe_ready" class="mt-3 text-xs text-amber-700">Stripe price not configured for this add-on yet.</p>
                </div>
            </div>
        </div>

        <div v-show="activeSection === 'features'" class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
                    <h2 class="text-lg font-medium text-slate-900">{{ $t('settings_billing.feature_access') }}</h2>
                    <div class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                        <div
                            v-for="feature in billingFeatures"
                            :key="feature"
                            class="flex items-center gap-2 rounded-lg border px-3 py-2 text-sm"
                            :class="hasFeature(feature) ? 'border-emerald-200 bg-emerald-50 text-emerald-800' : 'border-slate-200 bg-slate-50 text-slate-500'"
                        >
                            <span>{{ featureLabel(feature) }}</span>
                            <span class="ml-auto text-xs font-medium">{{ hasFeature(feature) ? 'Included' : 'Locked' }}</span>
                        </div>
                    </div>
                </div>
    </SettingsPage>
</template>
