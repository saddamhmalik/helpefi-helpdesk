<script setup>
import { computed } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import AdminLayout from '../../../../Layouts/AdminLayout.vue';
import PageHeader from '../../../../Components/PageHeader.vue';
import SettingsTabs from '../../../../Components/Platform/SettingsTabs.vue';
import { useCurrency } from '../../../../composables/useCurrency.js';

const props = defineProps({
    settings: Object,
});

const selectedCurrency = computed(() => (
    typeof props.settings.currency === 'object'
        ? props.settings.currency
        : { symbol: '$', code: 'USD' }
));

const indiaEnabled = computed(() => props.settings.india_pricing_effective ?? props.settings.india_pricing ?? false);

const indiaCurrency = computed(() => (
    typeof props.settings.india_currency === 'object'
        ? props.settings.india_currency
        : { symbol: '₹', code: 'INR' }
));

const { formatPrice } = useCurrency(() => selectedCurrency.value);
const { formatPrice: formatIndiaPrice } = useCurrency(() => indiaCurrency.value);

const mapAddon = (addon) => ({
    key: addon.key,
    name: addon.name,
    description: addon.description ?? '',
    price_monthly: addon.price_monthly ?? 0,
    price_india: addon.price_monthly_india ?? 0,
    enabled: addon.enabled ?? true,
    razorpay_plan_id_monthly: addon.razorpay_plan_id_monthly ?? '',
    razorpay_plan_id_monthly_india: addon.razorpay_plan_id_monthly_india ?? '',
});

const form = useForm({
    addons: (props.settings.addons ?? []).map(mapAddon),
});

const submit = () => {
    form.transform((data) => ({
        addons: data.addons.map((addon) => ({
            key: addon.key,
            name: addon.name,
            description: addon.description || null,
            price_monthly: addon.price_monthly,
            price_india: addon.price_india,
            enabled: addon.enabled,
            razorpay_plan_id_monthly: addon.razorpay_plan_id_monthly || null,
            razorpay_plan_id_monthly_india: addon.razorpay_plan_id_monthly_india || null,
        })),
    })).put('/admin/settings', { preserveScroll: true });
};
</script>

