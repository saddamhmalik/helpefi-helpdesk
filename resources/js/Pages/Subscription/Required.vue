<script setup>
import { Head, Link, useForm, usePage, router } from '@inertiajs/vue3';
import { onMounted, watch } from 'vue';
import AgentLayout from '../../Layouts/AgentLayout.vue';
import { useCurrency } from '../../composables/useCurrency.js';
import { useBillingInterval } from '../../composables/useBillingInterval.js';
import { useRazorpayCheckout } from '../../composables/useRazorpayCheckout.js';
import { useI18n } from 'vue-i18n';

const props = defineProps({
    billing: Object,
});

const { t } = useI18n();
const page = usePage();
const { open: openRazorpayCheckout } = useRazorpayCheckout();
const isAdmin = page.props.auth?.user?.is_admin;

const form = useForm({
    plan: props.billing.available_plans[0]?.slug ?? 'starter',
});

const { billingInterval, intervalSuffix, planPrice, billingReadyForInterval } = useBillingInterval();

const purchase = () => {
    if (props.billing.razorpay_enabled) {
        router.post('/settings/billing/checkout', {
            plan: form.plan,
            interval: billingInterval.value,
            redirect: '/subscription-required',
        }, { preserveScroll: true });

        return;
    }

    form.transform((data) => ({
        ...data,
        interval: billingInterval.value,
    })).put('/settings/billing/plan', {
        preserveScroll: true,
        onSuccess: () => {
            window.location.href = '/dashboard';
        },
    });
};

const openCheckoutFromFlash = (session) => {
    if (session?.subscription_id) {
        openRazorpayCheckout(session, {
            redirectOnSuccess: '/dashboard',
        });
    }
};

onMounted(() => openCheckoutFromFlash(page.props.flash?.razorpay_checkout));

watch(
    () => page.props.flash?.razorpay_checkout,
    (session) => openCheckoutFromFlash(session),
);

const { formatPrice } = useCurrency(() => props.billing.currency);
</script>

<template>
    <Head :title="$t('subscription_required.choose_a_plan')" />
    <AgentLayout>
        <div class="mx-auto max-w-3xl px-4 py-10">
            <div class="rounded-2xl border border-amber-200 dark:border-amber-900/60 bg-amber-50 dark:bg-amber-950/40 p-6 text-center">
                <p class="text-sm font-semibold uppercase tracking-wider text-amber-700 dark:text-amber-300">{{ $t('subscription_required.trial_ended') }}</p>
                <h1 class="mt-2 text-2xl font-semibold text-slate-900 dark:text-slate-100">{{ $t('subscription_required.choose_a_plan_to_keep_using_your_workspace') }}</h1>
                <p class="mt-2 text-sm text-slate-600 dark:text-slate-400">
                    {{ $t('subscription_required.your_free_trial_has_expired_select_a_plan_below_to_restore_access_for_') }}
                </p>
            </div>

            <div v-if="isAdmin" class="mt-8 space-y-4">
                <div class="inline-flex rounded-lg border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-950 p-1">
                    <button
                        type="button"
                        class="rounded-md px-3 py-1.5 text-sm font-medium transition"
                        :class="billingInterval === 'month' ? 'bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 shadow-sm' : 'text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-slate-100 dark:text-slate-100'"
                        @click="billingInterval = 'month'"
                    >{{ $t('subscription_required.monthly') }}</button>
                    <button
                        type="button"
                        class="rounded-md px-3 py-1.5 text-sm font-medium transition"
                        :class="billingInterval === 'year' ? 'bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 shadow-sm' : 'text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-slate-100 dark:text-slate-100'"
                        @click="billingInterval = 'year'"
                    >{{ $t('subscription_required.yearly') }}</button>
                </div>

                <label
                    v-for="plan in billing.available_plans"
                    :key="plan.slug"
                    class="relative flex cursor-pointer items-start gap-4 rounded-xl border bg-white dark:bg-slate-900 p-5 shadow-sm transition"
                    :class="form.plan === plan.slug ? 'border-blue-600 ring-1 ring-blue-600/20' : 'border-slate-200 dark:border-slate-800 hover:border-slate-300 dark:hover:border-slate-600 dark:border-slate-700'"
                >
                    <input v-model="form.plan" type="radio" :value="plan.slug" class="mt-1" />
                    <div class="flex-1">
                        <div class="flex items-baseline justify-between gap-4">
                            <span class="text-lg font-semibold text-slate-900 dark:text-slate-100">{{ plan.name }}</span>
                            <span class="text-slate-600 dark:text-slate-400">{{ formatPrice(planPrice(plan), intervalSuffix) }}</span>
                        </div>
                        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                            {{ plan.limits.agents }} agents · {{ plan.limits.tickets_monthly }} tickets/mo
                        </p>
                        <p v-if="billing.razorpay_enabled && !billingReadyForInterval(plan)" class="mt-2 text-xs text-amber-700 dark:text-amber-300">
                            Razorpay price not configured for this plan ({{ billingInterval }})
                        </p>
                    </div>
                </label>

                <button type="button" class="w-full rounded-xl bg-blue-600 px-4 py-3 text-sm font-semibold text-white hover:bg-blue-700 disabled:opacity-70" :disabled="form.processing" @click="purchase">
                    {{ form.processing ? 'Opening checkout…' : (billing.razorpay_enabled ? 'Continue to checkout' : 'Activate plan') }}
                </button>

                <p class="text-center text-xs text-slate-500 dark:text-slate-400">
                    {{ billing.razorpay_enabled ? 'Razorpay checkout will open to authorise your subscription.' : 'Simulated checkout for local development — Razorpay is not configured.' }}
                </p>
            </div>

            <div v-else class="mt-8 rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 p-6 text-center shadow-sm">
                <p class="text-slate-700 dark:text-slate-300">{{ $t('subscription_required.this_workspace_trial_has_expired') }}</p>
                <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">{{ $t('subscription_required.contact_your_workspace_admin_to_choose_a_subscription_plan') }}</p>
                <Link href="/logout" method="post" as="button" class="mt-6 text-sm font-medium text-blue-600 hover:text-blue-700 dark:hover:text-blue-300 dark:text-blue-300">
                    {{ $t('subscription_required.sign_out') }}
                </Link>
            </div>
        </div>
    </AgentLayout>
</template>
