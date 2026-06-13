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
import { useI18n } from 'vue-i18n';
import { useDateTime } from '../../../../composables/useDateTime.js';

const props = defineProps({
    tenants: Object,
    stats: Object,
    filters: Object,
    plans: { type: Array, default: () => [] },
    razorpay_enabled: Boolean,
});

const { formatDateTime, formatDate } = useDateTime();

const { t } = useI18n();

const { can } = usePlatformAdmin();
const canManage = can('tenants.manage');

const search = ref(props.filters.q ?? '');
const status = ref(props.filters.status ?? 'all');
const manageTenant = ref(null);
const selectedPlan = ref('starter');
const savingPlan = ref(false);
const startingTrial = ref(false);
const deleteTenant = ref(null);
const deleteConfirmSlug = ref('');
const deleting = ref(false);

const statusFilters = [
    { value: 'all', label: t('central.all') },
    { value: 'active', label: t('common.active') },
    { value: 'trial', label: t('central.on_trial') },
    { value: 'trial_expired', label: t('central.trial_expired') },
    { value: 'blocked', label: t('central.blocked') },
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
        return 'bg-red-100 text-red-700 dark:text-red-300 ring-red-200';
    }

    if (tenant.subscription?.on_trial) {
        return 'bg-blue-100 text-blue-700 dark:text-blue-300 ring-blue-200';
    }

    if (tenant.subscription?.trial_expired) {
        return 'bg-amber-100 text-amber-800 ring-amber-200';
    }

    if (tenant.subscription?.status === 'past_due') {
        return 'bg-orange-100 text-orange-800 ring-orange-200';
    }

    if (!tenant.subscription) {
        return 'bg-slate-100 dark:bg-slate-900 text-slate-700 dark:text-slate-300 ring-slate-200 dark:ring-slate-700';
    }

    return 'bg-emerald-100 text-emerald-700 dark:text-emerald-300 ring-emerald-200';
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

const openDelete = (tenant) => {
    closeManage();
    deleteTenant.value = tenant;
    deleteConfirmSlug.value = '';
};

const closeDelete = () => {
    deleteTenant.value = null;
    deleteConfirmSlug.value = '';
};

