<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import { usePage } from '@inertiajs/vue3';
import AgentLayout from '../../Layouts/AgentLayout.vue';
import { useCurrency } from '../../composables/useCurrency.js';

const props = defineProps({
    billing: Object,
});

const page = usePage();
const isAdmin = page.props.auth?.user?.is_admin;

const form = useForm({
    plan: props.billing.available_plans[0]?.slug ?? 'starter',
});

const purchase = () => {
    if (props.billing.stripe_enabled) {
        window.location.href = `/settings/billing/checkout?plan=${encodeURIComponent(form.plan)}`;
        return;
    }

    form.put('/settings/billing/plan', {
        preserveScroll: true,
        onSuccess: () => {
            window.location.href = '/dashboard';
        },
    });
};

const { formatPrice } = useCurrency(() => props.billing.currency);
</script>

<template>
    <Head title="Choose a plan" />
    <AgentLayout>
        <div class="mx-auto max-w-3xl px-4 py-10">
            <div class="rounded-2xl border border-amber-200 bg-amber-50 p-6 text-center">
                <p class="text-sm font-semibold uppercase tracking-wider text-amber-700">Trial ended</p>
                <h1 class="mt-2 text-2xl font-semibold text-slate-900">Choose a plan to keep using your workspace</h1>
                <p class="mt-2 text-sm text-slate-600">
                    Your free trial has expired. Select a plan below to restore access for your team.
                </p>
            </div>

            <div v-if="isAdmin" class="mt-8 space-y-4">
                <label
                    v-for="plan in billing.available_plans"
                    :key="plan.slug"
                    class="relative flex cursor-pointer items-start gap-4 rounded-xl border bg-white p-5 shadow-sm transition"
                    :class="form.plan === plan.slug ? 'border-blue-600 ring-1 ring-blue-600/20' : 'border-slate-200 hover:border-slate-300'"
                >
                    <input v-model="form.plan" type="radio" :value="plan.slug" class="mt-1" />
                    <div class="flex-1">
                        <div class="flex items-baseline justify-between gap-4">
                            <span class="text-lg font-semibold text-slate-900">{{ plan.name }}</span>
                            <span class="text-slate-600">{{ formatPrice(plan.price, '/mo') }}</span>
                        </div>
                        <p class="mt-1 text-sm text-slate-500">
                            {{ plan.limits.agents }} agents · {{ plan.limits.tickets_monthly }} tickets/mo
                        </p>
                    </div>
                </label>

                <button type="button" class="w-full rounded-xl bg-blue-600 px-4 py-3 text-sm font-semibold text-white hover:bg-blue-700 disabled:opacity-70" :disabled="form.processing" @click="purchase">
                    {{ form.processing ? 'Redirecting…' : (billing.stripe_enabled ? 'Continue to checkout' : 'Activate plan') }}
                </button>

                <p class="text-center text-xs text-slate-500">
                    {{ billing.stripe_enabled ? 'You will be redirected to Stripe to complete payment.' : 'Simulated checkout for local development — Stripe is not configured.' }}
                </p>
            </div>

            <div v-else class="mt-8 rounded-xl border border-slate-200 bg-white p-6 text-center shadow-sm">
                <p class="text-slate-700">This workspace trial has expired.</p>
                <p class="mt-2 text-sm text-slate-500">Contact your workspace admin to choose a subscription plan.</p>
                <Link href="/logout" method="post" as="button" class="mt-6 text-sm font-medium text-blue-600 hover:text-blue-700">
                    Sign out
                </Link>
            </div>
        </div>
    </AgentLayout>
</template>
