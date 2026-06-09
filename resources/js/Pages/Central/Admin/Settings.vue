<script setup>
import { computed } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import AdminLayout from '../../../Layouts/AdminLayout.vue';
import PageHeader from '../../../Components/PageHeader.vue';

const props = defineProps({
    settings: Object,
    availableCurrencies: { type: Array, default: () => [] },
    planCatalog: { type: Object, default: () => ({ limits: [], features: [] }) },
});

const mapPlan = (plan) => {
    const unlimited = {};
    const limits = {};

    props.planCatalog.limits.forEach(({ key }) => {
        unlimited[key] = plan.limits?.[key] === null;
        limits[key] = plan.limits?.[key] ?? 1;
    });

    return {
        slug: plan.slug,
        name: plan.name,
        price: plan.price,
        stripe_product_id: plan.stripe_product_id ?? '',
        stripe_price_id: plan.stripe_price_id ?? '',
        limits,
        unlimited,
        features: [...(plan.features ?? [])],
    };
};

const form = useForm({
    trial_days: props.settings.trial_days,
    currency: props.settings.currency,
    plans: props.settings.plans.map(mapPlan),
});

const selectedCurrency = computed(() => (
    props.availableCurrencies.find((item) => item.code === form.currency)
    ?? { symbol: '$', code: 'USD', name: 'US Dollar' }
));

const toggleFeature = (plan, featureKey) => {
    const index = plan.features.indexOf(featureKey);

    if (index === -1) {
        plan.features.push(featureKey);
    } else {
        plan.features.splice(index, 1);
    }
};

const hasFeature = (plan, featureKey) => plan.features.includes(featureKey);

const submit = () => {
    form.transform((data) => ({
        ...data,
        plans: data.plans.map((plan) => ({
            slug: plan.slug,
            name: plan.name,
            price: plan.price,
            stripe_price_id: plan.stripe_price_id || null,
            limits: Object.fromEntries(
                props.planCatalog.limits.map(({ key }) => [
                    key,
                    plan.unlimited[key] ? null : plan.limits[key],
                ]),
            ),
            features: plan.features,
        })),
    })).put('/admin/settings');
};
</script>

