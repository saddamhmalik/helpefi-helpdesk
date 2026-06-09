<script setup>
import { computed } from 'vue';
import { useForm } from '@inertiajs/vue3';
import SettingsLayout from '../../Layouts/SettingsLayout.vue';
import { useSettingsSection } from '../../composables/useSettingsSection.js';
import { useCurrency } from '../../composables/useCurrency.js';

const props = defineProps({
    billing: Object,
});

const billingSections = computed(() => ['usage', 'plans', 'features']);

const { activeSection } = useSettingsSection({
    defaultSection: 'usage',
    sections: billingSections.value,
});

const form = useForm({
    plan: props.billing.plan.slug ?? props.billing.available_plans[0]?.slug ?? 'starter',
});

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
    props.billing.available_plans.some((plan) => plan.stripe_ready)
));

const savePlan = () => {
    if (props.billing.stripe_enabled) {
        if (!selectedPlan.value?.stripe_ready) {
            form.setError('plan', 'This plan is not configured for Stripe billing yet. Ask your platform admin to add Stripe price IDs.');
            return;
        }

        window.location.href = `/settings/billing/checkout?plan=${encodeURIComponent(form.plan)}`;
        return;
    }

    form.put('/settings/billing/plan', { preserveScroll: true });
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

const { formatPrice } = useCurrency(() => props.billing.currency);
</script>

<template>
    <SettingsLayout
        title="Billing & plan"
        description="Manage your workspace subscription. Billing is handled on the central platform while usage is measured in this workspace."
    >
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
                    <h2 class="text-lg font-medium text-slate-900">Current plan</h2>
                    <div v-if="billing.on_trial" class="mt-3 rounded-lg border border-blue-200 bg-blue-50 px-3 py-2 text-sm text-blue-900">
                        Free trial · {{ billing.trial_days_remaining }} day{{ billing.trial_days_remaining === 1 ? '' : 's' }} remaining
                        <span v-if="billing.trial_ends_at" class="text-blue-700"> (ends {{ new Date(billing.trial_ends_at).toLocaleDateString() }})</span>
                    </div>
                    <div class="mt-4 flex items-baseline gap-2">
                        <span class="text-3xl font-semibold text-slate-900">{{ billing.plan.name }}</span>
                        <span v-if="billing.on_trial" class="text-sm text-slate-500">Full access during trial</span>
                        <span v-else-if="billing.plan.slug" class="text-sm text-slate-500">{{ formatPrice(billing.plan.price, '/mo') }}</span>
                    </div>
                    <p class="mt-2 text-sm text-slate-600">
                        Status:
                        <span class="font-medium capitalize">{{ billing.status.replace('_', ' ') }}</span>
                    </p>
                    <p v-if="billing.renews_at" class="mt-1 text-sm text-slate-500">
                        Renews {{ new Date(billing.renews_at).toLocaleDateString() }}
                    </p>

                    <div class="mt-6 space-y-4">
                        <div>
                            <div class="mb-1 flex justify-between text-sm">
                                <span class="text-slate-700">Team members</span>
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
                                <span class="text-slate-700">Tickets this month</span>
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
                                    <span class="font-medium text-slate-900">Free trial</span>
                                    <span
                                        v-if="billing.on_trial"
                                        class="rounded-full bg-blue-100 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-blue-700"
                                    >
                                        Current
                                    </span>
                                    <span
                                        v-else
                                        class="rounded-full bg-slate-200 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-slate-600"
                                    >
                                        New workspaces
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
                                    <span v-if="billing.trial_ends_at">· ends {{ new Date(billing.trial_ends_at).toLocaleDateString() }}</span>
                                </p>
                                <p v-else-if="!billing.on_trial" class="mt-2 text-xs text-slate-500">
                                    Every new workspace starts here before choosing a paid plan.
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
                                        <span class="text-sm text-slate-500">{{ formatPrice(plan.price, '/mo') }}</span>
                                    </div>
                                    <p class="mt-1 text-xs text-slate-500">
                                        {{ plan.limits.agents }} agents · {{ plan.limits.tickets_monthly }} tickets/mo
                                    </p>
                                    <p v-if="billing.on_trial" class="mt-2 text-xs text-slate-500">
                                        Available after your trial ends
                                    </p>
                                    <p v-if="billing.stripe_enabled && !plan.stripe_ready" class="mt-2 text-xs text-amber-700">
                                        Stripe price not configured for this plan
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
                                :disabled="form.processing || (billing.stripe_enabled && !stripePlansReady)"
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
                    >
                        Manage payment method & invoices
                    </button>
                </div>

        <div v-show="activeSection === 'features'" class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
                    <h2 class="text-lg font-medium text-slate-900">Feature access</h2>
                    <div class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                        <div
                            v-for="feature in ['automation', 'service_catalog', 'ai', 'integrations', 'assets', 'channels', 'sla', 'workspace']"
                            :key="feature"
                            class="flex items-center gap-2 rounded-lg border px-3 py-2 text-sm"
                            :class="hasFeature(feature) ? 'border-emerald-200 bg-emerald-50 text-emerald-800' : 'border-slate-200 bg-slate-50 text-slate-500'"
                        >
                            <span>{{ feature.replace('_', ' ') }}</span>
                            <span class="ml-auto text-xs font-medium">{{ hasFeature(feature) ? 'Included' : 'Locked' }}</span>
                        </div>
                    </div>
                </div>
    </SettingsLayout>
</template>
