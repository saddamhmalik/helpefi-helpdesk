<script setup>
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import AgentLayout from '../../Layouts/AgentLayout.vue';
import PageHeader from '../../Components/PageHeader.vue';
import ListPanel from '../../Components/ListPanel.vue';
import FilterField from '../../Components/FilterField.vue';
import DataTable from '../../Components/DataTable.vue';
import DataTableMobileCard from '../../Components/ui/DataTableMobileCard.vue';
import StatusBadge from '../../Components/StatusBadge.vue';
import { useI18n } from 'vue-i18n';
import { useDateTime } from '../../composables/useDateTime.js';

const props = defineProps({
    reportTypes: Array,
    savedReports: Array,
    scheduleOptions: Object,
    statuses: Array,
    priorities: Array,
    agents: Array,
    teams: { type: Array, default: () => [] },
    filters: Object,
    activeType: String,
    result: Object,
});

const { formatDateTime } = useDateTime();

const { t } = useI18n();

const filterForm = useForm({
    type: props.activeType ?? 'tickets',
    date_from: props.filters?.date_from ?? '',
    date_to: props.filters?.date_to ?? '',
    status_id: props.filters?.status_id ?? '',
    priority_id: props.filters?.priority_id ?? '',
    assigned_to: props.filters?.assigned_to ?? '',
    team_id: props.filters?.team_id ?? '',
});

const showSave = ref(false);
const showSchedule = ref(false);
const schedulingReport = ref(null);

const scheduleForm = useForm({
    frequency: 'weekly',
    weekday: 1,
    send_hour: 8,
    format: 'csv',
    is_enabled: true,
});

const saveForm = useForm({
    name: '',
    type: props.activeType ?? 'tickets',
    filters: {},
    is_default: false,
});

const inputClass = 'w-full rounded-lg border px-3 py-2 text-sm agent-input';

const isAgentReport = computed(() => props.result?.format === 'agents');
const isCsatReport = computed(() => props.result?.format === 'csat');
const isTimeTrackingReport = computed(() => props.result?.format === 'time_tracking');

const ticketRows = computed(() => {
    if (!props.result || isAgentReport.value || isCsatReport.value || isTimeTrackingReport.value) {
        return [];
    }
    return props.result.rows?.data ?? [];
});

const csatRows = computed(() => (isCsatReport.value ? props.result?.rows?.data ?? [] : []));

const agentRows = computed(() => (isAgentReport.value ? props.result?.rows ?? [] : []));
const timeAgentRows = computed(() => (isTimeTrackingReport.value ? props.result?.agents ?? [] : []));
const timeTeamRows = computed(() => (isTimeTrackingReport.value ? props.result?.teams ?? [] : []));

const formatMinutes = (minutes) => {
    const hours = Math.floor(minutes / 60);
    const remainder = minutes % 60;

    if (!hours) {
        return `${remainder}m`;
    }

    return remainder ? `${hours}h ${remainder}m` : `${hours}h`;
};

const exportUrl = computed(() => {
    const params = new URLSearchParams();
    params.set('type', filterForm.type);
    if (filterForm.date_from) params.set('date_from', filterForm.date_from);
    if (filterForm.date_to) params.set('date_to', filterForm.date_to);
    if (filterForm.status_id) params.set('status_id', filterForm.status_id);
    if (filterForm.priority_id) params.set('priority_id', filterForm.priority_id);
    if (filterForm.assigned_to) params.set('assigned_to', filterForm.assigned_to);
    if (filterForm.team_id) params.set('team_id', filterForm.team_id);
    return `/reports/export?${params.toString()}`;
});

const runReport = () => {
    router.get('/reports', {
        type: filterForm.type,
        date_from: filterForm.date_from || undefined,
        date_to: filterForm.date_to || undefined,
        status_id: filterForm.status_id || undefined,
        priority_id: filterForm.priority_id || undefined,
        assigned_to: filterForm.assigned_to || undefined,
        team_id: filterForm.team_id || undefined,
        run: 1,
    }, { preserveState: true, replace: true });
};

const loadSaved = (report) => {
    filterForm.type = report.type;
    filterForm.date_from = report.filters?.date_from ?? '';
    filterForm.date_to = report.filters?.date_to ?? '';
    filterForm.status_id = report.filters?.status_id ?? '';
    filterForm.priority_id = report.filters?.priority_id ?? '';
    filterForm.assigned_to = report.filters?.assigned_to ?? '';
    filterForm.team_id = report.filters?.team_id ?? '';
    runReport();
};

