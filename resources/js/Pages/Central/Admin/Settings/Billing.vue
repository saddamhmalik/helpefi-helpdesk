<script setup>
import { computed } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import AdminLayout from '../../../../Layouts/AdminLayout.vue';
import PageHeader from '../../../../Components/PageHeader.vue';
import SettingsTabs from '../../../../Components/Platform/SettingsTabs.vue';

const props = defineProps({
    settings: Object,
    availableCurrencies: { type: Array, default: () => [] },
});

const initialCurrencyCode = () => (
    typeof props.settings.currency === 'object'
        ? props.settings.currency.code
        : props.settings.currency
);

const indiaCurrency = computed(() => (
    typeof props.settings.india_currency === 'object'
        ? props.settings.india_currency
        : { symbol: '₹', code: 'INR', name: 'Indian Rupee' }
));

const form = useForm({
    currency: initialCurrencyCode(),
    india_pricing: props.settings.india_pricing ?? false,
});

const baseMatchesIndia = computed(() => form.currency === indiaCurrency.value.code);

const submit = () => {
    form.put('/admin/settings', { preserveScroll: true });
};
</script>

<template>
    <Head :title="$t('central.settings_tab_billing')" />
    <AdminLayout>
        <div class="mx-auto max-w-4xl px-4 py-8 sm:px-6">
            <PageHeader
                :title="$t('central.platform_settings')"
                :description="$t('central.settings_billing_description')"
            />

            <SettingsTabs />

            <form class="space-y-6" @submit.prevent="submit">
                <section class="rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 p-6 shadow-sm">
                    <h2 class="font-semibold text-slate-900 dark:text-slate-100">{{ $t('central.currency') }}</h2>
                    <div class="mt-5">
                        <label class="mb-1.5 block text-sm font-medium text-slate-700 dark:text-slate-300">{{ $t('central.base_global_currency') }}</label>
                        <select v-model="form.currency" required class="w-full rounded-xl border border-slate-200 dark:border-slate-800 px-3.5 py-2.5 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20 sm:max-w-md">
                            <option v-for="item in availableCurrencies" :key="item.code" :value="item.code">
                                {{ item.label }}
                            </option>
                        </select>
                        <p v-if="form.errors.currency" class="mt-1.5 text-xs text-red-600">{{ form.errors.currency }}</p>
                        <p class="mt-2 text-sm text-slate-600 dark:text-slate-400">{{ $t('central.base_currency_hint') }}</p>
                    </div>

                    <label class="mt-5 flex cursor-pointer items-start gap-3 rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-950 px-4 py-3.5">
                        <input v-model="form.india_pricing" type="checkbox" class="mt-0.5 h-4 w-4 rounded border-slate-300 text-blue-600 focus:ring-blue-500" />
                        <span>
                            <span class="block text-sm font-medium text-slate-900 dark:text-slate-100">{{ $t('central.india_pricing') }}</span>
                            <span class="mt-0.5 block text-xs text-slate-600 dark:text-slate-400">{{ $t('central.india_pricing_hint') }}</span>
                        </span>
                    </label>

                    <p
                        v-if="form.india_pricing && baseMatchesIndia"
                        class="mt-3 rounded-xl border border-amber-200 dark:border-amber-900/60 bg-amber-50 dark:bg-amber-950/40 px-4 py-3 text-xs text-amber-800 dark:text-amber-200"
                    >{{ $t('central.india_pricing_same_currency_warning', { currency: indiaCurrency.code }) }}</p>
                    <p
                        v-else-if="form.india_pricing"
                        class="mt-3 rounded-xl border border-blue-200 dark:border-blue-900/60 bg-blue-50 dark:bg-blue-950/40 px-4 py-3 text-xs text-blue-800 dark:text-blue-200"
                    >{{ $t('central.india_pricing_active_note', { symbol: indiaCurrency.symbol, currency: indiaCurrency.code }) }}</p>
                </section>

                <button type="submit" class="rounded-xl bg-blue-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-blue-700 disabled:opacity-60" :disabled="form.processing">{{ $t('central.save_settings') }}</button>
            </form>
        </div>
    </AdminLayout>
</template>
