<script setup>
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import AdminLayout from '../../../../Layouts/AdminLayout.vue';
import PageHeader from '../../../../Components/PageHeader.vue';
import PaginationLinks from '../../../../Components/PaginationLinks.vue';
import { adminInputClass } from '../../../../composables/usePlatformAdmin.js';
import { useI18n } from 'vue-i18n';
import { useDateTime } from '../../../../composables/useDateTime.js';

const props = defineProps({
    auditLogs: Object,
    filters: Object,
    eventLabels: Object,
    summary: Object,
});

const { formatDateTime } = useDateTime();

const { t } = useI18n();

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
    <Head :title="$t('central.platform_audit_logs')" />
    <AdminLayout>
        <PageHeader
            :title="$t('settings.audit_logs')"
            :description="$t('central.track_platform_admin_sign-ins_workspace_changes_backups_and_other_cent')"
        />

        <div class="mb-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <div
                v-for="(total, event) in summary"
                :key="event"
                class="rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 p-4 shadow-sm"
            >
                <p class="text-xs font-medium uppercase tracking-wide text-slate-500 dark:text-slate-400">{{ $t('central.7_days') }}</p>
                <p class="mt-1 text-sm font-medium text-slate-900 dark:text-slate-100">{{ eventLabel(event) }}</p>
                <p class="mt-2 text-2xl font-semibold text-slate-900 dark:text-slate-100">{{ total }}</p>
            </div>
            <div v-if="!Object.keys(summary ?? {}).length" class="rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 p-4 text-sm text-slate-500 dark:text-slate-400 shadow-sm sm:col-span-2 lg:col-span-4">
                No platform audit events recorded in the last 7 days.
            </div>
        </div>

        <div class="rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 p-6 shadow-sm">
            <div class="mb-4 flex flex-wrap items-end gap-3">
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-300">{{ $t('central.event') }}</label>
                    <input v-model="filterForm.event" type="text" :class="adminInputClass" placeholder="platform.tenant.updated" />
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-300">{{ $t('common.search') }}</label>
                    <input v-model="filterForm.search" type="text" :class="adminInputClass" :placeholder="$t('central.email_event_subject_id')" />
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-300">{{ $t('central.workspace_id') }}</label>
                    <input v-model="filterForm.tenant_id" type="text" :class="adminInputClass" :placeholder="$t('central.tenant_uuid')" />
                </div>
                <button type="button" class="rounded-lg border border-slate-300 dark:border-slate-700 px-4 py-2 text-sm font-medium text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:bg-slate-950 dark:hover:bg-slate-800" @click="applyFilters">{{ $t('central.filter') }}</button>
                <button type="button" class="rounded-lg border border-slate-300 dark:border-slate-700 px-4 py-2 text-sm font-medium text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:bg-slate-950 dark:hover:bg-slate-800" @click="clearFilters">{{ $t('central.clear') }}</button>
                <a :href="exportUrl" class="rounded-lg border border-slate-300 dark:border-slate-700 px-4 py-2 text-sm font-medium text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:bg-slate-950 dark:hover:bg-slate-800">
                    {{ $t('central.export_csv') }}
                </a>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead>
                        <tr class="border-b border-slate-200 dark:border-slate-800 text-left text-slate-500 dark:text-slate-400">
                            <th class="px-3 py-2 font-medium">{{ $t('central.when') }}</th>
                            <th class="px-3 py-2 font-medium">{{ $t('central.event') }}</th>
                            <th class="px-3 py-2 font-medium">{{ $t('central.actor') }}</th>
                            <th class="px-3 py-2 font-medium">{{ $t('settings.groups.workspace') }}</th>
                            <th class="px-3 py-2 font-medium">{{ $t('central.subject') }}</th>
                            <th class="px-3 py-2 font-medium">{{ $t('central.details') }}</th>
                            <th class="px-3 py-2 font-medium">{{ $t('central.ip') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="log in auditLogs.data" :key="log.id" class="border-b border-slate-100 dark:border-slate-800 align-top">
                            <td class="px-3 py-2 whitespace-nowrap text-slate-600 dark:text-slate-400">{{ formatDateTime(log.created_at) }}</td>
                            <td class="px-3 py-2">
                                <p class="font-medium text-slate-900 dark:text-slate-100">{{ eventLabel(log.event) }}</p>
                                <p class="text-xs text-slate-500 dark:text-slate-400">{{ log.event }}</p>
                            </td>
                            <td class="px-3 py-2 text-slate-600 dark:text-slate-400">{{ log.user?.name ?? log.user?.email ?? log.actor_email ?? 'System' }}</td>
                            <td class="px-3 py-2 text-slate-600 dark:text-slate-400">{{ log.tenant?.name ?? log.tenant_id ?? '—' }}</td>
                            <td class="px-3 py-2 text-slate-600 dark:text-slate-400">{{ subjectLabel(log) }}</td>
                            <td class="px-3 py-2">
                                <pre v-if="log.properties" class="max-w-md overflow-x-auto rounded bg-slate-50 dark:bg-slate-950 p-2 text-xs text-slate-700 dark:text-slate-300">{{ formatProperties(log.properties) }}</pre>
                                <span v-else class="text-slate-400 dark:text-slate-500">—</span>
                            </td>
                            <td class="px-3 py-2 text-slate-500 dark:text-slate-400">{{ log.ip_address ?? '—' }}</td>
                        </tr>
                        <tr v-if="!auditLogs.data?.length">
                            <td colspan="7" class="px-3 py-8 text-center text-slate-500 dark:text-slate-400">No audit logs match your filters.</td>
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
