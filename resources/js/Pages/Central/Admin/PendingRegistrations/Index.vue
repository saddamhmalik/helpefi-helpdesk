<script setup>
import { Head, router } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import AdminLayout from '../../../../Layouts/AdminLayout.vue';
import PageHeader from '../../../../Components/PageHeader.vue';
import PaginationLinks from '../../../../Components/PaginationLinks.vue';
import PlatformStatCard from '../../../../Components/Platform/PlatformStatCard.vue';
import { adminInputClass, usePlatformAdmin } from '../../../../composables/usePlatformAdmin.js';
import { useDateTime } from '../../../../composables/useDateTime.js';

const props = defineProps({
    registrations: Object,
    stats: Object,
    filters: Object,
});

const { formatDateTime } = useDateTime();
const { can } = usePlatformAdmin();
const canManage = can('tenants.manage');

const search = ref(props.filters.q ?? '');
const status = ref(props.filters.status ?? 'all');
const purging = ref(false);
const deletingIds = ref([]);

const statusFilters = [
    { value: 'all', label: 'All' },
    { value: 'active', label: 'Active' },
    { value: 'expired', label: 'Expired' },
];

let searchTimer = null;

watch(search, () => {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(applyFilters, 300);
});

watch(status, applyFilters);

onBeforeUnmount(() => {
    clearTimeout(searchTimer);
});