<template>
    <Head :title="$t('central.settings_tab_addons')" />
    <AdminLayout>
        <div class="mx-auto max-w-4xl px-4 py-8 sm:px-6">
            <PageHeader
                :title="$t('central.platform_settings')"
                :description="$t('central.settings_addons_description')"
            />

            <SettingsTabs />

            <form class="space-y-4" @submit.prevent="submit">
                <p class="text-sm text-slate-600 dark:text-slate-400">
                    Monthly add-ons tenants purchase on top of their base plan. Base prices use {{ selectedCurrency.code }}.
                    <span v-if="indiaEnabled"> India visitors see separate INR prices when India pricing is enabled.</span>
                    Prices sync to Razorpay when enabled.
                </p>

                <div
                    v-for="(addon, index) in form.addons"
                    :key="addon.key"
                    class="rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 p-6 shadow-sm"
                >
                    <div class="mb-4 flex items-center justify-between border-b border-slate-100 dark:border-slate-800 pb-4">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">{{ addon.key }}</p>
                            <p class="mt-0.5 text-sm text-slate-600 dark:text-slate-400">Monthly add-on</p>
                        </div>
                        <label class="flex items-center gap-2 text-sm text-slate-700 dark:text-slate-300">
                            <input v-model="addon.enabled" type="checkbox" class="rounded border-slate-300 dark:border-slate-700 text-blue-600" />
                            Available for purchase
                        </label>
                    </div>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-slate-700 dark:text-slate-300">Display name</label>
                            <input v-model="addon.name" type="text" required class="w-full rounded-xl border border-slate-200 dark:border-slate-800 px-3.5 py-2.5 text-sm" />
                        </div>
                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-slate-700 dark:text-slate-300">Price ({{ selectedCurrency.code }} / month)</label>
                            <div class="flex overflow-hidden rounded-xl border border-slate-200 dark:border-slate-800">
                                <span class="flex items-center bg-slate-50 dark:bg-slate-950 px-3 text-sm text-slate-500 dark:text-slate-400">{{ selectedCurrency.symbol }}</span>
                                <input v-model.number="addon.price_monthly" type="number" min="0" max="99999" required class="min-w-0 flex-1 border-0 bg-transparent px-3 py-2.5 text-sm" />
                            </div>
                            <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Tenants see {{ formatPrice(addon.price_monthly) }}/mo</p>
                        </div>

                        <template v-if="indiaEnabled">
                            <div class="sm:col-span-2">
                                <p class="text-xs font-semibold uppercase tracking-wider text-violet-600 dark:text-violet-400">{{ $t('central.india_pricing_section') }}</p>
                            </div>
                            <div>
                                <label class="mb-1.5 block text-sm font-medium text-slate-700 dark:text-slate-300">{{ $t('central.india_monthly_price') }}</label>
                                <div class="flex overflow-hidden rounded-xl border border-slate-200 dark:border-slate-800">
                                    <span class="flex items-center bg-slate-50 dark:bg-slate-950 px-3 text-sm text-slate-500 dark:text-slate-400">{{ indiaCurrency.symbol }}</span>
                                    <input v-model.number="addon.price_india" type="number" min="0" max="99999" class="min-w-0 flex-1 border-0 bg-transparent px-3 py-2.5 text-sm" />
                                </div>
                                <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">India visitors see {{ formatIndiaPrice(addon.price_india) }}/mo</p>
                                <p v-if="form.errors[`addons.${index}.price_india`]" class="mt-1 text-xs text-red-600">{{ form.errors[`addons.${index}.price_india`] }}</p>
                            </div>
                            <div v-if="settings.razorpay_enabled && addon.razorpay_plan_id_monthly_india" class="font-mono text-xs text-violet-700 dark:text-violet-300">
                                {{ indiaCurrency.code }} monthly plan: {{ addon.razorpay_plan_id_monthly_india }}
                            </div>
                            <div v-else-if="!settings.razorpay_enabled" class="sm:col-span-2">
                                <label class="mb-1.5 block text-sm font-medium text-slate-700 dark:text-slate-300">Razorpay {{ indiaCurrency.code }} monthly price ID</label>
                                <input v-model="addon.razorpay_plan_id_monthly_india" type="text" class="w-full rounded-xl border border-slate-200 dark:border-slate-800 px-3.5 py-2.5 font-mono text-sm" />
                            </div>
                        </template>

                        <div v-if="!settings.razorpay_enabled" class="sm:col-span-2">
                            <label class="mb-1.5 block text-sm font-medium text-slate-700 dark:text-slate-300">Razorpay monthly price ID</label>
                            <input v-model="addon.razorpay_plan_id_monthly" type="text" class="w-full rounded-xl border border-slate-200 dark:border-slate-800 px-3.5 py-2.5 font-mono text-sm" />
                        </div>
                        <div v-else-if="addon.razorpay_plan_id_monthly" class="sm:col-span-2 font-mono text-xs text-emerald-800 dark:text-emerald-200">
                            Monthly price: {{ addon.razorpay_plan_id_monthly }}
                        </div>
                        <div class="sm:col-span-2">
                            <label class="mb-1.5 block text-sm font-medium text-slate-700 dark:text-slate-300">Description</label>
                            <textarea v-model="addon.description" rows="2" class="w-full rounded-xl border border-slate-200 dark:border-slate-800 px-3.5 py-2.5 text-sm" />
                        </div>
                    </div>
                    <p v-if="form.errors[`addons.${index}.price_monthly`]" class="mt-2 text-xs text-red-600">{{ form.errors[`addons.${index}.price_monthly`] }}</p>
                </div>

                <button type="submit" class="rounded-xl bg-blue-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-blue-700 disabled:opacity-60" :disabled="form.processing">{{ $t('central.save_settings') }}</button>
            </form>
        </div>
    </AdminLayout>
</template>