const openSave = () => {
    saveForm.type = filterForm.type;
    saveForm.filters = {
        date_from: filterForm.date_from || null,
        date_to: filterForm.date_to || null,
        status_id: filterForm.status_id || null,
        priority_id: filterForm.priority_id || null,
        assigned_to: filterForm.assigned_to || null,
        team_id: filterForm.team_id || null,
    };
    saveForm.name = '';
    showSave.value = true;
};

const saveReport = () => {
    saveForm.post('/reports', {
        onSuccess: () => {
            saveForm.reset('name');
            showSave.value = false;
        },
    });
};

const deleteSaved = (reportId) => {
    router.delete(`/reports/${reportId}`, { preserveScroll: true });
};

const scheduleSummary = (report) => {
    if (!report.schedule?.is_enabled) {
        return '';
    }

    const hour = scheduleOptions?.hours?.find((item) => item.value === report.schedule.send_hour)?.label ?? `${report.schedule.send_hour}:00`;
    const frequency = report.schedule.frequency === 'weekly' ? 'Weekly' : 'Daily';
    const day = report.schedule.frequency === 'weekly'
        ? scheduleOptions?.weekdays?.find((item) => item.value === report.schedule.weekday)?.label
        : null;

    return day ? `${frequency} · ${day} · ${hour}` : `${frequency} · ${hour}`;
};

const openSchedule = (report) => {
    schedulingReport.value = report;
    scheduleForm.frequency = report.schedule?.frequency ?? 'weekly';
    scheduleForm.weekday = report.schedule?.weekday ?? 1;
    scheduleForm.send_hour = report.schedule?.send_hour ?? 8;
    scheduleForm.format = report.schedule?.format ?? 'csv';
    scheduleForm.is_enabled = report.schedule?.is_enabled ?? true;
    showSchedule.value = true;
};

const saveSchedule = () => {
    if (!schedulingReport.value) {
        return;
    }

    scheduleForm.put(`/reports/${schedulingReport.value.id}/schedule`, {
        preserveScroll: true,
        onSuccess: () => {
            showSchedule.value = false;
            schedulingReport.value = null;
        },
    });
};

const removeSchedule = () => {
    if (!schedulingReport.value) {
        return;
    }

    router.delete(`/reports/${schedulingReport.value.id}/schedule`, {
        preserveScroll: true,
        onSuccess: () => {
            showSchedule.value = false;
            schedulingReport.value = null;
        },
    });
};

const formatDate = (value) => value ? formatDateTime(value) : '—';
</script>

