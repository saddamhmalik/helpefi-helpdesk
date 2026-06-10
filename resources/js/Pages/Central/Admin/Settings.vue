<script setup>
import { computed, ref } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import AdminLayout from '../../../Layouts/AdminLayout.vue';
import PageHeader from '../../../Components/PageHeader.vue';
import { useI18n } from 'vue-i18n';

const props = defineProps({
    settings: Object,
    availableCurrencies: { type: Array, default: () => [] },
    planCatalog: { type: Object, default: () => ({ limits: [], features: [] }) },
});

const { t } = useI18n();

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
        price: plan.price_monthly ?? plan.price,
        price_yearly: plan.price_yearly ?? (plan.price_monthly ?? plan.price) * 10,
        stripe_product_id: plan.stripe_product_id ?? '',
        stripe_price_id: plan.stripe_price_id_monthly ?? plan.stripe_price_id ?? '',
        stripe_price_id_yearly: plan.stripe_price_id_yearly ?? '',
        limits,
        unlimited,
        features: [...(plan.features ?? [])],
    };
};

const mapAddon = (addon) => ({
    key: addon.key,
    name: addon.name,
    description: addon.description ?? '',
    price_monthly: addon.price_monthly ?? 0,
    enabled: addon.enabled ?? true,
    stripe_price_id_monthly: addon.stripe_price_id_monthly ?? '',
});

const purging = ref(false);

const form = useForm({
    trial_days: props.settings.trial_days,
    tenant_purge_grace_days: props.settings.tenant_purge_grace_days ?? 15,
    tenant_purge_enabled: props.settings.tenant_purge_enabled ?? true,
    currency: props.settings.currency,
    plans: props.settings.plans.map(mapPlan),
    addons: (props.settings.addons ?? []).map(mapAddon),
});

