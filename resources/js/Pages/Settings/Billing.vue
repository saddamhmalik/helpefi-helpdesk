<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import { router, useForm, usePage } from '@inertiajs/vue3';
import SettingsPage from '../../Components/SettingsPage.vue';
import { useSettingsSection } from '../../composables/useSettingsSection.js';
import { useCurrency } from '../../composables/useCurrency.js';
import { useBillingInterval } from '../../composables/useBillingInterval.js';
import { useRazorpayCheckout, checkoutFlowFinishedInUrl } from '../../composables/useRazorpayCheckout.js';
import { useI18n } from 'vue-i18n';
import { useDateTime } from '../../composables/useDateTime.js';

const props = defineProps({
    billing: Object,
    payments: { type: Array, default: () => [] },
});

const page = usePage();
const { open: openRazorpayCheckout } = useRazorpayCheckout();

const { formatDateTime, formatDate } = useDateTime();

const { t, te } = useI18n();

const billingSections = computed(() => ['usage', 'plans', 'addons', 'payments', 'features']);

const { activeSection } = useSettingsSection({
    defaultSection: 'usage',
    sections: billingSections.value,
});

const billingSectionKeys = {
    usage: 'usage_billing',
    plans: 'change_plan',
    addons: 'addons',
    payments: 'payment_history',
    features: 'plan_features',
};

const pageMeta = computed(() => {
    const key = billingSectionKeys[activeSection.value] ?? 'usage_billing';

    return {
        title: t(`settings.${key}`),
        description: t(`settings.descriptions.${key}`),
    };
});

const infoSection = computed(() => billingSectionKeys[activeSection.value] ?? 'usage_billing');

const formatMoney = (amount, currencyCode) => {
    const value = (amount ?? 0) / 100;

    try {
        return new Intl.NumberFormat(undefined, {
            style: 'currency',
            currency: currencyCode ?? props.billing?.currency?.code ?? 'INR',
            minimumFractionDigits: 0,
            maximumFractionDigits: 2,
        }).format(value);
    } catch {
        return `${currencyCode ?? ''} ${value}`.trim();
    }
};

const paymentStatusClass = (status) => ({
    paid: 'bg-emerald-50 text-emerald-700 ring-emerald-200 dark:bg-emerald-950/50 dark:text-emerald-300 dark:ring-emerald-900/60',
    failed: 'bg-red-50 text-red-700 ring-red-200 dark:bg-red-950/50 dark:text-red-300 dark:ring-red-900/60',
    refunded: 'bg-amber-50 text-amber-700 ring-amber-200 dark:bg-amber-950/50 dark:text-amber-300 dark:ring-amber-900/60',
}[status] ?? 'bg-slate-50 text-slate-600 ring-slate-200 dark:bg-slate-800 dark:text-slate-300 dark:ring-slate-700');

const paymentStatusLabel = (status) => {
    const key = `central.${status}`;

    return te(key) ? t(key) : status;
};

const form = useForm({
    plan: props.billing.plan?.slug ?? props.billing.available_plans[0]?.slug ?? 'starter',
});

const checkoutProcessing = ref(false);

const { billingInterval, intervalSuffix, planPrice, billingReadyForInterval } = useBillingInterval();

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

const billingPlansReady = computed(() => (
    props.billing.available_plans.some((plan) => plan.billing_ready_monthly || plan.billing_ready_yearly || plan.billing_ready)
));

const selectedPlanBillingReady = computed(() => {
    if (!selectedPlan.value) {
        return false;
    }

    return billingReadyForInterval(selectedPlan.value);
});

const currentPlanSuffix = computed(() => {
    if (props.billing.plan?.billing_interval === 'year') {
        return '/yr';
    }

    return '/mo';
});

const openCheckoutFromFlash = (session) => {
    if (checkoutFlowFinishedInUrl()) {
        return;
    }

    if (session?.subscription_id) {
        openRazorpayCheckout(session, {
            redirectOnSuccess: session.redirect_on_success ?? '/settings/billing?checkout=success&section=plans',
            redirectOnCancel: session.redirect_on_cancel ?? '/settings/billing?checkout=cancelled&section=plans',
        });
    }
};