<template>
    <Head :title="$t('reports.reports')" />
    <AgentLayout>
        <PageHeader :description="$t('reports.run_save_and_export_helpdesk_reports')" />

        <div v-if="savedReports.length" class="mb-4 flex flex-wrap gap-2">
            <div
                v-for="saved in savedReports"
                :key="saved.id"
                class="group flex items-center gap-1 rounded-lg agent-panel px-2 py-1.5 text-sm font-medium agent-text-muted ring-1 agent-border agent-hover-surface"
            >
                <button type="button" class="px-1" @click="loadSaved(saved)">
                    {{ saved.name }}
                    <span v-if="saved.schedule?.is_enabled" class="ml-1 rounded bg-blue-50 dark:bg-blue-950/40 px-1.5 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-blue-700 dark:text-blue-300">{{ $t('reports.scheduled') }}</span>
                </button>
                <button
                    type="button"
                    class="rounded px-1.5 py-0.5 text-xs text-slate-400 dark:text-slate-500 agent-hover-surface hover:text-slate-700 dark:hover:text-slate-300 dark:text-slate-300"
                    :title="$t('reports.email_schedule')"
                    @click="openSchedule(saved)"
                >
                    ⏱
                </button>
                <button
                    type="button"
                    class="rounded px-1.5 py-0.5 text-xs text-slate-400 dark:text-slate-500 opacity-0 agent-hover-surface hover:text-slate-700 dark:hover:text-slate-300 dark:text-slate-300 group-hover:opacity-100"
                    @click="deleteSaved(saved.id)"
                >
                    ×
                </button>
            </div>
        </div>

        <ListPanel v-if="showSchedule" class="mb-4" :title="$t('reports.email_schedule')" :description="schedulingReport ? `Deliver “${schedulingReport.name}” to your inbox.` : ''">
            <form @submit.prevent="saveSchedule">
                <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                    <FilterField :label="$t('reports.frequency')">
                        <select v-model="scheduleForm.frequency" :class="inputClass">
                            <option v-for="option in scheduleOptions?.frequencies ?? []" :key="option.value" :value="option.value">{{ option.label }}</option>
                        </select>
                    </FilterField>

                    <FilterField v-if="scheduleForm.frequency === 'weekly'" :label="$t('reports.day')">
                        <select v-model="scheduleForm.weekday" :class="inputClass">
                            <option v-for="option in scheduleOptions?.weekdays ?? []" :key="option.value" :value="option.value">{{ option.label }}</option>
                        </select>
                    </FilterField>

                    <FilterField :label="$t('reports.send_time')">
                        <select v-model="scheduleForm.send_hour" :class="inputClass">
                            <option v-for="option in scheduleOptions?.hours ?? []" :key="option.value" :value="option.value">{{ option.label }}</option>
                        </select>
                    </FilterField>

                    <FilterField :label="$t('reports.attachment_format')">
                        <select v-model="scheduleForm.format" :class="inputClass">
                            <option v-for="option in scheduleOptions?.formats ?? []" :key="option.value" :value="option.value">{{ option.label }}</option>
                        </select>
                    </FilterField>
                </div>

                <label class="mt-4 flex items-center gap-2 text-sm agent-text-muted">
                    <input v-model="scheduleForm.is_enabled" type="checkbox" class="rounded agent-border" />
                    Enable scheduled delivery
                </label>

                <p v-if="schedulingReport?.schedule?.is_enabled" class="mt-2 text-xs agent-text-subtle">
                    Current: {{ scheduleSummary(schedulingReport) }}
                </p>

                <div class="mt-4 flex flex-wrap gap-2">
                    <button type="submit" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700" :disabled="scheduleForm.processing">{{ $t('reports.save_schedule') }}</button>
                    <button v-if="schedulingReport?.schedule" type="button" class="agent-btn-secondary" @click="removeSchedule">{{ $t('reports.remove_schedule') }}</button>
                    <button type="button" class="agent-btn-secondary" @click="showSchedule = false">{{ $t('reports.cancel') }}</button>
                </div>
            </form>
        </ListPanel>

        <ListPanel class="mb-6" :title="$t('reports.report_options')" :description="$t('reports.choose_a_report_type_set_filters_then_run_the_report')">
            <form @submit.prevent="runReport">
                <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                    <FilterField :label="$t('reports.report_type')" class="xl:col-span-2">
                        <select v-model="filterForm.type" :class="inputClass">
                            <option v-for="reportType in reportTypes" :key="reportType.value" :value="reportType.value">{{ reportType.label }}</option>
                        </select>
                    </FilterField>

                    <FilterField :label="$t('reports.assignee')">
                        <select v-model="filterForm.assigned_to" :class="inputClass">
                            <option value="">{{ $t('reports.all_assignees') }}</option>
                            <option v-for="agent in agents" :key="agent.id" :value="agent.id">{{ agent.name }}</option>
                        </select>
                    </FilterField>

                    <FilterField :label="$t('reports.date_from')">
                        <input v-model="filterForm.date_from" type="date" :class="inputClass" />
                    </FilterField>

                    <FilterField :label="$t('reports.date_to')">
                        <input v-model="filterForm.date_to" type="date" :class="inputClass" />
                    </FilterField>

                    <FilterField v-if="filterForm.type === 'time_tracking'" :label="$t('reports.team')">
                        <select v-model="filterForm.team_id" :class="inputClass">
                            <option value="">{{ $t('reports.all_teams') }}</option>
                            <option v-for="team in teams" :key="team.id" :value="team.id">{{ team.name }}</option>
                        </select>
                    </FilterField>

                    <FilterField v-if="filterForm.type !== 'agent_performance' && filterForm.type !== 'csat' && filterForm.type !== 'time_tracking'" :label="$t('reports.status')">
                        <select v-model="filterForm.status_id" :class="inputClass">
                            <option value="">{{ $t('reports.all_statuses') }}</option>
                            <option v-for="status in statuses" :key="status.id" :value="status.id">{{ status.name }}</option>
                        </select>
                    </FilterField>

                    <FilterField v-if="filterForm.type === 'tickets'" :label="$t('reports.priority')">
                        <select v-model="filterForm.priority_id" :class="inputClass">
                            <option value="">{{ $t('reports.all_priorities') }}</option>
                            <option v-for="priority in priorities" :key="priority.id" :value="priority.id">{{ priority.name }}</option>
                        </select>
                    </FilterField>
                </div>

                <div class="mt-4 flex flex-wrap items-center gap-2">
                    <button type="submit" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">{{ $t('reports.run_report') }}</button>
                    <a v-if="result" :href="exportUrl" class="agent-btn-secondary">{{ $t('reports.export_csv') }}</a>
                    <button v-if="result" type="button" class="agent-btn-secondary" @click="openSave">{{ $t('reports.save_report') }}</button>
                </div>
            </form>

            <form v-if="showSave" class="mt-5 border-t agent-border-subtle pt-5" @submit.prevent="saveReport">
                <div class="grid gap-4 md:grid-cols-2">
                    <FilterField :label="$t('reports.report_name')">
                        <input v-model="saveForm.name" type="text" required :placeholder="$t('reports.weekly_open_tickets')" :class="inputClass" />
                    </FilterField>
                    <label class="flex items-center gap-2 self-end pb-2 text-sm agent-text-muted">
                        <input v-model="saveForm.is_default" type="checkbox" class="rounded agent-border" />
                        Set as default
                    </label>
                </div>
                <button type="submit" class="mt-3 rounded-lg bg-slate-900 px-4 py-2 text-sm font-medium text-white" :disabled="saveForm.processing">{{ $t('reports.save') }}</button>
            </form>
        </ListPanel>

        <div v-if="result && isTimeTrackingReport" class="mb-6 grid gap-4 sm:grid-cols-2">
            <div class="agent-stat-card">
                <p class="text-sm agent-text-subtle">{{ $t('reports.total_minutes') }}</p>
                <p class="mt-2 text-3xl font-semibold agent-text">{{ formatMinutes(result.summary?.total_minutes ?? 0) }}</p>
            </div>
            <div class="agent-stat-card">
                <p class="text-sm agent-text-subtle">{{ $t('reports.entries_logged') }}</p>
                <p class="mt-2 text-3xl font-semibold agent-text">{{ result.summary?.entry_count ?? 0 }}</p>
            </div>
        </div>

        <div v-if="result && isCsatReport" class="mb-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
            <div class="agent-stat-card">
                <p class="text-sm agent-text-subtle">{{ $t('reports.average_rating') }}</p>
                <p class="mt-2 text-3xl font-semibold agent-text">{{ result.summary?.average_rating ?? '—' }}</p>
            </div>
            <div class="agent-stat-card">
                <p class="text-sm agent-text-subtle">{{ $t('reports.total_responses') }}</p>
                <p class="mt-2 text-3xl font-semibold agent-text">{{ result.summary?.total_responses ?? 0 }}</p>
            </div>
            <div class="agent-stat-card">
                <p class="text-sm agent-text-subtle">{{ $t('reports.portal_responses') }}</p>
                <p class="mt-2 text-3xl font-semibold agent-text">{{ result.summary?.by_channel?.portal ?? 0 }}</p>
            </div>
            <div class="agent-stat-card">
                <p class="text-sm agent-text-subtle">{{ $t('reports.email_responses') }}</p>
                <p class="mt-2 text-3xl font-semibold agent-text">{{ result.summary?.by_channel?.email ?? 0 }}</p>
            </div>
            <div class="agent-stat-card">
                <p class="text-sm agent-text-subtle">{{ $t('reports.5-star_ratings') }}</p>
                <p class="mt-2 text-3xl font-semibold agent-text">{{ result.summary?.breakdown?.[5] ?? 0 }}</p>
            </div>
        </div>

        <template v-if="result && isTimeTrackingReport">
            <DataTable class="mb-6">
                <thead class="agent-panel-muted">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide agent-text-subtle">{{ $t('reports.agent') }}</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide agent-text-subtle">{{ $t('reports.total_time') }}</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide agent-text-subtle">{{ $t('reports.entries') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y agent-table-divider">
                    <tr v-for="row in timeAgentRows" :key="row.agent_id" class="agent-hover-surface">
                        <td class="px-4 py-3 text-sm font-medium agent-text">{{ row.agent_name }}</td>
                        <td class="px-4 py-3 text-sm agent-text-muted">{{ formatMinutes(row.total_minutes) }}</td>
                        <td class="px-4 py-3 text-sm agent-text-muted">{{ row.entry_count }}</td>
                    </tr>
                    <tr v-if="!timeAgentRows.length">
                        <td colspan="3" class="px-4 py-12 text-center text-sm agent-text-subtle">No agent time logged for this period.</td>
                    </tr>
                </tbody>
            </DataTable>

            <DataTable>
                <thead class="agent-panel-muted">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide agent-text-subtle">{{ $t('reports.team') }}</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide agent-text-subtle">{{ $t('reports.total_time') }}</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide agent-text-subtle">{{ $t('reports.entries') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y agent-table-divider">
                    <tr v-for="row in timeTeamRows" :key="row.team_id" class="agent-hover-surface">
                        <td class="px-4 py-3 text-sm font-medium agent-text">{{ row.team_name }}</td>
                        <td class="px-4 py-3 text-sm agent-text-muted">{{ formatMinutes(row.total_minutes) }}</td>
                        <td class="px-4 py-3 text-sm agent-text-muted">{{ row.entry_count }}</td>
                    </tr>
                    <tr v-if="!timeTeamRows.length">
                        <td colspan="3" class="px-4 py-12 text-center text-sm agent-text-subtle">No team time logged for this period.</td>
                    </tr>
                </tbody>
            </DataTable>
        </template>

        <DataTable v-else-if="result && isAgentReport">
            <thead class="agent-panel-muted">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide agent-text-subtle">{{ $t('reports.agent') }}</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide agent-text-subtle">{{ $t('reports.open') }}</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide agent-text-subtle">{{ $t('reports.closed') }}</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide agent-text-subtle">{{ $t('reports.total') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y agent-table-divider">
                <tr v-for="row in agentRows" :key="row.agent_id" class="agent-hover-surface">
                    <td class="px-4 py-3 text-sm font-medium agent-text">{{ row.agent_name }}</td>
                    <td class="px-4 py-3 text-sm agent-text-muted">{{ row.open_count }}</td>
                    <td class="px-4 py-3 text-sm agent-text-muted">{{ row.closed_count }}</td>
                    <td class="px-4 py-3 text-sm agent-text-muted">{{ row.total_count }}</td>
                </tr>
                <tr v-if="!agentRows.length">
                    <td colspan="4" class="px-4 py-12 text-center text-sm agent-text-subtle">No data for this report.</td>
                </tr>
            </tbody>
            <template #mobile>
                <DataTableMobileCard v-for="row in agentRows" :key="`mobile-agent-${row.agent_id}`" tag="div">
                    <p class="text-sm font-medium agent-text">{{ row.agent_name }}</p>
                    <div class="mt-2 grid grid-cols-3 gap-2 text-center text-xs">
                        <div class="rounded-lg agent-panel-muted p-2">
                            <p class="font-semibold agent-text">{{ row.open_count }}</p>
                            <p class="agent-text-subtle">{{ $t('reports.open') }}</p>
                        </div>
                        <div class="rounded-lg agent-panel-muted p-2">
                            <p class="font-semibold agent-text">{{ row.closed_count }}</p>
                            <p class="agent-text-subtle">{{ $t('reports.closed') }}</p>
                        </div>
                        <div class="rounded-lg agent-panel-muted p-2">
                            <p class="font-semibold agent-text">{{ row.total_count }}</p>
                            <p class="agent-text-subtle">{{ $t('reports.total') }}</p>
                        </div>
                    </div>
                </DataTableMobileCard>
                <div v-if="!agentRows.length" class="p-6 text-center text-sm agent-text-subtle">No data for this report.</div>
            </template>
        </DataTable>

        <DataTable v-else-if="result && isCsatReport">
            <thead class="agent-panel-muted">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide agent-text-subtle">{{ $t('reports.ticket') }}</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide agent-text-subtle">{{ $t('reports.contact') }}</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide agent-text-subtle">{{ $t('reports.rating') }}</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide agent-text-subtle">{{ $t('reports.channel') }}</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide agent-text-subtle">{{ $t('reports.comment') }}</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide agent-text-subtle">{{ $t('reports.assignee') }}</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide agent-text-subtle">{{ $t('reports.submitted') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y agent-table-divider">
                <tr v-for="row in csatRows" :key="row.id" class="agent-hover-surface">
                    <td class="px-4 py-3 text-sm font-medium">
                        <Link v-if="row.ticket" :href="`/tickets/${row.ticket.id}`" class="text-blue-600 hover:text-blue-700 dark:text-blue-300 dark:hover:text-blue-300">{{ row.ticket.number }}</Link>
                    </td>
                    <td class="px-4 py-3 text-sm agent-text-muted">{{ row.contact?.name || '—' }}</td>
                    <td class="px-4 py-3 text-sm agent-text">{{ row.rating }}/5</td>
                    <td class="px-4 py-3 text-sm capitalize agent-text-muted">{{ row.channel || 'portal' }}</td>
                    <td class="px-4 py-3 text-sm agent-text-muted">{{ row.comment || '—' }}</td>
                    <td class="px-4 py-3 text-sm agent-text-muted">{{ row.ticket?.assignee?.name || '—' }}</td>
                    <td class="px-4 py-3 text-sm agent-text-subtle">{{ formatDate(row.created_at) }}</td>
                </tr>
                <tr v-if="!csatRows.length">
                    <td colspan="7" class="px-4 py-12 text-center text-sm agent-text-subtle">No CSAT responses for this period.</td>
                </tr>
            </tbody>
        </DataTable>

        <DataTable v-else-if="result">
            <thead class="agent-panel-muted">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide agent-text-subtle">{{ $t('reports.number') }}</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide agent-text-subtle">{{ $t('reports.subject') }}</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide agent-text-subtle">{{ $t('reports.contact') }}</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide agent-text-subtle">{{ $t('reports.status') }}</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide agent-text-subtle">{{ $t('reports.priority') }}</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide agent-text-subtle">{{ $t('reports.assignee') }}</th>
                    <th v-if="activeType === 'sla_breaches'" class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide agent-text-subtle">{{ $t('reports.breaches') }}</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide agent-text-subtle">{{ $t('reports.created') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y agent-table-divider">
                <tr v-for="ticket in ticketRows" :key="ticket.id" class="agent-hover-surface">
                    <td class="px-4 py-3 text-sm font-medium">
                        <Link :href="`/tickets/${ticket.id}`" class="text-blue-600 hover:text-blue-700 dark:hover:text-blue-300 dark:text-blue-300">{{ ticket.number }}</Link>
                    </td>
                    <td class="px-4 py-3 text-sm agent-text">{{ ticket.subject }}</td>
                    <td class="px-4 py-3 text-sm agent-text-muted">{{ ticket.contact?.name || '—' }}</td>
                    <td class="px-4 py-3">
                        <StatusBadge :label="ticket.status?.name" :color="ticket.status?.color" />
                    </td>
                    <td class="px-4 py-3 text-sm agent-text-muted">{{ ticket.priority?.name }}</td>
                    <td class="px-4 py-3 text-sm agent-text-muted">{{ ticket.assignee?.name || '—' }}</td>
                    <td v-if="activeType === 'sla_breaches'" class="px-4 py-3 text-sm agent-text-muted">
                        <span v-if="ticket.sla_timer?.first_response_breached" class="mr-1 rounded bg-red-100 px-1.5 py-0.5 text-xs text-red-800 dark:text-red-200">{{ $t('reports.fr') }}</span>
                        <span v-if="ticket.sla_timer?.resolution_breached" class="rounded bg-red-100 px-1.5 py-0.5 text-xs text-red-800 dark:text-red-200">{{ $t('reports.res') }}</span>
                    </td>
                    <td class="px-4 py-3 text-sm agent-text-subtle">{{ formatDate(ticket.created_at) }}</td>
                </tr>
                <tr v-if="!ticketRows.length">
                    <td :colspan="activeType === 'sla_breaches' ? 8 : 7" class="px-4 py-12 text-center text-sm agent-text-subtle">No data for this report.</td>
                </tr>
            </tbody>
            <template #mobile>
                <DataTableMobileCard v-for="ticket in ticketRows" :key="`mobile-ticket-${ticket.id}`" tag="div">
                    <Link :href="`/tickets/${ticket.id}`" class="block">
                        <p class="text-sm font-semibold text-blue-600 dark:text-blue-300">{{ ticket.number }}</p>
                        <p class="mt-1 text-sm agent-text">{{ ticket.subject }}</p>
                        <div class="mt-2 flex flex-wrap items-center gap-2">
                            <StatusBadge :label="ticket.status?.name" :color="ticket.status?.color" />
                            <span class="text-xs agent-text-muted">{{ ticket.priority?.name }}</span>
                        </div>
                        <p class="mt-2 text-xs agent-text-subtle">{{ ticket.assignee?.name || '—' }} · {{ formatDate(ticket.created_at) }}</p>
                    </Link>
                </DataTableMobileCard>
                <div v-if="!ticketRows.length" class="p-6 text-center text-sm agent-text-subtle">No data for this report.</div>
            </template>
        </DataTable>

        <div v-else class="rounded-xl border border-dashed agent-border agent-panel px-6 py-12 text-center text-sm agent-text-subtle">
            Choose a report type and filters, then click Run report.
        </div>
    </AgentLayout>
</template>
