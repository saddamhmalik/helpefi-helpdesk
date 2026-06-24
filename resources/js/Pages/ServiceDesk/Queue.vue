<script setup>
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { computed, watch } from 'vue';
import AgentLayout from '../../Layouts/AgentLayout.vue';
import ServiceDeskNav from '../../Components/ServiceDeskNav.vue';
import PageHeader from '../../Components/PageHeader.vue';
import FilterField from '../../Components/FilterField.vue';
import DataTable from '../../Components/DataTable.vue';
import DataTableMobileCard from '../../Components/ui/DataTableMobileCard.vue';
import PaginationLinks from '../../Components/PaginationLinks.vue';
import StatusBadge from '../../Components/StatusBadge.vue';
import UnreadBadge from '../../Components/UnreadBadge.vue';
import { useI18n } from 'vue-i18n';

const props = defineProps({
    type: Object,
    tickets: Object,
    statuses: Array,
    priorities: Array,
    agents: Array,
    channels: Array,
    departments: Array,
    teams: Array,
    filters: Object,
});

const { t } = useI18n();

const queueBase = computed(() => `/service-desk/queues/${props.type.value}`);

const filterForm = useForm({
    status_id: props.filters?.status_id ?? '',
    priority_id: props.filters?.priority_id ?? '',
    assigned_to: props.filters?.assigned_to ?? '',
    unassigned: props.filters?.unassigned ?? false,
    mine: props.filters?.mine ?? false,
    search: props.filters?.search ?? '',
});

const selectClass = 'w-full rounded-lg border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 px-2.5 py-1.5 text-sm text-slate-800 dark:text-slate-200 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20';

const assigneeFilter = computed({
    get() {
        if (filterForm.mine) {
            return 'mine';
        }

        if (filterForm.unassigned) {
            return 'unassigned';
        }

        return filterForm.assigned_to ? String(filterForm.assigned_to) : '';
    },
    set(value) {
        filterForm.mine = value === 'mine';
        filterForm.unassigned = value === 'unassigned';
        filterForm.assigned_to = value && value !== 'mine' && value !== 'unassigned' ? Number(value) : '';
    },
});

const applyFilters = () => {
    router.get(queueBase.value, {
        status_id: filterForm.status_id || undefined,
        priority_id: filterForm.priority_id || undefined,
        assigned_to: filterForm.assigned_to || undefined,
        unassigned: filterForm.unassigned || undefined,
        mine: filterForm.mine || undefined,
        search: filterForm.search || undefined,
    }, { preserveState: true, replace: true });
};

watch(
    () => props.filters,
    (filters) => {
        filterForm.status_id = filters?.status_id ?? '';
        filterForm.priority_id = filters?.priority_id ?? '';
        filterForm.assigned_to = filters?.assigned_to ?? '';
        filterForm.unassigned = filters?.unassigned ?? false;
        filterForm.mine = filters?.mine ?? false;
        filterForm.search = filters?.search ?? '';
    },
    { deep: true },
);
</script>