const savePlan = () => {
    if (selectedPlan.value?.custom_pricing) {
        form.setError('plan', 'This plan has custom pricing. Please contact us to get started.');
        return;
    }

    if (props.billing.razorpay_enabled) {
        if (!selectedPlanBillingReady.value) {
            form.setError('plan', 'This plan is not configured for Razorpay billing at the selected billing interval yet. Ask your platform admin to add Razorpay price IDs.');
            return;
        }

        checkoutProcessing.value = true;

        router.post('/settings/billing/checkout', {
            plan: form.plan,
            interval: billingInterval.value,
            redirect: '/settings/billing?section=plans',
        }, {
            preserveScroll: true,
            onFinish: () => {
                checkoutProcessing.value = false;
            },
        });

        return;
    }

    form.transform((data) => ({
        ...data,
        interval: billingInterval.value,
    })).put('/settings/billing/plan', { preserveScroll: true });
};

onMounted(() => openCheckoutFromFlash(page.props.flash?.razorpay_checkout));

watch(
    () => page.props.flash?.razorpay_checkout,
    (session) => openCheckoutFromFlash(session),
);

const cancelSubscription = () => {
    router.post('/settings/billing/cancel', {}, { preserveScroll: true });
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
    if (addon.included_in_plan || addon.active) {
        return true;
    }

    if (props.billing.on_trial) {
        return false;
    }

    return props.billing.razorpay_enabled && !addon.billing_ready;
};

const addonStatusLabel = (addon) => {
    if (addon.included_in_plan) {
        return t('settings_billing.addon_included_in_plan');
    }

    if (addon.trial_access) {
        return t('settings_billing.trial_access');
    }

    return t('settings_billing.addon_active');
};

const { formatPrice } = useCurrency(() => props.addon?.currency ?? props.billing?.currency);
const { formatPrice: formatAddonPrice } = useCurrency(() => props.billing?.base_currency ?? props.billing?.currency);

const trialRemainingLabel = computed(() => {
    const days = props.billing.trial_days_remaining;

    if (days == null) {
        return t('settings_billing.free_trial');
    }

    return t(days === 1 ? 'settings_billing.trial_remaining_one' : 'settings_billing.trial_remaining_other', { days });
});

const formatLimit = (limit) => (limit === 'unlimited' ? t('settings_billing.unlimited') : limit);
</script>

