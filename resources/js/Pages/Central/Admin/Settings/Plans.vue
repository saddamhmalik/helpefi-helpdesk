<script setup>
import { computed } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import AdminLayout from '../../../../Layouts/AdminLayout.vue';
import PageHeader from '../../../../Components/PageHeader.vue';
import SettingsTabs from '../../../../Components/Platform/SettingsTabs.vue';

const props = defineProps({
    settings: Object,
    planCatalog: { type: Object, default: () => ({ limits: [], features: [] }) },
    defaultSlugs: { type: Array, default: () => [] },
});

const selectedCurrency = computed(() => (
    typeof props.settings.currency === 'object'
        ? props.settings.currency
        : { symbol: '$', code: 'USD' }
));

const indiaEnabled = computed(() => props.settings.india_pricing ?? false);

const indiaCurrency = computed(() => (
    typeof props.settings.india_currency === 'object'
        ? props.settings.india_currency
        : { symbol: '₹', code: 'INR' }
));

let uid = 0;
const nextUid = () => (uid += 1);

const slugify = (value) => (value ?? '')
    .toString()
    .toLowerCase()
    .trim()
    .replace(/[^a-z0-9]+/g, '_')
    .replace(/^_+|_+$/g, '')
    .replace(/^([0-9])/, 'p$1')
    .slice(0, 40);

const mapPlan = (plan) => {
    const unlimited = {};
    const limits = {};

    props.planCatalog.limits.forEach(({ key }) => {
        unlimited[key] = plan.limits?.[key] === null;
        limits[key] = plan.limits?.[key] ?? 1;
    });

    return {
        _uid: nextUid(),
        is_default: props.defaultSlugs.includes(plan.slug),
        slug: plan.slug,
        name: plan.name,
        custom_pricing: plan.custom_pricing ?? false,
        price: plan.price_monthly ?? plan.price,
        price_yearly: plan.price_yearly ?? (plan.price_monthly ?? plan.price) * 10,
        price_india: plan.price_monthly_india ?? 0,
        price_yearly_india: plan.price_yearly_india ?? 0,
        razorpay_plan_id: plan.razorpay_plan_id_monthly ?? plan.razorpay_plan_id ?? '',
        razorpay_plan_id_yearly: plan.razorpay_plan_id_yearly ?? '',
        razorpay_plan_id_monthly_india: plan.razorpay_plan_id_monthly_india ?? '',
        razorpay_plan_id_yearly_india: plan.razorpay_plan_id_yearly_india ?? '',
        limits,
        unlimited,
        features: [...(plan.features ?? [])],
    };
};

const blankLimits = () => Object.fromEntries(props.planCatalog.limits.map(({ key }) => [key, 1]));
const blankUnlimited = () => Object.fromEntries(props.planCatalog.limits.map(({ key }) => [key, false]));

const addPlan = () => {
    form.plans.push({
        _uid: nextUid(),
        is_default: false,
        slug: '',
        name: '',
        custom_pricing: false,
        price: 0,
        price_yearly: 0,
        price_india: 0,
        price_yearly_india: 0,
        razorpay_plan_id: '',
        razorpay_plan_id_yearly: '',
        razorpay_plan_id_monthly_india: '',
        razorpay_plan_id_yearly_india: '',
        limits: blankLimits(),
        unlimited: blankUnlimited(),
        features: [],
    });
};

const removePlan = (uidToRemove) => {
    const index = form.plans.findIndex((plan) => plan._uid === uidToRemove);

    if (index !== -1) {
        form.plans.splice(index, 1);
    }
};

const onSlugInput = (plan) => {
    plan.slug = slugify(plan.slug);
};

const onNameBlur = (plan) => {
    if (!plan.is_default && !plan.slug && plan.name) {
        plan.slug = slugify(plan.name);
    }
};

const form = useForm({
    plans: props.settings.plans.map(mapPlan),
});

const toggleFeature = (plan, featureKey) => {
    const index = plan.features.indexOf(featureKey);

    if (index === -1) {
        plan.features.push(featureKey);
    } else {
        plan.features.splice(index, 1);
    }
};

const hasFeature = (plan, featureKey) => plan.features.includes(featureKey);