const selectedCurrency = computed(() => (
    props.availableCurrencies.find((item) => item.code === form.currency)
    ?? { symbol: '$', code: 'USD', name: t('central.us_dollar') }
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

const runPurge = () => {
    if (!window.confirm('Delete all workspaces whose trial or paid access expired beyond the grace period? This drops their databases permanently.')) {
        return;
    }

    purging.value = true;

    router.post('/admin/settings/purge-expired-tenants', {}, {
        preserveScroll: true,
        onFinish: () => {
            purging.value = false;
        },
    });
};

const submit = () => {
    form.transform((data) => ({
        ...data,
        plans: data.plans.map((plan) => ({
            slug: plan.slug,
            name: plan.name,
            price: plan.price,
            price_yearly: plan.price_yearly,
            stripe_price_id: plan.stripe_price_id || null,
            stripe_price_id_yearly: plan.stripe_price_id_yearly || null,
            limits: Object.fromEntries(
                props.planCatalog.limits.map(({ key }) => [
                    key,
                    plan.unlimited[key] ? null : plan.limits[key],
                ]),
            ),
            features: plan.features,
        })),
        addons: data.addons.map((addon) => ({
            key: addon.key,
            name: addon.name,
            description: addon.description || null,
            price_monthly: addon.price_monthly,
            enabled: addon.enabled,
            stripe_price_id_monthly: addon.stripe_price_id_monthly || null,
        })),
    })).put('/admin/settings');
};
</script>

<template>
    <Head :title="$t('central.platform_settings')" />
    <AdminLayout>
        <div class="mx-auto max-w-4xl px-4 py-8 sm:px-6">
            <PageHeader
                :title="$t('central.platform_settings')"
                :description="settings.stripe_enabled
                    ? 'Configure trial, currency, plan pricing, limits, and feature access. Plan names and prices sync to Stripe automatically when you save.'
                    : 'Configure trial, currency, plan pricing, Stripe prices, limits, and feature access.'"
            />

            <form class="space-y-6" @submit.prevent="submit">
                <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                    <h2 class="font-semibold text-slate-900">{{ $t('central.free_trial') }}</h2>
                    <p class="mt-1 text-sm text-slate-600">{{ $t('central.how_long_new_workspaces_can_use_the_platform_before_choosing_a_paid_pl') }}</p>

                    <div class="mt-5">
                        <label class="mb-1.5 block text-sm font-medium text-slate-700">{{ $t('central.trial_length_days') }}</label>
                        <input v-model.number="form.trial_days" type="number" min="1" max="365" required class="w-32 rounded-xl border border-slate-200 px-3.5 py-2.5 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20" />
                        <p v-if="form.errors.trial_days" class="mt-1.5 text-xs text-red-600">{{ form.errors.trial_days }}</p>
                    </div>
                </section>

                <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                    <h2 class="font-semibold text-slate-900">{{ $t('central.expired_workspace_cleanup') }}</h2>
                    <p class="mt-1 text-sm text-slate-600">
                        Automatically delete workspaces after their trial or paid access ends and the grace period passes.
                        The daily scheduler runs <code class="rounded bg-slate-100 px-1 py-0.5 text-xs">tenants:purge-expired</code>.
                    </p>

                    <div class="mt-5 grid gap-5 sm:grid-cols-2">
                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-slate-700">{{ $t('central.grace_period_after_expiry_days') }}</label>
                            <input v-model.number="form.tenant_purge_grace_days" type="number" min="1" max="365" required class="w-32 rounded-xl border border-slate-200 px-3.5 py-2.5 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20" />
                            <p v-if="form.errors.tenant_purge_grace_days" class="mt-1.5 text-xs text-red-600">{{ form.errors.tenant_purge_grace_days }}</p>
                        </div>
                        <div class="flex items-end">
                            <label class="flex items-center gap-2 text-sm text-slate-700">
                                <input v-model="form.tenant_purge_enabled" type="checkbox" class="rounded border-slate-300 text-blue-600 focus:ring-blue-500" />
                                Enable automatic daily purge
                            </label>
                        </div>
                    </div>

                    <div class="mt-5 flex flex-col gap-3 border-t border-slate-100 pt-5 sm:flex-row sm:items-center sm:justify-between">
                        <p class="text-sm text-slate-600">{{ $t('central.run_the_purge_job_immediately_for_all_eligible_workspaces') }}</p>
                        <button
                            type="button"
                            class="rounded-xl bg-red-600 px-4 py-2 text-sm font-semibold text-white hover:bg-red-700 disabled:opacity-60"
                            :disabled="purging"
                            @click="runPurge"
                        >
                            {{ purging ? 'Purging…' : 'Run purge now' }}
                        </button>
                    </div>
                </section>

                <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                    <h2 class="font-semibold text-slate-900">{{ $t('central.currency') }}</h2>
                    <div class="mt-5">
                        <label class="mb-1.5 block text-sm font-medium text-slate-700">{{ $t('central.billing_currency') }}</label>
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
                        <h2 class="font-semibold text-slate-900">{{ $t('central.plans') }}</h2>
                        <p class="mt-1 text-sm text-slate-600">{{ $t('central.define_monthly_and_yearly_pricing_usage_limits_and_enabled_features_fo') }}</p>
                    </div>

                    <div
                        v-for="(plan, index) in form.plans"
                        :key="plan.slug"
                        class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm"
                    >
                        <div class="mb-5 flex items-center justify-between border-b border-slate-100 pb-4">
                            <div>
                                <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">{{ plan.slug }}</p>
                                <p class="mt-0.5 text-sm text-slate-600">{{ $t('central.public_plan_tier') }}</p>
                            </div>
                        </div>

                        <div class="grid gap-4 sm:grid-cols-2">
                            <div>
                                <label class="mb-1.5 block text-sm font-medium text-slate-700">{{ $t('central.display_name') }}</label>
                                <input v-model="plan.name" type="text" required class="w-full rounded-xl border border-slate-200 px-3.5 py-2.5 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20" />
                                <p v-if="form.errors[`plans.${index}.name`]" class="mt-1 text-xs text-red-600">{{ form.errors[`plans.${index}.name`] }}</p>
                            </div>
                            <div v-if="settings.stripe_enabled" class="sm:col-span-2 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3">
                                <p class="text-sm font-medium text-emerald-900">{{ $t('central.stripe_sync_enabled') }}</p>
                                <p class="mt-1 text-xs text-emerald-800">{{ $t('central.saving_updates_the_stripe_product_and_creates_new_monthly_or_yearly_pr') }}</p>
                                <div v-if="plan.stripe_price_id || plan.stripe_price_id_yearly" class="mt-3 space-y-1 font-mono text-xs text-emerald-900">
                                    <p v-if="plan.stripe_product_id">Product: {{ plan.stripe_product_id }}</p>
                                    <p v-if="plan.stripe_price_id">Monthly price: {{ plan.stripe_price_id }}</p>
                                    <p v-if="plan.stripe_price_id_yearly">Yearly price: {{ plan.stripe_price_id_yearly }}</p>
                                </div>
                                <p v-else class="mt-2 text-xs text-emerald-800">{{ $t('central.stripe_ids_will_appear_here_after_the_first_save') }}</p>
                            </div>
                            <template v-else>
                                <div>
                                    <label class="mb-1.5 block text-sm font-medium text-slate-700">{{ $t('central.stripe_monthly_price_id') }}</label>
                                    <input v-model="plan.stripe_price_id" type="text" :placeholder="$t('central.price')" class="w-full rounded-xl border border-slate-200 px-3.5 py-2.5 font-mono text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20" />
                                    <p class="mt-1 text-xs text-slate-500">{{ $t('central.recurring_monthly_price_from_your_stripe_dashboard') }}</p>
                                </div>
                                <div>
                                    <label class="mb-1.5 block text-sm font-medium text-slate-700">{{ $t('central.stripe_yearly_price_id') }}</label>
                                    <input v-model="plan.stripe_price_id_yearly" type="text" :placeholder="$t('central.price')" class="w-full rounded-xl border border-slate-200 px-3.5 py-2.5 font-mono text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20" />
                                    <p class="mt-1 text-xs text-slate-500">{{ $t('central.recurring_yearly_price_from_your_stripe_dashboard') }}</p>
                                </div>
                            </template>
                            <div>
                                <label class="mb-1.5 block text-sm font-medium text-slate-700">Price ({{ selectedCurrency.code }} / month)</label>
                                <div class="flex overflow-hidden rounded-xl border border-slate-200 focus-within:border-blue-500 focus-within:ring-2 focus-within:ring-blue-500/20">
                                    <span class="flex items-center bg-slate-50 px-3 text-sm text-slate-500">{{ selectedCurrency.symbol }}</span>
                                    <input v-model.number="plan.price" type="number" min="0" max="99999" required class="min-w-0 flex-1 border-0 bg-transparent px-3 py-2.5 text-sm focus:outline-none" />
                                </div>
                                <p v-if="form.errors[`plans.${index}.price`]" class="mt-1 text-xs text-red-600">{{ form.errors[`plans.${index}.price`] }}</p>
                            </div>
                            <div>
                                <label class="mb-1.5 block text-sm font-medium text-slate-700">Price ({{ selectedCurrency.code }} / year)</label>
                                <div class="flex overflow-hidden rounded-xl border border-slate-200 focus-within:border-blue-500 focus-within:ring-2 focus-within:ring-blue-500/20">
                                    <span class="flex items-center bg-slate-50 px-3 text-sm text-slate-500">{{ selectedCurrency.symbol }}</span>
                                    <input v-model.number="plan.price_yearly" type="number" min="0" max="999999" required class="min-w-0 flex-1 border-0 bg-transparent px-3 py-2.5 text-sm focus:outline-none" />
                                </div>
                                <p v-if="form.errors[`plans.${index}.price_yearly`]" class="mt-1 text-xs text-red-600">{{ form.errors[`plans.${index}.price_yearly`] }}</p>
                            </div>
                        </div>

                        <div class="mt-6">
                            <h3 class="text-sm font-semibold text-slate-900">{{ $t('central.usage_limits') }}</h3>
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
                            <h3 class="text-sm font-semibold text-slate-900">{{ $t('central.included_features') }}</h3>
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

                <section class="space-y-4">
                    <div>
                        <h2 class="font-semibold text-slate-900">Paid add-ons</h2>
                        <p class="mt-1 text-sm text-slate-600">Monthly add-ons tenants purchase on top of their base plan. Prices sync to Stripe when enabled.</p>
                    </div>

                    <div
                        v-for="(addon, index) in form.addons"
                        :key="addon.key"
                        class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm"
                    >
                        <div class="mb-4 flex items-center justify-between border-b border-slate-100 pb-4">
                            <div>
                                <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">{{ addon.key }}</p>
                                <p class="mt-0.5 text-sm text-slate-600">Monthly add-on</p>
                            </div>
                            <label class="flex items-center gap-2 text-sm text-slate-700">
                                <input v-model="addon.enabled" type="checkbox" class="rounded border-slate-300 text-blue-600" />
                                Available for purchase
                            </label>
                        </div>

                        <div class="grid gap-4 sm:grid-cols-2">
                            <div>
                                <label class="mb-1.5 block text-sm font-medium text-slate-700">Display name</label>
                                <input v-model="addon.name" type="text" required class="w-full rounded-xl border border-slate-200 px-3.5 py-2.5 text-sm" />
                            </div>
                            <div>
                                <label class="mb-1.5 block text-sm font-medium text-slate-700">Price ({{ selectedCurrency.code }} / month)</label>
                                <div class="flex overflow-hidden rounded-xl border border-slate-200">
                                    <span class="flex items-center bg-slate-50 px-3 text-sm text-slate-500">{{ selectedCurrency.symbol }}</span>
                                    <input v-model.number="addon.price_monthly" type="number" min="0" max="99999" required class="min-w-0 flex-1 border-0 bg-transparent px-3 py-2.5 text-sm" />
                                </div>
                            </div>
                            <div v-if="!settings.stripe_enabled" class="sm:col-span-2">
                                <label class="mb-1.5 block text-sm font-medium text-slate-700">Stripe monthly price ID</label>
                                <input v-model="addon.stripe_price_id_monthly" type="text" class="w-full rounded-xl border border-slate-200 px-3.5 py-2.5 font-mono text-sm" />
                            </div>
                            <div v-else-if="addon.stripe_price_id_monthly" class="sm:col-span-2 font-mono text-xs text-emerald-800">
                                Monthly price: {{ addon.stripe_price_id_monthly }}
                            </div>
                            <div class="sm:col-span-2">
                                <label class="mb-1.5 block text-sm font-medium text-slate-700">Description</label>
                                <textarea v-model="addon.description" rows="2" class="w-full rounded-xl border border-slate-200 px-3.5 py-2.5 text-sm" />
                            </div>
                        </div>
                        <p v-if="form.errors[`addons.${index}.price_monthly`]" class="mt-2 text-xs text-red-600">{{ form.errors[`addons.${index}.price_monthly`] }}</p>
                    </div>
                </section>

                <button type="submit" class="rounded-xl bg-blue-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-blue-700" :disabled="form.processing">{{ $t('central.save_settings') }}</button>
            </form>
        </div>
    </AdminLayout>
</template>