<template>
    <Head title="Platform settings" />
    <AdminLayout>
        <div class="mx-auto max-w-4xl px-4 py-8 sm:px-6">
            <PageHeader
                title="Platform settings"
                :description="settings.stripe_enabled
                    ? 'Configure trial, currency, plan pricing, limits, and feature access. Plan names and prices sync to Stripe automatically when you save.'
                    : 'Configure trial, currency, plan pricing, Stripe prices, limits, and feature access.'"
            />

            <form class="space-y-6" @submit.prevent="submit">
                <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                    <h2 class="font-semibold text-slate-900">Free trial</h2>
                    <p class="mt-1 text-sm text-slate-600">How long new workspaces can use the platform before choosing a paid plan.</p>

                    <div class="mt-5">
                        <label class="mb-1.5 block text-sm font-medium text-slate-700">Trial length (days)</label>
                        <input v-model.number="form.trial_days" type="number" min="1" max="365" required class="w-32 rounded-xl border border-slate-200 px-3.5 py-2.5 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20" />
                        <p v-if="form.errors.trial_days" class="mt-1.5 text-xs text-red-600">{{ form.errors.trial_days }}</p>
                    </div>
                </section>

                <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                    <h2 class="font-semibold text-slate-900">Currency</h2>
                    <div class="mt-5">
                        <label class="mb-1.5 block text-sm font-medium text-slate-700">Billing currency</label>
                        <select v-model="form.currency" required class="w-full rounded-xl border border-slate-200 px-3.5 py-2.5 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20 sm:max-w-md">
                            <option v-for="item in availableCurrencies" :key="item.code" :value="item.code">
                                {{ item.label }}
                            </option>
                        </select>
                        <p v-if="form.errors.currency" class="mt-1.5 text-xs text-red-600">{{ form.errors.currency }}</p>
                    </div>
                </section>

                <section class="space-y-4">
                    <div>
                        <h2 class="font-semibold text-slate-900">Plans</h2>
                        <p class="mt-1 text-sm text-slate-600">Define pricing, usage limits, and enabled features for each subscription tier.</p>
                    </div>

                    <div
                        v-for="(plan, index) in form.plans"
                        :key="plan.slug"
                        class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm"
                    >
                        <div class="mb-5 flex items-center justify-between border-b border-slate-100 pb-4">
                            <div>
                                <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">{{ plan.slug }}</p>
                                <p class="mt-0.5 text-sm text-slate-600">Public plan tier</p>
                            </div>
                        </div>

                        <div class="grid gap-4 sm:grid-cols-2">
                            <div>
                                <label class="mb-1.5 block text-sm font-medium text-slate-700">Display name</label>
                                <input v-model="plan.name" type="text" required class="w-full rounded-xl border border-slate-200 px-3.5 py-2.5 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20" />
                                <p v-if="form.errors[`plans.${index}.name`]" class="mt-1 text-xs text-red-600">{{ form.errors[`plans.${index}.name`] }}</p>
                            </div>
                            <div v-if="settings.stripe_enabled" class="sm:col-span-2 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3">
                                <p class="text-sm font-medium text-emerald-900">Stripe sync enabled</p>
                                <p class="mt-1 text-xs text-emerald-800">Saving updates the Stripe product and creates a new monthly price when the amount or currency changes.</p>
                                <div v-if="plan.stripe_price_id" class="mt-3 space-y-1 font-mono text-xs text-emerald-900">
                                    <p v-if="plan.stripe_product_id">Product: {{ plan.stripe_product_id }}</p>
                                    <p>Price: {{ plan.stripe_price_id }}</p>
                                </div>
                                <p v-else class="mt-2 text-xs text-emerald-800">Stripe IDs will appear here after the first save.</p>
                            </div>
                            <div v-else class="sm:col-span-2">
                                <label class="mb-1.5 block text-sm font-medium text-slate-700">Stripe price ID</label>
                                <input v-model="plan.stripe_price_id" type="text" placeholder="price_..." class="w-full rounded-xl border border-slate-200 px-3.5 py-2.5 font-mono text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20" />
                                <p class="mt-1 text-xs text-slate-500">Recurring monthly price from your Stripe dashboard.</p>
                            </div>
                            <div>
                                <label class="mb-1.5 block text-sm font-medium text-slate-700">Price ({{ selectedCurrency.code }} / month)</label>
                                <div class="flex overflow-hidden rounded-xl border border-slate-200 focus-within:border-blue-500 focus-within:ring-2 focus-within:ring-blue-500/20">
                                    <span class="flex items-center bg-slate-50 px-3 text-sm text-slate-500">{{ selectedCurrency.symbol }}</span>
                                    <input v-model.number="plan.price" type="number" min="0" max="99999" required class="min-w-0 flex-1 border-0 bg-transparent px-3 py-2.5 text-sm focus:outline-none" />
                                </div>
                                <p v-if="form.errors[`plans.${index}.price`]" class="mt-1 text-xs text-red-600">{{ form.errors[`plans.${index}.price`] }}</p>
                            </div>
                        </div>

                        <div class="mt-6">
                            <h3 class="text-sm font-semibold text-slate-900">Usage limits</h3>
                            <div class="mt-3 grid gap-4 sm:grid-cols-2">
                                <div
                                    v-for="limit in planCatalog.limits"
                                    :key="limit.key"
                                    class="rounded-xl border border-slate-200 p-4"
                                >
                                    <div class="flex items-start justify-between gap-3">
                                        <div>
                                            <p class="text-sm font-medium text-slate-900">{{ limit.label }}</p>
                                            <p v-if="limit.description" class="mt-0.5 text-xs text-slate-500">{{ limit.description }}</p>
                                        </div>
                                        <label v-if="limit.allow_unlimited" class="flex shrink-0 items-center gap-1.5 text-xs text-slate-600">
                                            <input v-model="plan.unlimited[limit.key]" type="checkbox" class="rounded border-slate-300" />
                                            Unlimited
                                        </label>
                                    </div>
                                    <input
                                        v-model.number="plan.limits[limit.key]"
                                        type="number"
                                        :min="limit.min"
                                        :max="limit.max"
                                        :disabled="plan.unlimited[limit.key]"
                                        class="mt-3 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm disabled:bg-slate-50 disabled:text-slate-400"
                                    />
                                    <p v-if="form.errors[`plans.${index}.limits.${limit.key}`]" class="mt-1 text-xs text-red-600">
                                        {{ form.errors[`plans.${index}.limits.${limit.key}`] }}
                                    </p>
                                </div>
                            </div>
                        </div>

                        <div class="mt-6">
                            <h3 class="text-sm font-semibold text-slate-900">Included features</h3>
                            <div class="mt-3 grid gap-2 sm:grid-cols-2">
                                <label
                                    v-for="feature in planCatalog.features"
                                    :key="feature.key"
                                    class="flex cursor-pointer items-center gap-3 rounded-xl border px-3 py-2.5 text-sm transition"
                                    :class="hasFeature(plan, feature.key) ? 'border-blue-500 bg-blue-50 text-blue-900' : 'border-slate-200 text-slate-700 hover:border-slate-300'"
                                >
                                    <input
                                        type="checkbox"
                                        class="rounded border-slate-300"
                                        :checked="hasFeature(plan, feature.key)"
                                        @change="toggleFeature(plan, feature.key)"
                                    />
                                    {{ feature.label }}
                                </label>
                            </div>
                        </div>
                    </div>
                </section>

                <button type="submit" class="rounded-xl bg-blue-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-blue-700" :disabled="form.processing">
                    Save settings
                </button>
            </form>
        </div>
    </AdminLayout>
</template>
