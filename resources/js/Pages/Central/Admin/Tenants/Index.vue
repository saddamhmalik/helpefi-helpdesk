<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import AdminLayout from '../../../../Layouts/AdminLayout.vue';
import AppAvatar from '../../../../Components/AppAvatar.vue';
import AppModal from '../../../../Components/AppModal.vue';
import PageHeader from '../../../../Components/PageHeader.vue';
import PaginationLinks from '../../../../Components/PaginationLinks.vue';
import PlatformStatCard from '../../../../Components/Platform/PlatformStatCard.vue';
import { adminInputClass, usePlatformAdmin } from '../../../../composables/usePlatformAdmin.js';

const props = defineProps({
    tenants: Object,
    stats: Object,
    filters: Object,
    plans: { type: Array, default: () => [] },
    stripe_enabled: Boolean,
});

const { can } = usePlatformAdmin();
const canManage = can('tenants.manage');

const search = ref(props.filters.q ?? '');
const status = ref(props.filters.status ?? 'all');
const manageTenant = ref(null);
const selectedPlan = ref('starter');
const savingPlan = ref(false);

const statusFilters = [
    { value: 'all', label: 'All' },
    { value: 'active', label: 'Active' },
    { value: 'trial', label: 'On trial' },
    { value: 'trial_expired', label: 'Trial expired' },
    { value: 'blocked', label: 'Blocked' },
];

let searchTimer = null;

watch(search, () => {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(applyFilters, 300);
});

watch(status, applyFilters);