const errorList = computed(() => Object.entries(form.errors).map(([key, message]) => {
    const match = key.match(/^plans\.(\d+)\./);

    if (!match) {
        return message;
    }

    const plan = form.plans[Number(match[1])];
    const label = plan?.name || plan?.slug || `#${Number(match[1]) + 1}`;

    return `${label}: ${message}`;
}));

const submit = () => {
    form.transform((data) => ({
        plans: data.plans.map((plan) => ({
            slug: plan.slug,
            name: plan.name,
            custom_pricing: plan.custom_pricing,
            price: plan.price,
            price_monthly: plan.price,
            price_yearly: plan.price_yearly,
            price_india: plan.price_india,
            price_yearly_india: plan.price_yearly_india,
            razorpay_plan_id: plan.razorpay_plan_id || null,
            razorpay_plan_id_yearly: plan.razorpay_plan_id_yearly || null,
            razorpay_plan_id_monthly_india: plan.razorpay_plan_id_monthly_india || null,
            razorpay_plan_id_yearly_india: plan.razorpay_plan_id_yearly_india || null,
            limits: Object.fromEntries(
                props.planCatalog.limits.map(({ key }) => [
                    key,
                    plan.unlimited[key] ? null : plan.limits[key],
                ]),
            ),
            features: plan.features,
        })),
    })).put('/admin/settings', {
        preserveScroll: true,
        onError: () => window.scrollTo({ top: 0, behavior: 'smooth' }),
    });
};
</script>

