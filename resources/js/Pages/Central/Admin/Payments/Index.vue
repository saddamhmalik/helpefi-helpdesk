<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import AdminLayout from '../../../../Layouts/AdminLayout.vue';
import PageHeader from '../../../../Components/PageHeader.vue';
import PaginationLinks from '../../../../Components/PaginationLinks.vue';
import PlatformStatCard from '../../../../Components/Platform/PlatformStatCard.vue';
import { adminInputClass } from '../../../../composables/usePlatformAdmin.js';

const props = defineProps({
    payments: Object,
    stats: Object,
    currency: Object,
    filters: Object,
    stripe_enabled: Boolean,
});

const search = ref(props.filters.q ?? '');
const status = ref(props.filters.status ?? 'all');

const statusFilters = [
    { value: 'all', label: 'All' },
    { value: 'paid', label: 'Paid' },
    { value: 'failed', label: 'Failed' },
    { value: 'refunded', label: 'Refunded' },
];

let searchTimer = null;

watch(search, () => {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(applyFilters, 300);
});

watch(status, applyFilters);

function applyFilters() {
    router.get('/admin/payments', {
        q: search.value || undefined,
        status: status.value === 'all' ? undefined : status.value,
    }, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
}

const formatMoney = (amount, currencyCode = props.currency.code) => {
    const value = (amount ?? 0) / 100;
    const code = currencyCode ?? 'USD';

    try {
        return new Intl.NumberFormat(undefined, {
            style: 'currency',
            currency: code,
            minimumFractionDigits: 0,
            maximumFractionDigits: 2,
        }).format(value);
    } catch {
        return `${code} ${value.toFixed(2)}`;
    }
};

const formatDateTime = (value) => {
    if (!value) {
        return '—';
    }

    return new Date(value).toLocaleString(undefined, {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
        hour: 'numeric',
        minute: '2-digit',
    });
};

const statusClass = (payment) => {
    if (payment.status === 'paid') {
        return 'bg-emerald-100 text-emerald-700 ring-emerald-200';
    }

    if (payment.status === 'failed') {
        return 'bg-red-100 text-red-700 ring-red-200';
    }

    return 'bg-slate-100 text-slate-700 ring-slate-200';
};

const hasFilters = computed(() => Boolean(props.filters.q) || (props.filters.status && props.filters.status !== 'all'));
</script>

<template>
    <Head title="Payments" />
    <AdminLayout>
        <div class="mx-auto max-w-7xl px-4 py-8 sm:px-6">
            <PageHeader
                title="Payments"
                description="Received Stripe subscription payments with workspace and invoice details."
            />

            <div class="mb-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <PlatformStatCard label="Total collected" :value="formatMoney(stats.total_collected)" tone="emerald" />
                <PlatformStatCard label="This month" :value="formatMoney(stats.month_collected)" tone="blue" />
                <PlatformStatCard label="Paid payments" :value="stats.paid_count" />
                <PlatformStatCard label="All records" :value="stats.payment_count" />
            </div>

            <div v-if="!stripe_enabled" class="mb-4 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
                Stripe is not configured. New payments will not be recorded until Stripe webhooks are enabled.
            </div>

            <div class="mb-4 flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                <div class="relative max-w-md flex-1">
                    <svg class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-4.35-4.35M10 18a8 8 0 100-16 8 8 0 000 16z" />
                    </svg>
                    <input
                        v-model="search"
                        type="search"
                        placeholder="Search workspace, email, invoice..."
                        :class="[adminInputClass, 'pl-10']"
                    />
                </div>

                <div class="flex flex-wrap gap-2">
                    <button
                        v-for="filter in statusFilters"
                        :key="filter.value"
                        type="button"
                        class="rounded-full px-3 py-1.5 text-sm font-medium transition"
                        :class="status === filter.value ? 'bg-blue-600 text-white' : 'bg-white text-slate-600 ring-1 ring-slate-200 hover:bg-slate-50'"
                        @click="status = filter.value"
                    >
                        {{ filter.label }}
                    </button>
                </div>
            </div>

            <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200 text-sm">
                        <thead class="bg-slate-50">
                            <tr>
                                <th class="px-4 py-3 text-left font-semibold text-slate-600">Date</th>
                                <th class="px-4 py-3 text-left font-semibold text-slate-600">Workspace</th>
                                <th class="px-4 py-3 text-left font-semibold text-slate-600">Customer</th>
                                <th class="px-4 py-3 text-left font-semibold text-slate-600">Plan</th>
                                <th class="px-4 py-3 text-left font-semibold text-slate-600">Amount</th>
                                <th class="px-4 py-3 text-left font-semibold text-slate-600">Status</th>
                                <th class="px-4 py-3 text-left font-semibold text-slate-600">Invoice</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <tr v-for="payment in payments.data" :key="payment.id" class="hover:bg-slate-50/80">
                                <td class="whitespace-nowrap px-4 py-3 text-slate-700">
                                    {{ formatDateTime(payment.paid_at || payment.created_at) }}
                                </td>
                                <td class="px-4 py-3">
                                    <template v-if="payment.tenant">
                                        <p class="font-medium text-slate-900">{{ payment.tenant.name }}</p>
                                        <p class="text-xs text-slate-500">{{ payment.tenant.slug }}</p>
                                        <Link
                                            v-if="payment.tenant.url"
                                            :href="payment.tenant.url"
                                            class="text-xs text-blue-600 hover:text-blue-700"
                                            target="_blank"
                                        >
                                            Open workspace
                                        </Link>
                                    </template>
                                    <span v-else class="text-slate-400">Unlinked</span>
                                </td>
                                <td class="px-4 py-3">
                                    <p class="text-slate-900">{{ payment.customer_name || '—' }}</p>
                                    <p class="text-xs text-slate-500">{{ payment.customer_email || '—' }}</p>
                                </td>
                                <td class="px-4 py-3 text-slate-700">
                                    {{ payment.plan_name || '—' }}
                                </td>
                                <td class="whitespace-nowrap px-4 py-3 font-medium tabular-nums text-slate-900">
                                    {{ formatMoney(payment.amount, payment.currency) }}
                                </td>
                                <td class="px-4 py-3">
                                    <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold ring-1 ring-inset" :class="statusClass(payment)">
                                        {{ payment.status }}
                                    </span>
                                </td>
                                <td class="px-4 py-3">
                                    <p class="font-mono text-xs text-slate-600">{{ payment.invoice_number || payment.stripe_invoice_id }}</p>
                                    <p v-if="payment.description" class="mt-1 max-w-xs truncate text-xs text-slate-500" :title="payment.description">
                                        {{ payment.description }}
                                    </p>
                                    <div class="mt-1 flex gap-2 text-xs">
                                        <a
                                            v-if="payment.invoice_url"
                                            :href="payment.invoice_url"
                                            target="_blank"
                                            class="text-blue-600 hover:text-blue-700"
                                        >
                                            View
                                        </a>
                                        <a
                                            v-if="payment.invoice_pdf"
                                            :href="payment.invoice_pdf"
                                            target="_blank"
                                            class="text-blue-600 hover:text-blue-700"
                                        >
                                            PDF
                                        </a>
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="!payments.data.length">
                                <td colspan="7" class="px-4 py-12 text-center text-slate-500">
                                    <template v-if="hasFilters">No payments match your filters.</template>
                                    <template v-else>No payments recorded yet. Payments appear here when Stripe sends invoice webhooks.</template>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div v-if="payments.data.length" class="border-t border-slate-200 px-4 py-3">
                    <PaginationLinks :links="payments.links" />
                </div>
            </div>
        </div>
    </AdminLayout>
</template>
