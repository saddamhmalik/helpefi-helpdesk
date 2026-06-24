<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import { computed } from 'vue';
import AgentLayout from '../../Layouts/AgentLayout.vue';
import AppIconAction from '../../Components/AppIconAction.vue';
import AppRowActions from '../../Components/AppRowActions.vue';
import DataTable from '../../Components/DataTable.vue';
import DataTableMobileCard from '../../Components/ui/DataTableMobileCard.vue';
import AppBadge from '../../Components/ui/AppBadge.vue';
import ServiceDeskNav from '../../Components/ServiceDeskNav.vue';
import PageHeader from '../../Components/PageHeader.vue';
import { useI18n } from 'vue-i18n';
import { useDateTime } from '../../composables/useDateTime.js';

const props = defineProps({
    from: String,
    to: String,
    entries: Array,
    riskOptions: Array,
});

const { formatDateTime, formatDate } = useDateTime();

const { t } = useI18n();

const monthLabel = computed(() => {
    const date = new Date(`${props.from}T00:00:00`);

    return formatDate(date, { month: 'long', year: 'numeric' });
});

const shiftMonth = (delta) => {
    const start = new Date(`${props.from}T00:00:00`);
    start.setMonth(start.getMonth() + delta);

    const end = new Date(start.getFullYear(), start.getMonth() + 1, 0);

    router.get('/service-desk/changes/calendar', {
        from: start.toISOString().slice(0, 10),
        to: end.toISOString().slice(0, 10),
    }, { preserveState: true, replace: true });
};

const riskBadgeClass = (risk) => {
    if (risk === 'critical') return 'bg-red-100 text-red-800 dark:bg-red-950/50 dark:text-red-200';
    if (risk === 'high') return 'bg-orange-100 text-orange-800 dark:bg-orange-950/50 dark:text-orange-200';
    if (risk === 'low') return 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/50 dark:text-emerald-200';

    return 'bg-amber-100 text-amber-800 dark:bg-amber-950/50 dark:text-amber-200';
};

const formatRange = (entry) => {
    if (!entry.planned_start) {
        return t('service_desk.unscheduled');
    }

    const start = new Date(entry.planned_start);
    const end = entry.planned_end ? new Date(entry.planned_end) : null;
    const startText = formatDateTime(start, { month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' });

    if (!end) {
        return startText;
    }

    const endText = formatDateTime(end, { month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' });

    return `${startText} → ${endText}`;
};
</script>

<template>
    <Head :title="$t('service_desk.change_calendar')" />
    <AgentLayout>
        <PageHeader :title="$t('service_desk.change_calendar')" :description="$t('service_desk.scheduled_changes_across_your_service_desk')" />

        <ServiceDeskNav />

        <div class="mb-4 flex items-center justify-between gap-3">
            <button type="button" class="rounded-lg agent-panel px-3 py-1.5 text-sm font-medium agent-text ring-1 agent-border transition agent-hover-surface" @click="shiftMonth(-1)">{{ $t('service_desk.previous') }}</button>
            <h2 class="text-sm font-semibold agent-text">{{ monthLabel }}</h2>
            <button type="button" class="rounded-lg agent-panel px-3 py-1.5 text-sm font-medium agent-text ring-1 agent-border transition agent-hover-surface" @click="shiftMonth(1)">{{ $t('service_desk.next') }}</button>
        </div>

        <DataTable>
            <thead class="agent-panel-muted">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">{{ $t('service_desk.change') }}</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">{{ $t('service_desk.schedule') }}</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">{{ $t('service_desk.risk') }}</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">{{ $t('service_desk.assignee') }}</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">{{ $t('service_desk.status') }}</th>
                    <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">{{ $t('service_desk.actions') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                <tr v-for="entry in entries" :key="entry.id" class="hover:bg-slate-50 dark:hover:bg-slate-800">
                    <td class="px-4 py-3">
                        <Link :href="`/tickets/${entry.ticket_id}`" class="font-medium text-blue-600 hover:text-blue-700 dark:hover:text-blue-300 dark:text-blue-300">
                            {{ entry.number }}
                        </Link>
                        <p class="mt-0.5 max-w-md truncate text-sm text-slate-700 dark:text-slate-300">{{ entry.subject }}</p>
                    </td>
                    <td class="px-4 py-3 text-sm text-slate-600 dark:text-slate-400">{{ formatRange(entry) }}</td>
                    <td class="px-4 py-3">
                        <span class="rounded-full px-2 py-0.5 text-xs font-medium capitalize" :class="riskBadgeClass(entry.risk)">
                            {{ entry.risk }}
                        </span>
                    </td>
                    <td class="px-4 py-3 text-sm text-slate-600 dark:text-slate-400">{{ entry.assignee || '—' }}</td>
                    <td class="px-4 py-3 text-sm text-slate-600 dark:text-slate-400">{{ entry.status || '—' }}</td>
                    <td class="px-4 py-3">
                        <AppRowActions>
                            <AppIconAction
                                icon="view"
                                variant="primary"
                                :label="$t('service_desk.view_ticket')"
                                :href="`/tickets/${entry.ticket_id}`"
                            />
                        </AppRowActions>
                    </td>
                </tr>
                <tr v-if="!entries?.length">
                    <td colspan="6" class="px-4 py-12 text-center text-sm text-slate-500 dark:text-slate-400">{{ $t('service_desk.no_scheduled_changes') }}</td>
                </tr>
            </tbody>
            <template #mobile>
                <DataTableMobileCard v-for="entry in entries" :key="`mobile-${entry.id}`" tag="div">
                    <Link :href="`/tickets/${entry.ticket_id}`" class="block">
                        <p class="font-medium text-blue-600 dark:text-blue-300">{{ entry.number }}</p>
                        <p class="mt-1 text-sm agent-text">{{ entry.subject }}</p>
                        <div class="mt-2 flex flex-wrap items-center gap-2">
                            <AppBadge class="capitalize" :variant="entry.risk === 'high' ? 'error' : entry.risk === 'medium' ? 'warning' : 'default'">{{ entry.risk }}</AppBadge>
                            <span class="text-xs agent-text-muted">{{ entry.status || '—' }}</span>
                        </div>
                        <p class="mt-2 text-xs agent-text-subtle">{{ formatRange(entry) }}</p>
                    </Link>
                </DataTableMobileCard>
                <div v-if="!entries?.length" class="p-6 text-center text-sm agent-text-muted">{{ $t('service_desk.no_scheduled_changes') }}</div>
            </template>
        </DataTable>
    </AgentLayout>
</template>