<template>
    <Head :title="$t('central.settings_tab_plans')" />
    <AdminLayout>
        <div class="mx-auto max-w-4xl px-4 py-8 sm:px-6">
            <PageHeader
                :title="$t('central.platform_settings')"
                :description="$t('central.define_monthly_and_yearly_pricing_usage_limits_and_enabled_features_fo')"
            />

            <SettingsTabs />

            <div v-if="errorList.length" class="mt-4 rounded-2xl border border-red-200 dark:border-red-900/60 bg-red-50 dark:bg-red-950/40 p-4">
                <p class="text-sm font-semibold text-red-800 dark:text-red-300">{{ $t('central.fix_errors_to_save') }}</p>
                <ul class="mt-2 list-disc space-y-1 pl-5 text-sm text-red-700 dark:text-red-300">
                    <li v-for="(message, idx) in errorList" :key="idx">{{ message }}</li>
                </ul>
            </div>

            <form class="space-y-4" @submit.prevent="submit">
                <div
                    v-for="(plan, index) in form.plans"
                    :key="plan._uid"
                    class="rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 p-6 shadow-sm"
                >
                    <div class="mb-5 flex items-start justify-between gap-4 border-b border-slate-100 dark:border-slate-800 pb-4">
                        <div v-if="plan.is_default">
                            <p class="text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">{{ plan.slug }}</p>
                            <p class="mt-0.5 text-sm text-slate-600 dark:text-slate-400">{{ $t('central.public_plan_tier') }}</p>
                        </div>
                        <div v-else class="min-w-0 flex-1">
                            <label class="mb-1.5 block text-xs font-medium text-slate-700 dark:text-slate-300">{{ $t('central.plan_identifier') }}</label>
                            <input
                                v-model="plan.slug"
                                type="text"
                                required
                                :placeholder="$t('central.plan_identifier_placeholder')"
                                class="w-full max-w-xs rounded-xl border border-slate-200 dark:border-slate-800 px-3.5 py-2 font-mono text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20"
                                @input="onSlugInput(plan)"
                            />
                            <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ $t('central.plan_identifier_hint') }}</p>
                            <p v-if="form.errors[`plans.${index}.slug`]" class="mt-1 text-xs text-red-600">{{ form.errors[`plans.${index}.slug`] }}</p>
                        </div>
                        <button
                            v-if="!plan.is_default"
                            type="button"
                            class="shrink-0 rounded-lg border border-red-200 dark:border-red-900/60 px-3 py-1.5 text-xs font-semibold text-red-600 hover:bg-red-50 dark:hover:bg-red-950/40"
                            @click="removePlan(plan._uid)"
                        >{{ $t('central.remove') }}</button>
                    </div>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-slate-700 dark:text-slate-300">{{ $t('central.display_name') }}</label>
                            <input v-model="plan.name" type="text" required class="w-full rounded-xl border border-slate-200 dark:border-slate-800 px-3.5 py-2.5 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20" @blur="onNameBlur(plan)" />
                            <p v-if="form.errors[`plans.${index}.name`]" class="mt-1 text-xs text-red-600">{{ form.errors[`plans.${index}.name`] }}</p>
                        </div>
                        <label class="sm:col-span-2 flex cursor-pointer items-start gap-3 rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-950 px-4 py-3.5">
                            <input v-model="plan.custom_pricing" type="checkbox" class="mt-0.5 h-4 w-4 rounded border-slate-300 text-blue-600 focus:ring-blue-500" />
                            <span>
                                <span class="block text-sm font-medium text-slate-900 dark:text-slate-100">{{ $t('central.custom_pricing') }}</span>
                                <span class="mt-0.5 block text-xs text-slate-600 dark:text-slate-400">{{ $t('central.custom_pricing_hint') }}</span>
                            </span>
                        </label>
                        <div v-if="plan.custom_pricing" class="sm:col-span-2 rounded-xl border border-dashed border-slate-300 dark:border-slate-700 px-4 py-3 text-sm text-slate-600 dark:text-slate-400">
                            {{ $t('central.custom_pricing_note') }}
                        </div>
                        <template v-else>
                        <div v-if="settings.razorpay_enabled" class="sm:col-span-2 rounded-xl border border-emerald-200 dark:border-emerald-900/60 bg-emerald-50 dark:bg-emerald-950/40 px-4 py-3">
                            <p class="text-sm font-medium text-emerald-900">{{ $t('central.stripe_sync_enabled') }}</p>
                            <p class="mt-1 text-xs text-emerald-800 dark:text-emerald-200">{{ $t('central.saving_updates_the_stripe_product_and_creates_new_monthly_or_yearly_pr') }}</p>
                            <div v-if="plan.razorpay_plan_id || plan.razorpay_plan_id_yearly" class="mt-3 space-y-1 font-mono text-xs text-emerald-900">
                                <p v-if="plan.razorpay_plan_id">Monthly plan: {{ plan.razorpay_plan_id }}</p>
                                <p v-if="plan.razorpay_plan_id_yearly">Yearly plan: {{ plan.razorpay_plan_id_yearly }}</p>
                            </div>
                            <p v-else class="mt-2 text-xs text-emerald-800 dark:text-emerald-200">{{ $t('central.stripe_ids_will_appear_here_after_the_first_save') }}</p>
                        </div>
                        <template v-else>
                            <div>
                                <label class="mb-1.5 block text-sm font-medium text-slate-700 dark:text-slate-300">{{ $t('central.stripe_monthly_price_id') }}</label>
                                <input v-model="plan.razorpay_plan_id" type="text" :placeholder="$t('central.price')" class="w-full rounded-xl border border-slate-200 dark:border-slate-800 px-3.5 py-2.5 font-mono text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20" />
                                <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ $t('central.recurring_monthly_price_from_your_stripe_dashboard') }}</p>
                            </div>
                            <div>
                                <label class="mb-1.5 block text-sm font-medium text-slate-700 dark:text-slate-300">{{ $t('central.stripe_yearly_price_id') }}</label>
                                <input v-model="plan.razorpay_plan_id_yearly" type="text" :placeholder="$t('central.price')" class="w-full rounded-xl border border-slate-200 dark:border-slate-800 px-3.5 py-2.5 font-mono text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20" />
                                <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ $t('central.recurring_yearly_price_from_your_stripe_dashboard') }}</p>
                            </div>
                        </template>
                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-slate-700 dark:text-slate-300">Price ({{ selectedCurrency.code }} / month)</label>
                            <div class="flex overflow-hidden rounded-xl border border-slate-200 dark:border-slate-800 focus-within:border-blue-500 focus-within:ring-2 focus-within:ring-blue-500/20">
                                <span class="flex items-center bg-slate-50 dark:bg-slate-950 px-3 text-sm text-slate-500 dark:text-slate-400">{{ selectedCurrency.symbol }}</span>
                                <input v-model.number="plan.price" type="number" min="0" max="99999" required class="min-w-0 flex-1 border-0 bg-transparent px-3 py-2.5 text-sm focus:outline-none" />
                            </div>
                            <p v-if="form.errors[`plans.${index}.price`]" class="mt-1 text-xs text-red-600">{{ form.errors[`plans.${index}.price`] }}</p>
                        </div>
                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-slate-700 dark:text-slate-300">Price ({{ selectedCurrency.code }} / year)</label>
                            <div class="flex overflow-hidden rounded-xl border border-slate-200 dark:border-slate-800 focus-within:border-blue-500 focus-within:ring-2 focus-within:ring-blue-500/20">
                                <span class="flex items-center bg-slate-50 dark:bg-slate-950 px-3 text-sm text-slate-500 dark:text-slate-400">{{ selectedCurrency.symbol }}</span>
                                <input v-model.number="plan.price_yearly" type="number" min="0" max="999999" required class="min-w-0 flex-1 border-0 bg-transparent px-3 py-2.5 text-sm focus:outline-none" />
                            </div>
                            <p v-if="form.errors[`plans.${index}.price_yearly`]" class="mt-1 text-xs text-red-600">{{ form.errors[`plans.${index}.price_yearly`] }}</p>
                        </div>

                        <template v-if="indiaEnabled">
                            <div class="sm:col-span-2 border-t border-dashed border-slate-200 dark:border-slate-800 pt-4">
                                <p class="text-xs font-semibold uppercase tracking-wider text-violet-600 dark:text-violet-400">{{ $t('central.india_pricing_section') }}</p>
                            </div>
                            <div>
                                <label class="mb-1.5 block text-sm font-medium text-slate-700 dark:text-slate-300">{{ $t('central.india_monthly_price') }}</label>
                                <div class="flex overflow-hidden rounded-xl border border-slate-200 dark:border-slate-800 focus-within:border-blue-500 focus-within:ring-2 focus-within:ring-blue-500/20">
                                    <span class="flex items-center bg-slate-50 dark:bg-slate-950 px-3 text-sm text-slate-500 dark:text-slate-400">{{ indiaCurrency.symbol }}</span>
                                    <input v-model.number="plan.price_india" type="number" min="0" max="99999" class="min-w-0 flex-1 border-0 bg-transparent px-3 py-2.5 text-sm focus:outline-none" />
                                </div>
                                <p v-if="form.errors[`plans.${index}.price_india`]" class="mt-1 text-xs text-red-600">{{ form.errors[`plans.${index}.price_india`] }}</p>
                            </div>
                            <div>
                                <label class="mb-1.5 block text-sm font-medium text-slate-700 dark:text-slate-300">{{ $t('central.india_yearly_price') }}</label>
                                <div class="flex overflow-hidden rounded-xl border border-slate-200 dark:border-slate-800 focus-within:border-blue-500 focus-within:ring-2 focus-within:ring-blue-500/20">
                                    <span class="flex items-center bg-slate-50 dark:bg-slate-950 px-3 text-sm text-slate-500 dark:text-slate-400">{{ indiaCurrency.symbol }}</span>
                                    <input v-model.number="plan.price_yearly_india" type="number" min="0" max="999999" class="min-w-0 flex-1 border-0 bg-transparent px-3 py-2.5 text-sm focus:outline-none" />
                                </div>
                                <p v-if="form.errors[`plans.${index}.price_yearly_india`]" class="mt-1 text-xs text-red-600">{{ form.errors[`plans.${index}.price_yearly_india`] }}</p>
                            </div>
                            <div v-if="settings.razorpay_enabled && (plan.razorpay_plan_id_monthly_india || plan.razorpay_plan_id_yearly_india)" class="sm:col-span-2 space-y-1 font-mono text-xs text-violet-700 dark:text-violet-300">
                                <p v-if="plan.razorpay_plan_id_monthly_india">{{ indiaCurrency.code }} monthly plan: {{ plan.razorpay_plan_id_monthly_india }}</p>
                                <p v-if="plan.razorpay_plan_id_yearly_india">{{ indiaCurrency.code }} yearly plan: {{ plan.razorpay_plan_id_yearly_india }}</p>
                            </div>
                            <template v-else-if="!settings.razorpay_enabled">
                                <div>
                                    <label class="mb-1.5 block text-sm font-medium text-slate-700 dark:text-slate-300">Razorpay {{ indiaCurrency.code }} monthly ID</label>
                                    <input v-model="plan.razorpay_plan_id_monthly_india" type="text" class="w-full rounded-xl border border-slate-200 dark:border-slate-800 px-3.5 py-2.5 font-mono text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20" />
                                </div>
                                <div>
                                    <label class="mb-1.5 block text-sm font-medium text-slate-700 dark:text-slate-300">Razorpay {{ indiaCurrency.code }} yearly ID</label>
                                    <input v-model="plan.razorpay_plan_id_yearly_india" type="text" class="w-full rounded-xl border border-slate-200 dark:border-slate-800 px-3.5 py-2.5 font-mono text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20" />
                                </div>
                            </template>
                        </template>
                        </template>
                    </div>

                    <div class="mt-6">
                        <h3 class="text-sm font-semibold text-slate-900 dark:text-slate-100">{{ $t('central.usage_limits') }}</h3>
                        <div class="mt-3 grid gap-4 sm:grid-cols-2">
                            <div
                                v-for="limit in planCatalog.limits"
                                :key="limit.key"
                                class="rounded-xl border border-slate-200 dark:border-slate-800 p-4"
                            >
                                <div class="flex items-start justify-between gap-3">
                                    <div>
                                        <p class="text-sm font-medium text-slate-900 dark:text-slate-100">{{ limit.label }}</p>
                                        <p v-if="limit.description" class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">{{ limit.description }}</p>
                                    </div>
                                    <label v-if="limit.allow_unlimited" class="flex shrink-0 items-center gap-1.5 text-xs text-slate-600 dark:text-slate-400">
                                        <input v-model="plan.unlimited[limit.key]" type="checkbox" class="rounded border-slate-300 dark:border-slate-700" />
                                        Unlimited
                                    </label>
                                </div>
                                <input
                                    v-model.number="plan.limits[limit.key]"
                                    type="number"
                                    :min="limit.min"
                                    :max="limit.max"
                                    :disabled="plan.unlimited[limit.key]"
                                    class="mt-3 w-full rounded-lg border border-slate-200 dark:border-slate-800 px-3 py-2 text-sm disabled:bg-slate-50 dark:bg-slate-950 disabled:text-slate-400 dark:text-slate-500"
                                />
                                <p v-if="form.errors[`plans.${index}.limits.${limit.key}`]" class="mt-1 text-xs text-red-600">
                                    {{ form.errors[`plans.${index}.limits.${limit.key}`] }}
                                </p>
                            </div>
                        </div>
                    </div>

                    <div class="mt-6">
                        <h3 class="text-sm font-semibold text-slate-900 dark:text-slate-100">{{ $t('central.included_features') }}</h3>
                        <div class="mt-3 grid gap-2 sm:grid-cols-2">
                            <label
                                v-for="feature in planCatalog.features"
                                :key="feature.key"
                                class="flex cursor-pointer items-center gap-3 rounded-xl border px-3 py-2.5 text-sm transition"
                                :class="hasFeature(plan, feature.key) ? 'border-blue-500 bg-blue-50 dark:bg-blue-950/40 text-blue-900' : 'border-slate-200 dark:border-slate-800 text-slate-700 dark:text-slate-300 hover:border-slate-300 dark:hover:border-slate-600 dark:border-slate-700'"
                            >
                                <input
                                    type="checkbox"
                                    class="rounded border-slate-300 dark:border-slate-700"
                                    :checked="hasFeature(plan, feature.key)"
                                    @change="toggleFeature(plan, feature.key)"
                                />
                                {{ feature.label }}
                            </label>
                        </div>
                    </div>
                </div>

                <button
                    type="button"
                    class="flex w-full items-center justify-center gap-2 rounded-2xl border border-dashed border-slate-300 dark:border-slate-700 px-4 py-4 text-sm font-semibold text-slate-600 dark:text-slate-300 transition hover:border-blue-400 hover:text-blue-600 dark:hover:border-blue-500 dark:hover:text-blue-400"
                    @click="addPlan"
                >
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 5v14M5 12h14" />
                    </svg>
                    {{ $t('central.add_plan') }}
                </button>

                <p v-if="form.errors.plans" class="text-sm text-red-600">{{ form.errors.plans }}</p>

                <button type="submit" class="rounded-xl bg-blue-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-blue-700 disabled:opacity-60" :disabled="form.processing">{{ $t('central.save_settings') }}</button>
            </form>
        </div>
    </AdminLayout>
</template>
