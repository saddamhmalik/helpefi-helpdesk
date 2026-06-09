<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import AdminLayout from '../../../../Layouts/AdminLayout.vue';
import AppAvatar from '../../../../Components/AppAvatar.vue';
import PageHeader from '../../../../Components/PageHeader.vue';
import PaginationLinks from '../../../../Components/PaginationLinks.vue';
import PlatformStatCard from '../../../../Components/Platform/PlatformStatCard.vue';
import { adminInputClass } from '../../../../composables/usePlatformAdmin.js';

const props = defineProps({
    subscriptions: Object,
    stats: Object,
    currency: Object,
    filters: Object,
    stripe_enabled: Boolean,
});

const search = ref(props.filters.q ?? '');
const status = ref(props.filters.status ?? 'all');

const statusFilters = [
    { value: 'all', label: 'All' },
    { value: 'active', label: 'Active' },
    { value: 'trial', label: 'Trial' },
    { value: 'grace', label: 'Grace period' },
    { value: 'cancelled', label: 'Cancelled' },
    { value: 'past_due', label: 'Past due' },
    { value: 'blocked', label: 'Blocked' },
];

let searchTimer = null;

watch(search, () => {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(applyFilters, 300);
});

watch(status, applyFilters);

function applyFilters() {
    router.get('/admin/subscriptions', {
        q: search.value || undefined,
        status: status.value === 'all' ? undefined : status.value,
    }, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
}

const formatDate = (value) => {
    if (!value) {
        return '—';
    }

    return new Date(value).toLocaleDateString(undefined, {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
    });
};

const formatPrice = (price) => {
    if (price == null) {
        return '—';
    }

    try {
        return new Intl.NumberFormat(undefined, {
            style: 'currency',
            currency: props.currency.code,
            minimumFractionDigits: 0,
            maximumFractionDigits: 0,
        }).format(price);
    } catch {
        return `${props.currency.symbol}${price}`;
    }
};

const statusLabel = (item) => {
    if (item.tenant?.is_blocked) {
        return 'Blocked';
    }

    if (item.cancellation_pending) {
        return 'Ending soon';
    }

    if (item.in_grace_period && item.status === 'cancelled') {
        return 'Grace period';
    }

    if (item.on_trial) {
        return 'Trial';
    }

    if (item.trial_expired) {
        return 'Trial expired';
    }

    return item.status.replace('_', ' ');
};

const statusClass = (item) => {
    if (item.tenant?.is_blocked) {
        return 'bg-red-100 text-red-700 ring-red-200';
    }

    if (item.cancellation_pending || item.in_grace_period) {
        return 'bg-amber-100 text-amber-800 ring-amber-200';
    }

    if (item.on_trial) {
        return 'bg-blue-100 text-blue-700 ring-blue-200';
    }

    if (item.status === 'past_due') {
        return 'bg-orange-100 text-orange-800 ring-orange-200';
    }

    if (item.status === 'cancelled') {
        return 'bg-slate-100 text-slate-700 ring-slate-200';
    }

    return 'bg-emerald-100 text-emerald-700 ring-emerald-200';
};

const hasFilters = computed(() => Boolean(props.filters.q) || (props.filters.status && props.filters.status !== 'all'));
</script>

<template>
    <Head title="Subscriptions" />
    <AdminLayout>
        <div class="mx-auto max-w-7xl px-4 py-8 sm:px-6">
            <PageHeader
                title="Subscriptions"
                description="Workspace subscription status, billing cycles, Stripe linkage, and cancellation grace windows."
            />

            <div class="mb-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-6">
                <PlatformStatCard label="Total" :value="stats.total" />
                <PlatformStatCard label="Active" :value="stats.active" tone="emerald" />
                <PlatformStatCard label="On trial" :value="stats.on_trial" tone="blue" />
                <PlatformStatCard label="In grace" :value="stats.in_grace" tone="amber" />
                <PlatformStatCard label="Cancelled" :value="stats.cancelled" />
                <PlatformStatCard label="Blocked" :value="stats.blocked" tone="red" />
            </div>

            <div class="mb-4 flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                <div class="relative max-w-md flex-1">
                    <svg class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-4.35-4.35M10 18a8 8 0 100-16 8 8 0 000 16z" />
                    </svg>
                    <input
                        v-model="search"
                        type="search"
                        placeholder="Search workspace, plan, Stripe ID..."
                        :class="[adminInputClass, 'pl-10']"
                    />
                </div>

                <div class="flex flex-wrap gap-2">
                    <button
                        v-for="filter in statusFilters"
                        :key="filter.value"
                        type="button"
                        class="rounded-full px-3 py-1.5 text-sm font-medium transition"
                        :class="status === filter.value ? 'bg-slate-900 text-white' : 'bg-white text-slate-600 ring-1 ring-slate-200 hover:bg-slate-50'"
                        @click="status = filter.value"
                    >
                        {{ filter.label }}
                    </button>
                </div>
            </div>

            <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="border-b border-slate-200 bg-slate-50/80">
                            <tr>
                                <th class="px-5 py-3.5 text-left font-medium text-slate-600">Workspace</th>
                                <th class="px-5 py-3.5 text-left font-medium text-slate-600">Plan</th>
                                <th class="px-5 py-3.5 text-left font-medium text-slate-600">Status</th>
                                <th class="px-5 py-3.5 text-left font-medium text-slate-600">Billing</th>
                                <th class="px-5 py-3.5 text-left font-medium text-slate-600">Access ends</th>
                                <th class="px-5 py-3.5 text-left font-medium text-slate-600">Stripe</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <tr v-for="item in subscriptions.data" :key="item.id" class="transition hover:bg-slate-50/70">
                                <td class="px-5 py-4">
                                    <template v-if="item.tenant">
                                        <div class="flex items-start gap-3">
                                            <AppAvatar :name="item.tenant.name" size="sm" />
                                            <div class="min-w-0">
                                                <p class="font-medium text-slate-900">{{ item.tenant.name }}</p>
                                                <p class="font-mono text-xs text-slate-500">{{ item.tenant.slug }}</p>
                                                <p v-if="item.tenant.admin_email" class="mt-1 text-xs text-slate-500">{{ item.tenant.admin_email }}</p>
                                                <Link
                                                    v-if="item.tenant.url"
                                                    :href="`/admin/tenants?q=${encodeURIComponent(item.tenant.slug)}`"
                                                    class="mt-1 inline-flex text-xs font-medium text-blue-600 hover:text-blue-700"
                                                >
                                                    View workspace
                                                </Link>
                                            </div>
                                        </div>
                                    </template>
                                </td>
                                <td class="px-5 py-4">
                                    <p class="font-medium text-slate-900">{{ item.plan_name }}</p>
                                    <p v-if="item.plan_price != null && !item.on_trial" class="text-xs text-slate-500">
                                        {{ formatPrice(item.plan_price) }}/mo
                                    </p>
                                </td>
                                <td class="px-5 py-4">
                                    <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-medium capitalize ring-1 ring-inset" :class="statusClass(item)">
                                        {{ statusLabel(item) }}
                                    </span>
                                    <p v-if="item.on_trial && item.trial_ends_at" class="mt-1.5 text-xs text-slate-500">
                                        Trial ends {{ formatDate(item.trial_ends_at) }}
                                    </p>
                                    <p v-if="item.cancellation_pending" class="mt-1.5 text-xs text-amber-700">
                                        Ends {{ formatDate(item.renews_at) }}
                                    </p>
                                    <p v-if="item.in_grace_period && item.grace_days_remaining != null" class="mt-1.5 text-xs text-amber-700">
                                        {{ item.grace_days_remaining }} day{{ item.grace_days_remaining === 1 ? '' : 's' }} of export grace left
                                    </p>
                                </td>
                                <td class="px-5 py-4 text-slate-600">
                                    <p v-if="item.renews_at && !item.on_trial && !item.cancellation_pending">
                                        Renews {{ formatDate(item.renews_at) }}
                                    </p>
                                    <p v-else-if="item.cancelled_at">
                                        Cancelled {{ formatDate(item.cancelled_at) }}
                                    </p>
                                    <span v-else>—</span>
                                </td>
                                <td class="px-5 py-4 text-slate-600">
                                    <p v-if="item.access_ends_at">{{ formatDate(item.access_ends_at) }}</p>
                                    <span v-else>—</span>
                                </td>
                                <td class="px-5 py-4">
                                    <p v-if="item.has_stripe" class="font-mono text-xs text-violet-700">{{ item.stripe_subscription_id || 'Linked' }}</p>
                                    <p v-else-if="item.tenant?.stripe_customer" class="text-xs text-slate-500">Customer only</p>
                                    <span v-else class="text-slate-400">—</span>
                                    <Link
                                        v-if="stripe_enabled"
                                        href="/admin/payments"
                                        class="mt-1 block text-xs font-medium text-blue-600 hover:text-blue-700"
                                    >
                                        View payments
                                    </Link>
                                </td>
                            </tr>
                            <tr v-if="!subscriptions.data.length">
                                <td colspan="6" class="px-5 py-16 text-center">
                                    <p class="text-sm font-medium text-slate-900">No subscriptions found</p>
                                    <p class="mt-1 text-sm text-slate-500">
                                        {{ hasFilters ? 'Try adjusting your search or filters.' : 'Subscriptions appear when workspaces sign up.' }}
                                    </p>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="mt-6">
                <PaginationLinks :links="subscriptions.links" />
            </div>
        </div>
    </AdminLayout>
</template>