const confirmDelete = () => {
    if (!deleteTenant.value) {
        return;
    }

    deleting.value = true;

    router.delete(`/admin/tenants/${deleteTenant.value.id}`, {
        data: { confirm_slug: deleteConfirmSlug.value },
        preserveScroll: true,
        onFinish: () => {
            deleting.value = false;
            closeDelete();
        },
    });
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

const startTrial = () => {
    if (!manageTenant.value) {
        return;
    }

    startingTrial.value = true;

    router.put(`/admin/tenants/${manageTenant.value.id}`, {
        start_trial: true,
    }, {
        preserveScroll: true,
        onFinish: () => {
            startingTrial.value = false;
            closeManage();
        },
    });
};

const hasFilters = computed(() => Boolean(props.filters.q) || (props.filters.status && props.filters.status !== 'all'));
</script>

<template>
    <Head :title="$t('central.workspaces')" />
    <AdminLayout>
        <div class="mx-auto max-w-7xl px-4 py-8 sm:px-6">
            <PageHeader
                :title="$t('central.workspaces')"
                :description="$t('central.search_filter_and_manage_tenant_subscriptions_and_access')"
            />

            <div class="mb-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
                <PlatformStatCard :label="$t('central.total')" :value="stats.total" />
                <PlatformStatCard :label="$t('central.active_plans')" :value="stats.active" tone="emerald" />
                <PlatformStatCard :label="$t('central.on_trial')" :value="stats.on_trial" tone="blue" />
                <PlatformStatCard :label="$t('central.trial_expired')" :value="stats.expired_trial" tone="amber" />
                <PlatformStatCard :label="$t('central.blocked')" :value="stats.blocked" tone="red" />
            </div>

            <div class="mb-4 flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                <div class="relative max-w-md flex-1">
                    <svg class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400 dark:text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-4.35-4.35M10 18a8 8 0 100-16 8 8 0 000 16z" />
                    </svg>
                    <input
                        v-model="search"
                        type="search"
                        :placeholder="$t('central.search_by_name_slug_or_admin_email_ellipsis')"
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
                        :class="status === item.value ? 'bg-slate-900 text-white' : 'bg-white dark:bg-slate-900 text-slate-600 dark:text-slate-400 ring-1 ring-slate-200 dark:ring-slate-700 hover:bg-slate-50 dark:bg-slate-950 dark:hover:bg-slate-800'"
                        @click="status = item.value"
                    >
                        {{ item.label }}
                    </button>
                </div>
            </div>

            <div class="overflow-hidden rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 shadow-sm">
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="border-b border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-950/80">
                            <tr>
                                <th class="px-5 py-3.5 text-left font-medium text-slate-600 dark:text-slate-400">{{ $t('settings.groups.workspace') }}</th>
                                <th class="px-5 py-3.5 text-left font-medium text-slate-600 dark:text-slate-400">{{ $t('central.admin') }}</th>
                                <th class="px-5 py-3.5 text-left font-medium text-slate-600 dark:text-slate-400">{{ $t('central.plan') }}</th>
                                <th class="px-5 py-3.5 text-left font-medium text-slate-600 dark:text-slate-400">{{ $t('central.status') }}</th>
                                <th class="px-5 py-3.5 text-left font-medium text-slate-600 dark:text-slate-400">{{ $t('central.created') }}</th>
                                <th class="px-5 py-3.5 text-right font-medium text-slate-600 dark:text-slate-400">{{ $t('central.actions') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                            <tr v-for="tenant in tenants.data" :key="tenant.id" class="transition hover:bg-slate-50 dark:bg-slate-950 dark:hover:bg-slate-800/70">
                                <td class="px-5 py-4">
                                    <div class="flex items-start gap-3">
                                        <AppAvatar :name="tenant.name" size="sm" />
                                        <div class="min-w-0">
                                            <p class="font-medium text-slate-900 dark:text-slate-100">{{ tenant.name }}</p>
                                            <p class="font-mono text-xs text-slate-500 dark:text-slate-400">{{ tenant.slug }}</p>
                                            <p v-if="tenant.database" class="mt-0.5 font-mono text-[11px] text-slate-400 dark:text-slate-500">{{ tenant.database }}</p>
                                            <a
                                                v-if="tenant.url"
                                                :href="tenant.url"
                                                target="_blank"
                                                class="mt-1.5 inline-flex items-center gap-1 text-xs font-medium text-blue-600 hover:text-blue-700 dark:hover:text-blue-300 dark:text-blue-300"
                                            >
                                                {{ $t('central.open_workspace') }}
                                                <svg class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" /></svg>
                                            </a>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-5 py-4">
                                    <template v-if="tenant.admin_email">
                                        <p class="font-medium text-slate-900 dark:text-slate-100">{{ tenant.admin_name || 'Admin' }}</p>
                                        <p class="text-xs text-slate-500 dark:text-slate-400">{{ tenant.admin_email }}</p>
                                    </template>
                                    <span v-else class="text-slate-400 dark:text-slate-500">{{ $t('central.no_admin_found') }}</span>
                                </td>
                                <td class="px-5 py-4">
                                    <p class="font-medium text-slate-900 dark:text-slate-100">{{ planLabel(tenant) }}</p>
                                    <p v-if="tenant.subscription?.plan_price && !tenant.subscription?.on_trial" class="text-xs text-slate-500 dark:text-slate-400">
                                        ${{ tenant.subscription.plan_price }}/mo
                                    </p>
                                    <p v-if="razorpay_enabled && tenant.subscription?.has_razorpay" class="mt-1 text-[10px] font-semibold uppercase tracking-wide text-violet-600">
                                        {{ $t('central.stripe_billing') }}
                                    </p>
                                    <p v-if="tenant.subscription?.renews_at && !tenant.subscription?.on_trial" class="mt-1 text-xs text-slate-400 dark:text-slate-500">
                                        Renews {{ formatDate(tenant.subscription.renews_at) }}
                                    </p>
                                </td>
                                <td class="px-5 py-4">
                                    <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-medium capitalize ring-1 ring-inset" :class="statusClass(tenant)">
                                        {{ statusLabel(tenant) }}
                                    </span>
                                    <p v-if="tenant.subscription?.trial_ends_at && tenant.subscription?.on_trial" class="mt-1.5 text-xs text-slate-500 dark:text-slate-400">
                                        Ends {{ formatDate(tenant.subscription.trial_ends_at) }}
                                    </p>
                                </td>
                                <td class="px-5 py-4 text-slate-600 dark:text-slate-400">
                                    {{ formatDate(tenant.created_at) }}
                                </td>
                                <td class="px-5 py-4 text-right">
                                    <div class="flex justify-end gap-2">
                                        <button
                                            v-if="canManage"
                                            type="button"
                                            class="rounded-lg px-3 py-1.5 text-xs font-medium text-slate-700 dark:text-slate-300 ring-1 ring-slate-200 dark:ring-slate-700 hover:bg-slate-50 dark:bg-slate-950 dark:hover:bg-slate-800"
                                            @click="openManage(tenant)"
                                        >{{ $t('nav.sections.manage') }}</button>
                                        <a
                                            v-if="tenant.url"
                                            :href="tenant.url"
                                            target="_blank"
                                            class="rounded-lg px-3 py-1.5 text-xs font-medium text-blue-600 ring-1 ring-blue-200 hover:bg-blue-50 dark:bg-blue-950/40"
                                        >
                                            {{ $t('central.visit') }}
                                        </a>
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="!tenants.data.length">
                                <td colspan="6" class="px-5 py-16 text-center">
                                    <p class="text-sm font-medium text-slate-900 dark:text-slate-100">{{ $t('central.no_workspaces_found') }}</p>
                                    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
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
            :description="$t('central.override_subscription_plan_or_block_workspace_access')"
            size="md"
            @close="closeManage"
        >
            <div v-if="manageTenant" class="space-y-5">
                <div class="rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-950 p-4 text-sm">
                    <p class="font-medium text-slate-900 dark:text-slate-100">{{ manageTenant.slug }}</p>
                    <p v-if="manageTenant.database" class="mt-1 font-mono text-xs text-slate-500 dark:text-slate-400">{{ manageTenant.database }}</p>
                    <p class="mt-1 text-slate-600 dark:text-slate-400">{{ manageTenant.admin_email || 'No admin email on file' }}</p>
                    <p class="mt-2 text-xs text-slate-500 dark:text-slate-400">
                        {{ $t('central.platform_overrides_apply_immediately_and_do_not_charge_stripe') }}
                    </p>
                </div>

                <div
                    v-if="canManage && !manageTenant.subscription"
                    class="rounded-xl border border-amber-200 dark:border-amber-900/60 bg-amber-50 dark:bg-amber-950/40 p-4"
                >
                    <p class="text-sm font-medium text-amber-950 dark:text-amber-100">{{ $t('central.no_subscription_record') }}</p>
                    <p class="mt-1 text-sm text-amber-800 dark:text-amber-200">
                        {{ $t('central.this_workspace_was_created_without_a_trial_start_a_free_trial_so_billi') }}
                    </p>
                    <button
                        type="button"
                        class="mt-3 rounded-xl bg-amber-900 px-4 py-2 text-sm font-semibold text-white hover:bg-amber-950 disabled:opacity-60"
                        :disabled="startingTrial"
                        @click="startTrial"
                    >
                        {{ startingTrial ? 'Starting…' : 'Start free trial' }}
                    </button>
                </div>

                <div v-if="canManage && manageTenant.subscription">
                    <label class="mb-1.5 block text-sm font-medium text-slate-700 dark:text-slate-300">{{ $t('central.subscription_plan') }}</label>
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

                <div class="border-t border-slate-200 dark:border-slate-800 pt-4">
                    <button
                        type="button"
                        class="w-full rounded-xl px-4 py-2.5 text-sm font-semibold transition"
                        :class="manageTenant.is_blocked ? 'bg-emerald-600 text-white hover:bg-emerald-700' : 'bg-red-50 dark:bg-red-950/40 text-red-700 dark:text-red-300 ring-1 ring-red-200 hover:bg-red-100'"
                        @click="toggleBlock(manageTenant)"
                    >
                        {{ manageTenant.is_blocked ? 'Unblock workspace' : 'Block workspace' }}
                    </button>
                </div>

                <div class="border-t border-slate-200 dark:border-slate-800 pt-4">
                    <button
                        type="button"
                        class="w-full rounded-xl bg-red-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-red-700"
                        @click="openDelete(manageTenant)"
                    >{{ $t('central.delete_workspace_permanently') }}</button>
                    <p class="mt-2 text-xs text-slate-500 dark:text-slate-400">{{ $t('central.removes_the_workspace_record_and_drops_its_tenant_database') }}</p>
                </div>
            </div>
        </AppModal>

        <AppModal
            :open="Boolean(deleteTenant)"
            :title="deleteTenant ? `Delete ${deleteTenant.name}?` : ''"
            :description="$t('central.this_permanently_deletes_the_workspace_and_drops_its_database_this_can')"
            size="md"
            @close="closeDelete"
        >
            <div v-if="deleteTenant" class="space-y-4">
                <div class="rounded-xl border border-red-200 dark:border-red-900/60 bg-red-50 dark:bg-red-950/40 p-4 text-sm text-red-900">
                    <p class="font-medium">Database: <span class="font-mono">{{ deleteTenant.database }}</span></p>
                    <p class="mt-2">Type <span class="font-mono font-semibold">{{ deleteTenant.slug }}</span> to confirm.</p>
                </div>
                <input
                    v-model="deleteConfirmSlug"
                    type="text"
                    :placeholder="$t('central.workspace_slug')"
                    :class="adminInputClass"
                    autocomplete="off"
                />
                <button
                    type="button"
                    class="w-full rounded-xl bg-red-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-red-700 disabled:cursor-not-allowed disabled:opacity-50"
                    :disabled="deleting || deleteConfirmSlug !== deleteTenant.slug"
                    @click="confirmDelete"
                >
                    {{ deleting ? 'Deleting…' : 'Delete workspace and database' }}
                </button>
            </div>
        </AppModal>
    </AdminLayout>
</template>