function applyFilters() {
    router.get('/admin/tenants', {
        q: search.value || undefined,
        status: status.value === 'all' ? undefined : status.value,
    }, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
}

const statusLabel = (tenant) => {
    if (tenant.is_blocked) {
        return 'Blocked';
    }

    if (!tenant.subscription) {
        return 'No subscription';
    }

    if (tenant.subscription.on_trial) {
        return 'Trial';
    }

    if (tenant.subscription.trial_expired) {
        return 'Trial expired';
    }

    return tenant.subscription.status.replace('_', ' ');
};

const statusClass = (tenant) => {
    if (tenant.is_blocked) {
        return 'bg-red-100 text-red-700 ring-red-200';
    }

    if (tenant.subscription?.on_trial) {
        return 'bg-blue-100 text-blue-700 ring-blue-200';
    }

    if (tenant.subscription?.trial_expired) {
        return 'bg-amber-100 text-amber-800 ring-amber-200';
    }

    if (tenant.subscription?.status === 'past_due') {
        return 'bg-orange-100 text-orange-800 ring-orange-200';
    }

    return 'bg-emerald-100 text-emerald-700 ring-emerald-200';
};

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

const planLabel = (tenant) => {
    if (tenant.subscription?.on_trial) {
        return 'Free trial';
    }

    return tenant.subscription?.plan_name ?? 'No plan';
};

const openManage = (tenant) => {
    manageTenant.value = tenant;
    selectedPlan.value = tenant.subscription?.plan ?? props.plans[0]?.slug ?? 'starter';
};

const closeManage = () => {
    manageTenant.value = null;
};

const toggleBlock = (tenant) => {
    router.put(`/admin/tenants/${tenant.id}`, {
        is_blocked: !tenant.is_blocked,
    }, {
        preserveScroll: true,
        onSuccess: () => closeManage(),
    });
};

const savePlan = () => {
    if (!manageTenant.value) {
        return;
    }

    savingPlan.value = true;

    router.put(`/admin/tenants/${manageTenant.value.id}`, {
        plan: selectedPlan.value,
    }, {
        preserveScroll: true,
        onFinish: () => {
            savingPlan.value = false;
            closeManage();
        },
    });
};

const hasFilters = computed(() => Boolean(props.filters.q) || (props.filters.status && props.filters.status !== 'all'));
</script>

<template>
    <Head title="Workspaces" />
    <AdminLayout>
        <div class="mx-auto max-w-7xl px-4 py-8 sm:px-6">
            <PageHeader
                title="Workspaces"
                description="Search, filter, and manage tenant subscriptions and access."
            />

            <div class="mb-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
                <PlatformStatCard label="Total" :value="stats.total" />
                <PlatformStatCard label="Active plans" :value="stats.active" tone="emerald" />
                <PlatformStatCard label="On trial" :value="stats.on_trial" tone="blue" />
                <PlatformStatCard label="Trial expired" :value="stats.expired_trial" tone="amber" />
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
                        placeholder="Search by name, slug, or admin email…"
                        :class="adminInputClass"
                        class="pl-10"
                    />
                </div>
                <div class="flex flex-wrap gap-2">
                    <button
                        v-for="item in statusFilters"
                        :key="item.value"
                        type="button"
                        class="rounded-full px-3 py-1.5 text-sm font-medium transition"
                        :class="status === item.value ? 'bg-slate-900 text-white' : 'bg-white text-slate-600 ring-1 ring-slate-200 hover:bg-slate-50'"
                        @click="status = item.value"
                    >
                        {{ item.label }}
                    </button>
                </div>
            </div>

            <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="border-b border-slate-200 bg-slate-50/80">
                            <tr>
                                <th class="px-5 py-3.5 text-left font-medium text-slate-600">Workspace</th>
                                <th class="px-5 py-3.5 text-left font-medium text-slate-600">Admin</th>
                                <th class="px-5 py-3.5 text-left font-medium text-slate-600">Plan</th>
                                <th class="px-5 py-3.5 text-left font-medium text-slate-600">Status</th>
                                <th class="px-5 py-3.5 text-left font-medium text-slate-600">Created</th>
                                <th class="px-5 py-3.5 text-right font-medium text-slate-600">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <tr v-for="tenant in tenants.data" :key="tenant.id" class="transition hover:bg-slate-50/70">
                                <td class="px-5 py-4">
                                    <div class="flex items-start gap-3">
                                        <AppAvatar :name="tenant.name" size="sm" />
                                        <div class="min-w-0">
                                            <p class="font-medium text-slate-900">{{ tenant.name }}</p>
                                            <p class="font-mono text-xs text-slate-500">{{ tenant.slug }}</p>
                                            <a
                                                v-if="tenant.url"
                                                :href="tenant.url"
                                                target="_blank"
                                                class="mt-1.5 inline-flex items-center gap-1 text-xs font-medium text-blue-600 hover:text-blue-700"
                                            >
                                                Open workspace
                                                <svg class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" /></svg>
                                            </a>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-5 py-4">
                                    <template v-if="tenant.admin_email">
                                        <p class="font-medium text-slate-900">{{ tenant.admin_name || 'Admin' }}</p>
                                        <p class="text-xs text-slate-500">{{ tenant.admin_email }}</p>
                                    </template>
                                    <span v-else class="text-slate-400">No admin found</span>
                                </td>
                                <td class="px-5 py-4">
                                    <p class="font-medium text-slate-900">{{ planLabel(tenant) }}</p>
                                    <p v-if="tenant.subscription?.plan_price && !tenant.subscription?.on_trial" class="text-xs text-slate-500">
                                        ${{ tenant.subscription.plan_price }}/mo
                                    </p>
                                    <p v-if="stripe_enabled && tenant.subscription?.has_stripe" class="mt-1 text-[10px] font-semibold uppercase tracking-wide text-violet-600">
                                        Stripe billing
                                    </p>
                                    <p v-if="tenant.subscription?.renews_at && !tenant.subscription?.on_trial" class="mt-1 text-xs text-slate-400">
                                        Renews {{ formatDate(tenant.subscription.renews_at) }}
                                    </p>
                                </td>
                                <td class="px-5 py-4">
                                    <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-medium capitalize ring-1 ring-inset" :class="statusClass(tenant)">
                                        {{ statusLabel(tenant) }}
                                    </span>
                                    <p v-if="tenant.subscription?.trial_ends_at && tenant.subscription?.on_trial" class="mt-1.5 text-xs text-slate-500">
                                        Ends {{ formatDate(tenant.subscription.trial_ends_at) }}
                                    </p>
                                </td>
                                <td class="px-5 py-4 text-slate-600">
                                    {{ formatDate(tenant.created_at) }}
                                </td>
                                <td class="px-5 py-4 text-right">
                                    <div class="flex justify-end gap-2">
                                        <button
                                            v-if="canManage"
                                            type="button"
                                            class="rounded-lg px-3 py-1.5 text-xs font-medium text-slate-700 ring-1 ring-slate-200 hover:bg-slate-50"
                                            @click="openManage(tenant)"
                                        >
                                            Manage
                                        </button>
                                        <a
                                            v-if="tenant.url"
                                            :href="tenant.url"
                                            target="_blank"
                                            class="rounded-lg px-3 py-1.5 text-xs font-medium text-blue-600 ring-1 ring-blue-200 hover:bg-blue-50"
                                        >
                                            Visit
                                        </a>
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="!tenants.data.length">
                                <td colspan="6" class="px-5 py-16 text-center">
                                    <p class="text-sm font-medium text-slate-900">No workspaces found</p>
                                    <p class="mt-1 text-sm text-slate-500">
                                        {{ hasFilters ? 'Try adjusting your search or filters.' : 'New signups will appear here.' }}
                                    </p>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="mt-6">
                <PaginationLinks
                    :links="tenants.links"
                    :from="tenants.from"
                    :to="tenants.to"
                    :total="tenants.total"
                />
            </div>
        </div>

        <AppModal
            :open="Boolean(manageTenant)"
            :title="manageTenant ? `Manage ${manageTenant.name}` : ''"
            description="Override subscription plan or block workspace access."
            size="md"
            @close="closeManage"
        >
            <div v-if="manageTenant" class="space-y-5">
                <div class="rounded-xl border border-slate-200 bg-slate-50 p-4 text-sm">
                    <p class="font-medium text-slate-900">{{ manageTenant.slug }}</p>
                    <p class="mt-1 text-slate-600">{{ manageTenant.admin_email || 'No admin email on file' }}</p>
                    <p class="mt-2 text-xs text-slate-500">
                        Platform overrides apply immediately and do not charge Stripe.
                    </p>
                </div>

                <div v-if="canManage">
                    <label class="mb-1.5 block text-sm font-medium text-slate-700">Subscription plan</label>
                    <select v-model="selectedPlan" :class="adminInputClass">
                        <option v-for="plan in plans" :key="plan.slug" :value="plan.slug">
                            {{ plan.name }} — ${{ plan.price }}/mo
                        </option>
                    </select>
                    <button
                        type="button"
                        class="mt-3 rounded-xl bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700 disabled:opacity-60"
                        :disabled="savingPlan"
                        @click="savePlan"
                    >
                        {{ savingPlan ? 'Saving…' : 'Update plan' }}
                    </button>
                </div>

                <div class="border-t border-slate-200 pt-4">
                    <button
                        type="button"
                        class="w-full rounded-xl px-4 py-2.5 text-sm font-semibold transition"
                        :class="manageTenant.is_blocked ? 'bg-emerald-600 text-white hover:bg-emerald-700' : 'bg-red-50 text-red-700 ring-1 ring-red-200 hover:bg-red-100'"
                        @click="toggleBlock(manageTenant)"
                    >
                        {{ manageTenant.is_blocked ? 'Unblock workspace' : 'Block workspace' }}
                    </button>
                </div>
            </div>
        </AppModal>
    </AdminLayout>
</template>