<template>
    <SettingsPage
        :title="pageMeta.title"
        :description="pageMeta.description"
        :info-section="infoSection"
    >
        <div v-if="checkoutSuccess" class="mb-4 rounded-xl border border-emerald-200 dark:border-emerald-900/60 bg-emerald-50 dark:bg-emerald-950/40 px-4 py-3 text-sm text-emerald-800 dark:text-emerald-200">
            Payment received. Your plan will update shortly once Razorpay confirms the subscription.
        </div>
        <div v-if="checkoutCancelled" class="mb-4 rounded-xl border border-amber-200 dark:border-amber-900/60 bg-amber-50 dark:bg-amber-950/40 px-4 py-3 text-sm text-amber-800">
            Checkout was cancelled. No changes were made to your subscription.
        </div>
        <div v-if="billing.razorpay_enabled && !billingPlansReady" class="mb-4 rounded-xl border border-red-200 dark:border-red-900/60 bg-red-50 dark:bg-red-950/40 px-4 py-3 text-sm text-red-800 dark:text-red-200">
            Razorpay is connected but no plans have price IDs configured yet. Add Razorpay price IDs in platform admin → Settings.
        </div>

        <div v-show="activeSection === 'usage'" class="agent-card">
                    <h2 class="text-lg font-medium agent-text">{{ $t('settings_billing.current_plan') }}</h2>
                    <div v-if="billing.on_trial" class="mt-3 rounded-lg border border-blue-200 bg-blue-50 px-3 py-2 text-sm text-blue-900 dark:border-blue-900/60 dark:bg-blue-950/40 dark:text-blue-100">
                        {{ trialRemainingLabel }}
                        <span v-if="billing.trial_ends_at" class="text-blue-700 dark:text-blue-300">
                            {{ $t('settings_billing.trial_ends_on', { date: formatDate(billing.trial_ends_at) }) }}
                        </span>
                    </div>
                    <div class="mt-4 flex items-baseline gap-2">
                        <span class="text-3xl font-semibold agent-text">{{ billing.plan.name }}</span>
                        <span v-if="billing.on_trial" class="text-sm agent-text-subtle">{{ $t('settings_billing.full_access_during_trial') }}</span>
                        <span v-else-if="billing.plan.slug" class="text-sm agent-text-subtle">
                            <template v-if="billing.plan.is_custom_price && billing.plan.price == null">{{ $t('settings_billing.custom_pricing') }}</template>
                            <template v-else>{{ formatPrice(billing.plan.price, currentPlanSuffix) }}</template>
                        </span>
                    </div>
                    <p class="mt-2 text-sm agent-text-muted">
                        {{ $t('settings_billing.status_label') }}
                        <span class="font-medium capitalize agent-text">{{ billing.status.replace('_', ' ') }}</span>
                    </p>
                    <p v-if="billing.renews_at" class="mt-1 text-sm agent-text-subtle">
                        {{ $t('settings_billing.renews_on', { date: formatDate(billing.renews_at) }) }}
                    </p>

                    <div class="mt-6 space-y-4 rounded-xl border agent-border agent-panel-muted p-4">
                        <h3 class="text-sm font-medium agent-text">{{ $t('settings_billing.usage_heading') }}</h3>
                        <div>
                            <div class="mb-1 flex justify-between text-sm">
                                <span class="agent-text-muted">{{ $t('settings_billing.team_members') }}</span>
                                <span class="agent-text-subtle">
                                    {{ billing.usage.agents }}
                                    <template v-if="billing.usage.pending_invites">
                                        {{ $t('settings_billing.pending_invites', { count: billing.usage.pending_invites }) }}
                                    </template>
                                    / {{ formatLimit(billing.limits.agents) }}
                                </span>
                            </div>
                            <div class="h-2 overflow-hidden rounded-full bg-slate-100 dark:bg-slate-800">
                                <div class="h-full rounded-full bg-blue-600 transition-all" :style="{ width: usagePercent('agents') + '%' }" />
                            </div>
                        </div>
                        <div>
                            <div class="mb-1 flex justify-between text-sm">
                                <span class="agent-text-muted">{{ $t('settings_billing.tickets_this_month') }}</span>
                                <span class="agent-text-subtle">{{ billing.usage.tickets_monthly }} / {{ formatLimit(billing.limits.tickets_monthly) }}</span>
                            </div>
                            <div class="h-2 overflow-hidden rounded-full bg-slate-100 dark:bg-slate-800">
                                <div class="h-full rounded-full bg-emerald-600 transition-all" :style="{ width: usagePercent('tickets_monthly') + '%' }" />
                            </div>
                        </div>
                    </div>
                </div>

        <div v-show="activeSection === 'plans'" class="max-w-2xl agent-card">
                    <h2 class="text-lg font-medium agent-text">
                        {{ billing.on_trial ? 'Your plan options' : billing.trial_expired ? 'Choose a plan' : 'Change plan' }}
                    </h2>
                    <p class="mt-1 text-sm agent-text-subtle">
                        <template v-if="billing.on_trial">
                            You are on the free trial. Paid plans become available when the trial ends.
                        </template>
                        <template v-else-if="billing.razorpay_enabled">
                            {{ billing.trial_expired ? 'Your trial has ended. Complete checkout to restore full access.' : 'Plan changes are processed through Razorpay checkout.' }}
                        </template>
                        <template v-else>
                            {{ billing.trial_expired ? 'Your trial has ended. Select a plan to restore full access.' : 'Simulated plan switch for local development (Razorpay not configured).' }}
                        </template>
                    </p>

                    <p v-if="form.errors.plan" class="mt-3 rounded-lg border border-red-200 dark:border-red-900/60 bg-red-50 dark:bg-red-950/40 px-3 py-2 text-sm text-red-700 dark:text-red-300">
                        {{ form.errors.plan }}
                    </p>

                    <div v-if="!billing.on_trial" class="mt-4 inline-flex rounded-lg border agent-border agent-panel-muted p-1">
                        <button
                            type="button"
                            class="rounded-md px-3 py-1.5 text-sm font-medium transition"
                            :class="billingInterval === 'month' ? 'agent-panel agent-text shadow-sm' : 'agent-text-muted hover:text-slate-900 dark:text-slate-100 dark:hover:text-slate-100'"
                            @click="billingInterval = 'month'"
                        >{{ $t('settings_billing.monthly') }}</button>
                        <button
                            type="button"
                            class="rounded-md px-3 py-1.5 text-sm font-medium transition"
                            :class="billingInterval === 'year' ? 'agent-panel agent-text shadow-sm' : 'agent-text-muted hover:text-slate-900 dark:text-slate-100 dark:hover:text-slate-100'"
                            @click="billingInterval = 'year'"
                        >{{ $t('settings_billing.yearly') }}</button>
                    </div>

                    <div class="mt-4 space-y-3">
                        <div
                            class="flex items-start gap-3 rounded-lg border p-4"
                            :class="billing.on_trial ? 'border-blue-500 bg-blue-50 dark:bg-blue-950/40' : 'agent-border agent-panel-muted'"
                        >
                            <div class="mt-1 flex h-4 w-4 shrink-0 items-center justify-center rounded-full border-2" :class="billing.on_trial ? 'border-blue-600' : 'agent-border'">
                                <span v-if="billing.on_trial" class="h-2 w-2 rounded-full bg-blue-600" />
                            </div>
                            <div class="flex-1">
                                <div class="flex flex-wrap items-center gap-2">
                                    <span class="font-medium agent-text">{{ $t('settings_billing.free_trial') }}</span>
                                    <span
                                        v-if="billing.on_trial"
                                        class="rounded-full bg-blue-100 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-blue-700 dark:text-blue-300"
                                    >
                                        {{ $t('settings_billing.current') }}
                                    </span>
                                    <span
                                        v-else
                                        class="rounded-full bg-slate-200 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide agent-text-muted"
                                    >
                                        {{ $t('settings_billing.new_workspaces') }}
                                    </span>
                                </div>
                                <p class="mt-1 text-sm agent-text-muted">
                                    Free for {{ billing.trial_offer.days }} days · {{ billing.trial_offer.plan_name }} access
                                </p>
                                <p class="mt-1 text-xs agent-text-subtle">
                                    {{ billing.trial_offer.limits.agents }} agents · {{ billing.trial_offer.limits.tickets_monthly }} tickets/mo
                                </p>
                                <p v-if="billing.on_trial && billing.trial_days_remaining != null" class="mt-2 text-xs font-medium text-blue-700 dark:text-blue-300">
                                    {{ billing.trial_days_remaining }} day{{ billing.trial_days_remaining === 1 ? '' : 's' }} remaining
                                    <span v-if="billing.trial_ends_at">· ends {{ formatDate(billing.trial_ends_at) }}</span>
                                </p>
                                <p v-else-if="!billing.on_trial" class="mt-2 text-xs agent-text-subtle">
                                    {{ $t('settings_billing.every_new_workspace_starts_here_before_choosing_a_paid_plan') }}
                                </p>
                                <p v-if="billing.trial_offer.features.length" class="mt-2 text-xs agent-text-muted">
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
                                    billing.on_trial ? 'cursor-not-allowed agent-border agent-panel-muted opacity-70' : 'cursor-pointer',
                                    !billing.on_trial && form.plan === plan.slug ? 'border-blue-500 bg-blue-50 dark:bg-blue-950/40' : !billing.on_trial ? 'agent-border hover:border-slate-300 dark:border-slate-700 dark:hover:border-slate-600' : '',
                                ]"
                            >
                                <input
                                    v-model="form.plan"
                                    type="radio"
                                    :value="plan.slug"
                                    class="mt-1"
                                    :disabled="billing.on_trial || plan.custom_pricing"
                                />
                                <div class="flex-1">
                                    <div class="flex items-baseline justify-between gap-3">
                                        <span class="font-medium agent-text">{{ plan.name }}</span>
                                        <span class="text-sm agent-text-subtle">{{ plan.custom_pricing ? $t('settings_billing.custom_pricing') : formatPrice(planPrice(plan), intervalSuffix) }}</span>
                                    </div>
                                    <p class="mt-1 text-xs agent-text-subtle">
                                        {{ plan.limits.agents }} agents · {{ plan.limits.tickets_monthly }} tickets/mo
                                    </p>
                                    <p v-if="billing.on_trial" class="mt-2 text-xs agent-text-subtle">
                                        {{ $t('settings_billing.available_after_your_trial_ends') }}
                                    </p>
                                    <p v-if="plan.custom_pricing" class="mt-2 text-xs text-blue-700 dark:text-blue-300">
                                        {{ $t('settings_billing.custom_pricing_contact') }}
                                    </p>
                                    <p v-else-if="billing.razorpay_enabled && !billingReadyForInterval(plan)" class="mt-2 text-xs text-amber-700 dark:text-amber-300">
                                        Razorpay price not configured for this plan ({{ billingInterval }})
                                    </p>
                                    <p v-if="plan.features.length" class="mt-2 text-xs agent-text-muted">
                                        Includes: {{ plan.features.join(', ') }}
                                    </p>
                                </div>
                            </label>

                            <button
                                v-if="!billing.on_trial"
                                type="submit"
                                class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-60"
                                :disabled="form.processing || checkoutProcessing || (billing.razorpay_enabled && !selectedPlanBillingReady)"
                            >
                                {{ billing.razorpay_enabled ? (billing.trial_expired ? 'Continue to checkout' : 'Change plan via Razorpay') : (billing.trial_expired ? 'Activate plan' : 'Update plan') }}
                            </button>
                        </form>
                    </div>

                    <button
                        v-if="billing.razorpay_enabled && billing.has_razorpay_subscription"
                        type="button"
                        class="mt-4 text-sm font-medium text-blue-600 hover:text-blue-700 dark:hover:text-blue-300 dark:text-blue-300"
                        @click="cancelSubscription"
                    >Cancel subscription at period end</button>
                </div>

        <div v-show="activeSection === 'addons'" class="agent-card">
            <h2 class="text-lg font-medium agent-text">{{ $t('settings_billing.paid_addons') }}</h2>
            <p class="mt-1 text-sm agent-text-subtle">
                {{ $t('settings_billing.paid_addons_description') }}
                <span v-if="billing.on_trial" class="mt-1 block text-blue-700 dark:text-blue-300">
                    {{ $t('settings_billing.paid_addons_trial_description') }}
                </span>
            </p>

            <div v-if="!billing.available_addons?.length" class="mt-4 text-sm agent-text-subtle">{{ $t('settings_billing.no_addons_available') }}</div>

            <div v-else class="mt-4 space-y-4">
                <div
                    v-for="addon in billing.available_addons"
                    :key="addon.key"
                    class="rounded-xl border p-4"
                    :class="addon.active
                        ? 'border-emerald-200 bg-emerald-50 dark:border-emerald-900/60 dark:bg-emerald-950/40'
                        : 'agent-border agent-panel'"
                >
                    <div class="flex items-start justify-between gap-4">
                        <div class="min-w-0 flex-1">
                            <p class="font-medium agent-text">{{ addon.name }}</p>
                            <p class="mt-1 text-sm agent-text-muted">{{ addon.description }}</p>
                            <p class="mt-2 text-sm font-semibold agent-text">{{ formatAddonPrice(addon.price_monthly) }}/mo</p>
                        </div>
                        <div class="flex shrink-0 flex-col items-end gap-2 sm:flex-row sm:items-center">
                            <span
                                v-if="addon.active"
                                class="rounded-full px-2.5 py-1 text-xs font-semibold"
                                :class="addon.trial_access
                                    ? 'bg-blue-100 text-blue-800 dark:bg-blue-950/50 dark:text-blue-200'
                                    : addon.included_in_plan
                                        ? 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-200'
                                        : 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/50 dark:text-emerald-200'"
                            >
                                {{ addonStatusLabel(addon) }}
                            </span>
                            <button
                                v-if="addon.active && !addon.included_in_plan"
                                type="button"
                                class="agent-btn-secondary px-3 py-1.5"
                                @click="cancelAddon(addon.key)"
                            >
                                {{ $t('settings_billing.cancel_addon') }}
                            </button>
                            <button
                                v-else-if="!addon.included_in_plan"
                                type="button"
                                class="rounded-lg bg-blue-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-60"
                                :disabled="addonPurchaseDisabled(addon)"
                                @click="purchaseAddon(addon.key)"
                            >
                                {{ billing.on_trial ? $t('settings_billing.enable_for_trial') : $t('settings_billing.add_to_subscription') }}
                            </button>
                        </div>
                    </div>
                    <p v-if="addon.active && billing.on_trial" class="mt-3 text-xs text-blue-700 dark:text-blue-300">
                        {{ $t('settings_billing.addon_trial_included_note', { price: formatAddonPrice(addon.price_monthly) }) }}
                    </p>
                    <p v-else-if="billing.on_trial && !addon.active" class="mt-3 text-xs agent-text-subtle">
                        {{ $t('settings_billing.addon_trial_try_note', { price: formatAddonPrice(addon.price_monthly) }) }}
                    </p>
                    <p v-else-if="billing.razorpay_enabled && !addon.billing_ready" class="mt-3 text-xs text-amber-700 dark:text-amber-300">{{ $t('settings_billing.addon_billing_not_ready') }}</p>
                </div>
            </div>
        </div>

        <div v-show="activeSection === 'payments'" class="agent-card">
            <h2 class="text-lg font-medium agent-text">{{ $t('settings.payment_history') }}</h2>
            <p class="mt-1 text-sm agent-text-subtle" dir="auto">{{ $t('settings.descriptions.payment_history') }}</p>

            <div v-if="payments.length" class="mt-4 overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead>
                        <tr class="border-b agent-border text-start agent-text-subtle">
                            <th class="px-4 py-3 font-semibold">{{ $t('central.date') }}</th>
                            <th class="px-4 py-3 font-semibold">{{ $t('central.amount') }}</th>
                            <th class="px-4 py-3 font-semibold">{{ $t('settings_billing.plan') }}</th>
                            <th class="px-4 py-3 font-semibold">{{ $t('central.status') }}</th>
                            <th class="px-4 py-3 font-semibold">{{ $t('central.invoice') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="payment in payments" :key="payment.id" class="border-b agent-border last:border-0">
                            <td class="whitespace-nowrap px-4 py-3 agent-text" dir="ltr">{{ formatDate(payment.paid_at ?? payment.created_at) }}</td>
                            <td class="whitespace-nowrap px-4 py-3 font-medium agent-text" dir="ltr">{{ formatMoney(payment.amount, payment.currency) }}</td>
                            <td class="whitespace-nowrap px-4 py-3 agent-text">{{ payment.plan_name ?? '—' }}</td>
                            <td class="whitespace-nowrap px-4 py-3">
                                <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium ring-1 ring-inset" :class="paymentStatusClass(payment.status)">{{ paymentStatusLabel(payment.status) }}</span>
                            </td>
                            <td class="whitespace-nowrap px-4 py-3">
                                <a
                                    v-if="payment.invoice_pdf || payment.invoice_url"
                                    :href="payment.invoice_pdf ?? payment.invoice_url"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    class="font-medium text-blue-600 hover:text-blue-700 dark:text-blue-400 dark:hover:text-blue-300"
                                >
                                    {{ payment.invoice_number ?? $t('central.view') }}
                                </a>
                                <span v-else class="agent-text-subtle" dir="ltr">{{ payment.invoice_number ?? '—' }}</span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <p v-else class="mt-4 rounded-lg border agent-border agent-panel-muted px-4 py-6 text-center text-sm agent-text-subtle">
                {{ $t('settings_billing.no_payments_yet') }}
            </p>
        </div>

        <div v-show="activeSection === 'features'" class="agent-card">
                    <h2 class="text-lg font-medium agent-text">{{ $t('settings_billing.feature_access') }}</h2>
                    <div class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                        <div
                            v-for="feature in billingFeatures"
                            :key="feature"
                            class="flex items-center gap-2 rounded-lg border px-3 py-2 text-sm"
                            :class="hasFeature(feature) ? 'border-emerald-200 dark:border-emerald-900/60 bg-emerald-50 dark:bg-emerald-950/40 text-emerald-800 dark:text-emerald-200' : 'agent-border agent-panel-muted agent-text-subtle'"
                        >
                            <span>{{ featureLabel(feature) }}</span>
                            <span class="ml-auto text-xs font-medium">{{ hasFeature(feature) ? 'Included' : 'Locked' }}</span>
                        </div>
                    </div>
                </div>
    </SettingsPage>
</template>
