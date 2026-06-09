<script setup>
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import AdminLayout from '../../../../Layouts/AdminLayout.vue';
import PageHeader from '../../../../Components/PageHeader.vue';
import PaginationLinks from '../../../../Components/PaginationLinks.vue';
import { adminInputClass } from '../../../../composables/usePlatformAdmin.js';

const props = defineProps({
    auditLogs: Object,
    filters: Object,
    eventLabels: Object,
    summary: Object,
});

const filterForm = useForm({
    event: props.filters.event ?? '',
    search: props.filters.search ?? '',
    tenant_id: props.filters.tenant_id ?? '',
});

const exportUrl = computed(() => {
    const params = new URLSearchParams();

    if (filterForm.event) {
        params.set('event', filterForm.event);
    }

    if (filterForm.search) {
        params.set('search', filterForm.search);
    }

    if (filterForm.tenant_id) {
        params.set('tenant_id', filterForm.tenant_id);
    }

    const query = params.toString();

    return query ? `/admin/audit-logs/export?${query}` : '/admin/audit-logs/export';
});

const applyFilters = () => {
    router.get('/admin/audit-logs', filterForm.data(), { preserveState: true, preserveScroll: true });
};

const clearFilters = () => {
    filterForm.event = '';
    filterForm.search = '';
    filterForm.tenant_id = '';
    applyFilters();
};

const eventLabel = (event) => props.eventLabels?.[event] ?? event;

const subjectLabel = (log) => {
    if (!log.subject_type) {
        return '—';
    }

    const type = log.subject_type.split('\\').pop();
    return log.subject_id ? `${type} #${log.subject_id}` : type;
};

const formatProperties = (properties) => {
    if (!properties || !Object.keys(properties).length) {
        return '';
    }

    return JSON.stringify(properties, null, 2);
};
</script>

<template>
    <Head title="Platform audit logs" />
    <AdminLayout>
        <PageHeader
            title="Audit logs"
            description="Track platform admin sign-ins, workspace changes, backups, and other central activity."
        />

        <div class="mb-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <div
                v-for="(total, event) in summary"
                :key="event"
                class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm"
            >
                <p class="text-xs font-medium uppercase tracking-wide text-slate-500">7 days</p>
                <p class="mt-1 text-sm font-medium text-slate-900">{{ eventLabel(event) }}</p>
                <p class="mt-2 text-2xl font-semibold text-slate-900">{{ total }}</p>
            </div>
            <div v-if="!Object.keys(summary ?? {}).length" class="rounded-xl border border-slate-200 bg-white p-4 text-sm text-slate-500 shadow-sm sm:col-span-2 lg:col-span-4">
                No platform audit events recorded in the last 7 days.
            </div>
        </div>

        <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
            <div class="mb-4 flex flex-wrap items-end gap-3">
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">Event</label>
                    <input v-model="filterForm.event" type="text" :class="adminInputClass" placeholder="platform.tenant.updated" />
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">Search</label>
                    <input v-model="filterForm.search" type="text" :class="adminInputClass" placeholder="Email, event, subject id" />
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">Workspace id</label>
                    <input v-model="filterForm.tenant_id" type="text" :class="adminInputClass" placeholder="Tenant UUID" />
                </div>
                <button type="button" class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50" @click="applyFilters">
                    Filter
                </button>
                <button type="button" class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50" @click="clearFilters">
                    Clear
                </button>
                <a :href="exportUrl" class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">
                    Export CSV
                </a>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead>
                        <tr class="border-b border-slate-200 text-left text-slate-500">
                            <th class="px-3 py-2 font-medium">When</th>
                            <th class="px-3 py-2 font-medium">Event</th>
                            <th class="px-3 py-2 font-medium">Actor</th>
                            <th class="px-3 py-2 font-medium">Workspace</th>
                            <th class="px-3 py-2 font-medium">Subject</th>
                            <th class="px-3 py-2 font-medium">Details</th>
                            <th class="px-3 py-2 font-medium">IP</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="log in auditLogs.data" :key="log.id" class="border-b border-slate-100 align-top">
                            <td class="px-3 py-2 whitespace-nowrap text-slate-600">{{ new Date(log.created_at).toLocaleString() }}</td>
                            <td class="px-3 py-2">
                                <p class="font-medium text-slate-900">{{ eventLabel(log.event) }}</p>
                                <p class="text-xs text-slate-500">{{ log.event }}</p>
                            </td>
                            <td class="px-3 py-2 text-slate-600">{{ log.user?.name ?? log.user?.email ?? log.actor_email ?? 'System' }}</td>
                            <td class="px-3 py-2 text-slate-600">{{ log.tenant?.name ?? log.tenant_id ?? '—' }}</td>
                            <td class="px-3 py-2 text-slate-600">{{ subjectLabel(log) }}</td>
                            <td class="px-3 py-2">
                                <pre v-if="log.properties" class="max-w-md overflow-x-auto rounded bg-slate-50 p-2 text-xs text-slate-700">{{ formatProperties(log.properties) }}</pre>
                                <span v-else class="text-slate-400">—</span>
                            </td>
                            <td class="px-3 py-2 text-slate-500">{{ log.ip_address ?? '—' }}</td>
                        </tr>
                        <tr v-if="!auditLogs.data?.length">
                            <td colspan="7" class="px-3 py-8 text-center text-slate-500">No audit logs match your filters.</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div v-if="auditLogs.links?.length > 3" class="mt-4">
                <PaginationLinks
                    :links="auditLogs.links"
                    :from="auditLogs.from"
                    :to="auditLogs.to"
                    :total="auditLogs.total"
                />
            </div>
        </div>
    </AdminLayout>
</template>
