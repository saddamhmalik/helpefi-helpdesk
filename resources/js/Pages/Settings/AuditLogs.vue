<script setup>
import { Link, router, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import SettingsPage from '../../Components/SettingsPage.vue';
import { useI18n } from 'vue-i18n';
import { useDateTime } from '../../composables/useDateTime.js';

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
});

const exportUrl = computed(() => {
    const params = new URLSearchParams();

    if (filterForm.event) {
        params.set('event', filterForm.event);
    }

    if (filterForm.search) {
        params.set('search', filterForm.search);
    }

    const query = params.toString();

    return query ? `/settings/audit-logs/export?${query}` : '/settings/audit-logs/export';
});

const applyFilters = () => {
    router.get('/settings/audit-logs', filterForm.data(), { preserveState: true, preserveScroll: true });
};

const clearFilters = () => {
    filterForm.event = '';
    filterForm.search = '';
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
    <SettingsPage
        :title="$t('settings.audit_logs')"
        :description="$t('settings_audit_logs.track_sign-ins_ticket_changes_settings_updates_and_other_activity_acro')"
    >
        <div class="mb-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <div
                v-for="(total, event) in summary"
                :key="event"
                class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm"
            >
                <p class="text-xs font-medium uppercase tracking-wide text-slate-500">{{ $t('settings_audit_logs.7_days') }}</p>
                <p class="mt-1 text-sm font-medium text-slate-900">{{ eventLabel(event) }}</p>
                <p class="mt-2 text-2xl font-semibold text-slate-900">{{ total }}</p>
            </div>
            <div v-if="!Object.keys(summary ?? {}).length" class="rounded-xl border border-slate-200 bg-white p-4 text-sm text-slate-500 shadow-sm sm:col-span-2 lg:col-span-4">
                No audit events recorded in the last 7 days.
            </div>
        </div>

        <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
            <div class="mb-4 flex flex-wrap items-end gap-3">
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">{{ $t('settings_audit_logs.event') }}</label>
                    <input v-model="filterForm.event" type="text" class="rounded-lg border border-slate-300 px-3 py-2 text-sm" placeholder="ticket.updated" />
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">{{ $t('common.search') }}</label>
                    <input v-model="filterForm.search" type="text" class="rounded-lg border border-slate-300 px-3 py-2 text-sm" :placeholder="$t('settings_audit_logs.email_event_subject_id')" />
                </div>
                <button type="button" class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50" @click="applyFilters">{{ $t('settings_audit_logs.filter') }}</button>
                <button type="button" class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50" @click="clearFilters">{{ $t('settings_audit_logs.clear') }}</button>
                <a :href="exportUrl" class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">
                    {{ $t('settings_audit_logs.export_csv') }}
                </a>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead>
                        <tr class="border-b border-slate-200 text-left text-slate-500">
                            <th class="px-3 py-2 font-medium">{{ $t('settings_audit_logs.when') }}</th>
                            <th class="px-3 py-2 font-medium">{{ $t('settings_audit_logs.event') }}</th>
                            <th class="px-3 py-2 font-medium">{{ $t('settings_audit_logs.actor') }}</th>
                            <th class="px-3 py-2 font-medium">{{ $t('settings_audit_logs.subject') }}</th>
                            <th class="px-3 py-2 font-medium">{{ $t('settings_audit_logs.details') }}</th>
                            <th class="px-3 py-2 font-medium">{{ $t('settings_audit_logs.ip') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="log in auditLogs.data" :key="log.id" class="border-b border-slate-100 align-top">
                            <td class="px-3 py-2 whitespace-nowrap text-slate-600">{{ formatDateTime(log.created_at) }}</td>
                            <td class="px-3 py-2">
                                <p class="font-medium text-slate-900">{{ eventLabel(log.event) }}</p>
                                <p class="text-xs text-slate-500">{{ log.event }}</p>
                            </td>
                            <td class="px-3 py-2 text-slate-600">{{ log.user?.name ?? log.user?.email ?? log.actor_email ?? 'System' }}</td>
                            <td class="px-3 py-2 text-slate-600">{{ subjectLabel(log) }}</td>
                            <td class="px-3 py-2">
                                <pre v-if="log.properties" class="max-w-md overflow-x-auto rounded bg-slate-50 p-2 text-xs text-slate-700">{{ formatProperties(log.properties) }}</pre>
                                <span v-else class="text-slate-400">—</span>
                            </td>
                            <td class="px-3 py-2 text-slate-500">{{ log.ip_address ?? '—' }}</td>
                        </tr>
                        <tr v-if="!auditLogs.data?.length">
                            <td colspan="6" class="px-3 py-8 text-center text-slate-500">No audit logs match your filters.</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div v-if="auditLogs.links?.length > 3" class="mt-4 flex flex-wrap gap-1">
                <Link
                    v-for="link in auditLogs.links"
                    :key="link.label"
                    :href="link.url ?? '#'"
                    class="rounded border px-3 py-1 text-sm"
                    :class="link.active ? 'border-blue-600 bg-blue-50 text-blue-700' : 'border-slate-200 text-slate-600 hover:bg-slate-50'"
                    v-html="link.label"
                />
            </div>
        </div>
    </SettingsPage>
</template>