function applyFilters() {
    router.get('/admin/pending-registrations', {
        q: search.value !== '' ? search.value : undefined,
        status: status.value === 'all' ? undefined : status.value,
    }, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
}

function purgeExpired() {
    if (!window.confirm('Remove all expired pending registrations? Workspace URLs will become available again.')) {
        return;
    }

    purging.value = true;

    router.post('/admin/pending-registrations/purge-expired', {}, {
        preserveScroll: true,
        onFinish: () => {
            purging.value = false;
        },
    });
}

function removeRegistration(registration) {
    if (!window.confirm(`Remove pending registration for "${registration.organization_name}" and release ${registration.slug}?`)) {
        return;
    }

    deletingIds.value = [...deletingIds.value, registration.id];

    router.delete(`/admin/pending-registrations/${registration.id}`, {
        preserveScroll: true,
        onFinish: () => {
            deletingIds.value = deletingIds.value.filter((id) => id !== registration.id);
        },
    });
}

const statusLabel = (registration) => registration.status === 'expired' ? 'Expired' : 'Awaiting verification';

const statusClass = (registration) => registration.status === 'expired'
    ? 'bg-slate-100 text-slate-700 ring-slate-200 dark:bg-slate-800 dark:text-slate-300 dark:ring-slate-700'
    : 'bg-blue-100 text-blue-700 ring-blue-200 dark:bg-blue-950/40 dark:text-blue-300 dark:ring-blue-900';

const hasFilters = computed(() => Boolean(props.filters.q) || (props.filters.status && props.filters.status !== 'all'));
</script>

<template>
    <Head title="Pending registrations" />
    <AdminLayout>
        <div class="mx-auto max-w-7xl px-4 py-8 sm:px-6">
            <PageHeader
                title="Pending registrations"
                description="Unverified sign-ups waiting for email confirmation. Remove stuck entries to release workspace URLs."
            >
                <template v-if="canManage" #actions>
                    <button
                        type="button"
                        class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-70 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800"
                        :disabled="purging || stats.expired === 0"
                        @click="purgeExpired"
                    >
                        {{ purging ? 'Purging…' : `Purge expired (${stats.expired})` }}
                    </button>
                </template>
            </PageHeader>

            <p class="mb-6 text-sm text-slate-600 dark:text-slate-400">
                Verification links expire after 24 hours. Expired rows stop holding the workspace URL, but remain here until purged.
                The scheduler runs <code class="rounded bg-slate-100 px-1 py-0.5 text-xs dark:bg-slate-900">registrations:purge-expired</code> every 6 hours.
            </p>

            <div class="mb-6 grid gap-4 sm:grid-cols-3">
                <PlatformStatCard label="Total pending" :value="stats.total" />
                <PlatformStatCard label="Awaiting verification" :value="stats.active" tone="blue" />
                <PlatformStatCard label="Expired" :value="stats.expired" tone="amber" />
            </div>

            <div class="mb-4 flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                <div class="relative max-w-md flex-1">
                    <svg class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400 dark:text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-4.35-4.35M10 18a8 8 0 100-16 8 8 0 000 16z" />
                    </svg>
                    <input
                        v-model="search"
                        type="search"
                        placeholder="Search organization, slug, or email"
                        :class="[adminInputClass, 'pl-10']"
                    />
                </div>

                <div class="flex flex-wrap gap-2">
                    <button
                        v-for="filter in statusFilters"
                        :key="filter.value"
                        type="button"
                        class="rounded-full px-3 py-1.5 text-sm font-medium transition"
                        :class="status === filter.value ? 'bg-slate-900 text-white' : 'bg-white text-slate-600 ring-1 ring-slate-200 hover:bg-slate-50 dark:bg-slate-900 dark:text-slate-400 dark:ring-slate-700 dark:hover:bg-slate-800'"
                        @click="status = filter.value"
                    >
                        {{ filter.label }}
                    </button>
                </div>
            </div>

            <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="border-b border-slate-200 bg-slate-50 dark:border-slate-800 dark:bg-slate-950/80">
                            <tr>
                                <th class="px-5 py-3.5 text-left font-medium text-slate-600 dark:text-slate-400">Organization</th>
                                <th class="px-5 py-3.5 text-left font-medium text-slate-600 dark:text-slate-400">Workspace URL</th>
                                <th class="px-5 py-3.5 text-left font-medium text-slate-600 dark:text-slate-400">Admin</th>
                                <th class="px-5 py-3.5 text-left font-medium text-slate-600 dark:text-slate-400">Status</th>
                                <th class="px-5 py-3.5 text-left font-medium text-slate-600 dark:text-slate-400">Expires</th>
                                <th class="px-5 py-3.5 text-left font-medium text-slate-600 dark:text-slate-400">Started</th>
                                <th v-if="canManage" class="px-5 py-3.5 text-right font-medium text-slate-600 dark:text-slate-400">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200 dark:divide-slate-800">
                            <tr v-for="registration in registrations.data" :key="registration.id">
                                <td class="px-5 py-4">
                                    <p class="font-medium text-slate-900 dark:text-slate-100">{{ registration.organization_name }}</p>
                                </td>
                                <td class="px-5 py-4">
                                    <p class="font-mono text-xs text-slate-700 dark:text-slate-300">{{ registration.slug }}</p>
                                    <p class="text-xs text-slate-500 dark:text-slate-400">{{ registration.workspace_url }}</p>
                                </td>
                                <td class="px-5 py-4">
                                    <p class="text-slate-900 dark:text-slate-100">{{ registration.admin_name }}</p>
                                    <p class="text-xs text-slate-500 dark:text-slate-400">{{ registration.admin_email }}</p>
                                </td>
                                <td class="px-5 py-4">
                                    <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-medium ring-1 ring-inset" :class="statusClass(registration)">
                                        {{ statusLabel(registration) }}
                                    </span>
                                </td>
                                <td class="px-5 py-4 text-slate-600 dark:text-slate-400">{{ formatDateTime(registration.expires_at) }}</td>
                                <td class="px-5 py-4 text-slate-600 dark:text-slate-400">{{ formatDateTime(registration.created_at) }}</td>
                                <td v-if="canManage" class="px-5 py-4 text-right">
                                    <button
                                        type="button"
                                        class="rounded-lg border border-red-200 px-3 py-1.5 text-xs font-medium text-red-700 transition hover:bg-red-50 disabled:cursor-not-allowed disabled:opacity-70 dark:border-red-900 dark:text-red-300 dark:hover:bg-red-950/40"
                                        :disabled="deletingIds.includes(registration.id)"
                                        @click="removeRegistration(registration)"
                                    >
                                        {{ deletingIds.includes(registration.id) ? 'Removing…' : 'Remove' }}
                                    </button>
                                </td>
                            </tr>
                            <tr v-if="!registrations.data.length">
                                <td :colspan="canManage ? 7 : 6" class="px-5 py-10 text-center text-slate-500 dark:text-slate-400">
                                    {{ hasFilters ? 'No pending registrations match your filters.' : 'No pending registrations.' }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div v-if="registrations.links?.length > 3" class="border-t border-slate-200 px-5 py-4 dark:border-slate-800">
                    <PaginationLinks :links="registrations.links" />
                </div>
            </div>
        </div>
    </AdminLayout>
</template>