<template>
    <Head :title="t('service_desk.queue_title', { singular: type.singular })" />
    <AgentLayout>
        <PageHeader :title="type.label" :description="type.description">
            <template #description>
                {{ $t('service_desk.queue_description', { total: tickets.total, type: type.label.toLowerCase() }) }}
            </template>
        </PageHeader>

        <ServiceDeskNav :active-type="type.value" />

        <div class="mb-4 overflow-hidden rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 shadow-sm">
            <form @submit.prevent="applyFilters" class="grid gap-3 p-4 md:grid-cols-2 xl:grid-cols-5">
                <FilterField :label="$t('service_desk.search')">
                    <input v-model="filterForm.search" type="search" :placeholder="$t('service_desk.subject_or_number')" :class="selectClass" />
                </FilterField>
                <FilterField :label="$t('service_desk.status')">
                    <select v-model="filterForm.status_id" :class="selectClass">
                        <option value="">{{ $t('service_desk.all_statuses') }}</option>
                        <option v-for="status in statuses" :key="status.id" :value="status.id">{{ status.name }}</option>
                    </select>
                </FilterField>
                <FilterField :label="$t('service_desk.priority')">
                    <select v-model="filterForm.priority_id" :class="selectClass">
                        <option value="">{{ $t('service_desk.all_priorities') }}</option>
                        <option v-for="priority in priorities" :key="priority.id" :value="priority.id">{{ priority.name }}</option>
                    </select>
                </FilterField>
                <FilterField :label="$t('service_desk.assignee')">
                    <select v-model="assigneeFilter" :class="selectClass">
                        <option value="">{{ $t('service_desk.all_assignees') }}</option>
                        <option value="mine">{{ $t('service_desk.assigned_to_me') }}</option>
                        <option value="unassigned">{{ $t('service_desk.unassigned') }}</option>
                        <option v-for="agent in agents" :key="agent.id" :value="String(agent.id)">{{ agent.name }}</option>
                    </select>
                </FilterField>
                <div class="flex items-end">
                    <button type="submit" class="w-full rounded-lg bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-800">{{ $t('service_desk.apply_filters') }}</button>
                </div>
            </form>
        </div>

        <DataTable>
            <template #head>
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">{{ $t('service_desk.ticket') }}</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">{{ $t('service_desk.customer') }}</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">{{ $t('service_desk.status') }}</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">{{ $t('service_desk.priority') }}</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">{{ $t('service_desk.assignee') }}</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">{{ $t('service_desk.updated') }}</th>
                </tr>
            </template>
            <template #body>
                <tr v-if="tickets.data.length === 0">
                    <td colspan="6" class="px-4 py-10 text-center text-sm text-slate-500 dark:text-slate-400">
                        {{ $t('service_desk.no_matches_filters', { type: type.label.toLowerCase() }) }}
                    </td>
                </tr>
                <tr v-for="ticket in tickets.data" :key="ticket.id" class="border-t border-slate-100 dark:border-slate-800 hover:bg-slate-50 dark:hover:bg-slate-800 dark:bg-slate-950/80">
                    <td class="px-4 py-3">
                        <Link :href="`/tickets/${ticket.id}`" class="group inline-flex items-center gap-2">
                            <span class="font-medium text-slate-900 dark:text-slate-100 group-hover:text-blue-600">{{ ticket.subject }}</span>
                            <UnreadBadge v-if="ticket.unread_count" :count="ticket.unread_count" />
                        </Link>
                        <p class="text-xs text-slate-500 dark:text-slate-400">{{ ticket.number }}</p>
                    </td>
                    <td class="px-4 py-3 text-sm text-slate-700 dark:text-slate-300">{{ ticket.contact?.name || '—' }}</td>
                    <td class="px-4 py-3">
                        <StatusBadge :label="ticket.status?.name" :color="ticket.status?.color" />
                    </td>
                    <td class="px-4 py-3 text-sm text-slate-700 dark:text-slate-300">{{ ticket.priority?.name || '—' }}</td>
                    <td class="px-4 py-3 text-sm text-slate-700 dark:text-slate-300">{{ ticket.assignee?.name || 'Unassigned' }}</td>
                    <td class="px-4 py-3 text-sm text-slate-500 dark:text-slate-400">{{ ticket.updated_at }}</td>
                </tr>
            </template>
            <template #mobile>
                <DataTableMobileCard v-for="ticket in tickets.data" :key="`mobile-${ticket.id}`" tag="div">
                    <Link :href="`/tickets/${ticket.id}`" class="block">
                        <div class="flex items-center gap-2">
                            <p class="text-sm font-medium agent-text">{{ ticket.subject }}</p>
                            <UnreadBadge v-if="ticket.unread_count" :count="ticket.unread_count" />
                        </div>
                        <p class="mt-1 text-xs agent-text-subtle">{{ ticket.number }}</p>
                        <div class="mt-2 flex flex-wrap items-center gap-2">
                            <StatusBadge :label="ticket.status?.name" :color="ticket.status?.color" />
                            <span class="text-xs agent-text-muted">{{ ticket.priority?.name || '—' }}</span>
                        </div>
                        <p class="mt-2 text-xs agent-text-subtle">{{ ticket.assignee?.name || 'Unassigned' }}</p>
                    </Link>
                </DataTableMobileCard>
                <div v-if="!tickets.data.length" class="p-6 text-center text-sm agent-text-muted">
                    {{ $t('service_desk.no_matches_filters', { type: type.label.toLowerCase() }) }}
                </div>
            </template>
        </DataTable>

        <PaginationLinks :links="tickets.links" class="mt-4" />
    </AgentLayout>
</template>
