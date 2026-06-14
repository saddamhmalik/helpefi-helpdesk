<script setup>
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import AgentLayout from '../../Layouts/AgentLayout.vue';
import AppModal from '../../Components/AppModal.vue';
import PageHeader from '../../Components/PageHeader.vue';
import FilterField from '../../Components/FilterField.vue';
import DataTable from '../../Components/DataTable.vue';
import PaginationLinks from '../../Components/PaginationLinks.vue';
import StatusBadge from '../../Components/StatusBadge.vue';
import UnreadBadge from '../../Components/UnreadBadge.vue';
import ActiveFilterChips from '../../Components/ActiveFilterChips.vue';
import { useI18n } from 'vue-i18n';
import { useDateTime } from '../../composables/useDateTime.js';

const props = defineProps({
    tickets: Object,
    ticketViews: Array,
    statuses: Array,
    priorities: Array,
    agents: Array,
    channels: Array,
    departments: Array,
    teams: Array,
    userTeams: Array,
    filters: Object,
    activeViewId: Number,
});

const { formatDateTime, formatDate } = useDateTime();

const { t } = useI18n();

const page = usePage();
const currentUserId = computed(() => page.props.auth.user?.id);
const showMoreFilters = ref(hasMoreFiltersActive());
const showSaveView = ref(false);
const showBulkModal = ref(false);
const selectedIds = ref([]);

const bulkForm = useForm({
    action: 'assign',
    assigned_to: '',
    ticket_status_id: '',
    ticket_priority_id: '',
    minutes: 60,
});

const allVisibleSelected = computed(() =>
    props.tickets.data?.length > 0
    && props.tickets.data.every((ticket) => selectedIds.value.includes(ticket.id)),
);

const toggleSelectAll = () => {
    if (allVisibleSelected.value) {
        selectedIds.value = [];
        return;
    }

    selectedIds.value = props.tickets.data.map((ticket) => ticket.id);
};

const toggleTicket = (ticketId) => {
    if (selectedIds.value.includes(ticketId)) {
        selectedIds.value = selectedIds.value.filter((id) => id !== ticketId);
        return;
    }

    selectedIds.value = [...selectedIds.value, ticketId];
};

const openBulkModal = (action) => {
    bulkForm.action = action;
    bulkForm.assigned_to = '';
    bulkForm.ticket_status_id = props.statuses[0]?.id ?? '';
    bulkForm.ticket_priority_id = props.priorities[0]?.id ?? '';
    bulkForm.minutes = 60;
    showBulkModal.value = true;
};

const submitBulk = () => {
    bulkForm.transform((data) => ({
        ...data,
        ticket_ids: selectedIds.value,
        assigned_to: data.action === 'assign' ? (data.assigned_to || null) : undefined,
        ticket_status_id: data.action === 'status' ? data.ticket_status_id : undefined,
        ticket_priority_id: data.action === 'priority' ? data.ticket_priority_id : undefined,
        minutes: data.action === 'snooze' ? data.minutes : undefined,
    })).post('/tickets/bulk', {
        onSuccess: () => {
            selectedIds.value = [];
            showBulkModal.value = false;
        },
    });
};

const filterForm = useForm({
    status_id: props.filters?.status_id ?? '',
    priority_id: props.filters?.priority_id ?? '',
    assigned_to: props.filters?.assigned_to ?? '',
    unassigned: props.filters?.unassigned ?? false,
    mine: props.filters?.mine ?? false,
    channel_id: props.filters?.channel_id ?? '',
    department_id: props.filters?.department_id ?? '',
    team_id: props.filters?.team_id ?? '',
    search: props.filters?.search ?? '',
    contact: props.filters?.contact ?? '',
    created_from: props.filters?.created_from ?? '',
    created_to: props.filters?.created_to ?? '',
    watching: props.filters?.watching ?? false,
});

const saveViewForm = useForm({
    name: '',
    visibility: 'private',
    team_id: '',
    filters: {},
    is_default: false,
});

const inputClass = 'w-full rounded-lg border px-2.5 py-1.5 text-sm agent-input';
const selectClass = `${inputClass} pr-8`;

const filteredTeams = computed(() => {
    if (!filterForm.department_id) {
        return props.teams;
    }

    return props.teams.filter((team) => team.department_id === Number(filterForm.department_id));
});

const personalViews = computed(() =>
    props.ticketViews.filter((view) => view.visibility === 'private' && view.user_id === currentUserId.value),
);

const sharedViews = computed(() =>
    props.ticketViews.filter((view) => view.visibility === 'team'),
);

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

const buildQuery = () => {
    const query = {
        status_id: filterForm.status_id || undefined,
        priority_id: filterForm.priority_id || undefined,
        assigned_to: filterForm.assigned_to || undefined,
        unassigned: filterForm.unassigned || undefined,
        mine: filterForm.mine || undefined,
        channel_id: filterForm.channel_id || undefined,
        department_id: filterForm.department_id || undefined,
        team_id: filterForm.team_id || undefined,
        search: filterForm.search || undefined,
        contact: filterForm.contact || undefined,
        created_from: filterForm.created_from || undefined,
        created_to: filterForm.created_to || undefined,
        watching: filterForm.watching || undefined,
    };

    return Object.fromEntries(Object.entries(query).filter(([, value]) => value !== undefined && value !== false && value !== ''));
};

const activeFilterCount = computed(() => Object.keys(buildQuery()).length);

const exportUrl = computed(() => {
    const params = new URLSearchParams();

    Object.entries(buildQuery()).forEach(([key, value]) => {
        if (value !== undefined && value !== false && value !== '') {
            params.set(key, String(value));
        }
    });

    if (props.activeViewId) {
        params.set('view_id', String(props.activeViewId));
    }

    const query = params.toString();

    return query ? `/tickets/export/csv?${query}` : '/tickets/export/csv';
});

const extraFilterCount = computed(() => {
    let count = 0;

    if (filterForm.channel_id) count += 1;
    if (filterForm.department_id) count += 1;
    if (filterForm.team_id) count += 1;
    if (filterForm.contact) count += 1;
    if (filterForm.created_from) count += 1;
    if (filterForm.created_to) count += 1;
    if (filterForm.watching) count += 1;

    return count;
});

const applyFilters = () => {
    router.get('/tickets', buildQuery(), { preserveState: true, replace: true });
};

const loadView = (viewId) => {
    router.get('/tickets', { view_id: viewId }, { preserveState: true, replace: true });
};

const clearFilters = () => {
    filterForm.reset();
    filterForm.unassigned = false;
    filterForm.mine = false;
    filterForm.watching = false;
    router.get('/tickets', {}, { preserveState: true, replace: true });
};

const removeFilter = (key) => {
    const map = {
        search: () => { filterForm.search = ''; },
        status: () => { filterForm.status_id = ''; },
        priority: () => { filterForm.priority_id = ''; },
        mine: () => { filterForm.mine = false; },
        unassigned: () => { filterForm.unassigned = false; },
        agent: () => { filterForm.assigned_to = ''; },
        channel: () => { filterForm.channel_id = ''; },
        department: () => { filterForm.department_id = ''; filterForm.team_id = ''; },
        team: () => { filterForm.team_id = ''; },
        contact: () => { filterForm.contact = ''; },
        dates: () => { filterForm.created_from = ''; filterForm.created_to = ''; },
        watching: () => { filterForm.watching = false; },
    };

    map[key]?.();
    applyFilters();
};

const openSaveView = () => {
    saveViewForm.filters = buildQuery();
    saveViewForm.name = '';
    saveViewForm.visibility = 'private';
    saveViewForm.team_id = props.userTeams[0]?.id ?? '';
    saveViewForm.is_default = false;
    showSaveView.value = true;
};

const saveView = () => {
    saveViewForm.post('/ticket-views', {
        onSuccess: () => {
            saveViewForm.reset('name');
            showSaveView.value = false;
        },
    });
};

const deleteView = (view) => {
    if (view.user_id !== currentUserId.value) {
        return;
    }

    router.delete(`/ticket-views/${view.id}`, { preserveScroll: true });
};

function hasMoreFiltersActive(source = props.filters) {
    return Boolean(
        source?.channel_id
        || source?.department_id
        || source?.team_id
        || source?.contact
        || source?.created_from
        || source?.created_to
        || source?.watching,
    );
}

watch(
    () => props.filters,
    (filters) => {
        filterForm.status_id = filters?.status_id ?? '';
        filterForm.priority_id = filters?.priority_id ?? '';
        filterForm.assigned_to = filters?.assigned_to ?? '';
        filterForm.unassigned = filters?.unassigned ?? false;
        filterForm.mine = filters?.mine ?? false;
        filterForm.channel_id = filters?.channel_id ?? '';
        filterForm.department_id = filters?.department_id ?? '';
        filterForm.team_id = filters?.team_id ?? '';
        filterForm.search = filters?.search ?? '';
        filterForm.contact = filters?.contact ?? '';
        filterForm.created_from = filters?.created_from ?? '';
        filterForm.created_to = filters?.created_to ?? '';
        filterForm.watching = filters?.watching ?? false;

        if (hasMoreFiltersActive(filters)) {
            showMoreFilters.value = true;
        }
    },
    { deep: true },
);

const labelFor = (type, id) => {
    const lists = {
        status: props.statuses,
        priority: props.priorities,
        agent: props.agents,
        channel: props.channels,
        department: props.departments,
        team: props.teams,
    };

    return lists[type]?.find((item) => Number(item.id) === Number(id))?.name ?? id;
};

const activeFilterLabels = computed(() => {
    const chips = [];

    if (props.filters?.search) {
        chips.push({ key: 'search', label: `"${props.filters.search}"` });
    }

    if (props.filters?.status_id) {
        chips.push({ key: 'status', label: `Status: ${labelFor('status', props.filters.status_id)}` });
    }

    if (props.filters?.priority_id) {
        chips.push({ key: 'priority', label: `Priority: ${labelFor('priority', props.filters.priority_id)}` });
    }

    if (props.filters?.mine) {
        chips.push({ key: 'mine', label: t('tickets.assigned_to_me') });
    } else if (props.filters?.unassigned) {
        chips.push({ key: 'unassigned', label: t('tickets.unassigned') });
    } else if (props.filters?.assigned_to) {
        chips.push({ key: 'agent', label: `Assignee: ${labelFor('agent', props.filters.assigned_to)}` });
    }

    if (props.filters?.channel_id) {
        chips.push({ key: 'channel', label: `Channel: ${labelFor('channel', props.filters.channel_id)}` });
    }

    if (props.filters?.department_id) {
        chips.push({ key: 'department', label: `Department: ${labelFor('department', props.filters.department_id)}` });
    }

    if (props.filters?.team_id) {
        chips.push({ key: 'team', label: `Team: ${labelFor('team', props.filters.team_id)}` });
    }

    if (props.filters?.contact) {
        chips.push({ key: 'contact', label: `Customer: ${props.filters.contact}` });
    }

    if (props.filters?.created_from || props.filters?.created_to) {
        chips.push({
            key: 'dates',
            label: `Created ${props.filters.created_from || '…'} – ${props.filters.created_to || '…'}`,
        });
    }

    if (props.filters?.watching) {
        chips.push({ key: 'watching', label: t('tickets.watching') });
    }

    return chips;
});
</script>

<template>
    <Head :title="$t('tickets.tickets')" />
    <AgentLayout>
        <div class="space-y-4">
        <PageHeader class="!mb-0">
            <template #description>
                {{ tickets.total }} ticket(s)
                <span v-if="activeFilterCount"> with {{ activeFilterCount }} active filter(s)</span>
            </template>
            <template #actions>
                <a
                    :href="exportUrl"
                    class="rounded-lg border border-slate-300 dark:border-slate-700 px-4 py-2 text-sm font-medium text-slate-700 agent-hover-surface dark:border-slate-600 dark:text-slate-300"
                >
                    {{ $t('tickets.export_csv') }}
                </a>
            </template>
        </PageHeader>

        <div v-if="personalViews.length || sharedViews.length" class="flex flex-wrap items-center gap-2">
            <button
                type="button"
                class="rounded-lg px-3 py-1.5 text-sm font-medium transition"
                :class="!activeViewId ? 'bg-slate-900 text-white dark:bg-slate-100 dark:bg-slate-900 dark:text-slate-900 dark:text-slate-100' : 'agent-panel agent-text-muted ring-1 agent-border agent-hover-surface'"
                @click="clearFilters"
            >{{ $t('tickets.all_tickets') }}</button>

            <template v-if="personalViews.length">
                <span class="text-xs font-medium text-slate-400 dark:text-slate-500">|</span>
                <span class="text-xs text-slate-500 dark:text-slate-400">{{ $t('tickets.my_views') }}</span>
                <div
                    v-for="view in personalViews"
                    :key="view.id"
                    class="inline-flex overflow-hidden rounded-lg ring-1 ring-slate-200 dark:ring-slate-700"
                    :class="activeViewId === view.id ? 'bg-slate-900 text-white dark:bg-slate-100 dark:bg-slate-900 dark:text-slate-900 dark:text-slate-100' : 'agent-panel agent-text-muted'"
                >
                    <button type="button" class="px-3 py-1.5 text-sm font-medium" @click="loadView(view.id)">
                        {{ view.name }}
                    </button>
                    <button
                        type="button"
                        class="border-l px-2 text-xs opacity-60 hover:opacity-100"
                        :class="activeViewId === view.id ? 'border-white/20 dark:border-slate-900/20' : 'agent-border'"
                        @click="deleteView(view)"
                    >
                        ×
                    </button>
                </div>
            </template>

            <template v-if="sharedViews.length">
                <span class="text-xs font-medium text-slate-400 dark:text-slate-500">|</span>
                <span class="text-xs text-slate-500 dark:text-slate-400">{{ $t('tickets.team_views') }}</span>
                <div
                    v-for="view in sharedViews"
                    :key="view.id"
                    class="inline-flex overflow-hidden rounded-lg ring-1 ring-slate-200 dark:ring-slate-700"
                    :class="activeViewId === view.id ? 'bg-blue-600 text-white' : 'agent-panel agent-text-muted'"
                >
                    <button type="button" class="inline-flex items-center gap-1.5 px-3 py-1.5 text-sm font-medium" @click="loadView(view.id)">
                        {{ view.name }}
                        <span
                            class="rounded-full px-1.5 py-0.5 text-[10px]"
                            :class="activeViewId === view.id ? 'bg-white/15' : 'bg-blue-50 text-blue-700 dark:bg-blue-950/50 dark:text-blue-300'"
                        >
                            {{ view.team?.name }}
                        </span>
                    </button>
                    <button
                        v-if="view.user_id === currentUserId"
                        type="button"
                        class="border-l px-2 text-xs opacity-60 hover:opacity-100"
                        :class="activeViewId === view.id ? 'border-white/20 dark:border-slate-900/20' : 'agent-border'"
                        @click="deleteView(view)"
                    >
                        ×
                    </button>
                </div>
            </template>
        </div>

        <div class="overflow-hidden rounded-xl border agent-border agent-panel shadow-sm">
            <form @submit.prevent="applyFilters" class="p-3">
                <div class="flex flex-col gap-2 xl:flex-row xl:items-center">
                    <div class="relative min-w-0 flex-1 xl:max-w-xs">
                        <svg class="pointer-events-none absolute left-2.5 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400 dark:text-slate-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                        </svg>
                        <input
                            v-model="filterForm.search"
                            type="search"
                            :placeholder="$t('tickets.search_tickets_ellipsis')"
                            :aria-label="$t('tickets.search_tickets')"
                            class="w-full rounded-lg border py-1.5 pl-9 pr-3 text-sm agent-input"
                        />
                    </div>

                    <div class="grid grid-cols-2 gap-2 sm:grid-cols-3 xl:flex xl:min-w-0 xl:flex-1 xl:items-center xl:gap-2">
                        <select v-model="filterForm.status_id" :aria-label="$t('tickets.status')" :class="selectClass">
                            <option value="">{{ $t('tickets.all_statuses') }}</option>
                            <option v-for="status in statuses" :key="status.id" :value="status.id">{{ status.name }}</option>
                        </select>

                        <select v-model="filterForm.priority_id" :aria-label="$t('tickets.priority')" :class="selectClass">
                            <option value="">{{ $t('tickets.all_priorities') }}</option>
                            <option v-for="priority in priorities" :key="priority.id" :value="priority.id">{{ priority.name }}</option>
                        </select>

                        <select v-model="assigneeFilter" :aria-label="$t('tickets.assignee')" class="col-span-2 sm:col-span-1" :class="selectClass">
                            <option value="">{{ $t('tickets.all_assignees') }}</option>
                            <option value="mine">{{ $t('tickets.assigned_to_me') }}</option>
                            <option value="unassigned">{{ $t('tickets.unassigned') }}</option>
                            <optgroup :label="$t('tickets.agents')">
                                <option v-for="agent in agents" :key="agent.id" :value="String(agent.id)">{{ agent.name }}</option>
                            </optgroup>
                        </select>
                    </div>

                    <div class="flex shrink-0 items-center gap-1.5">
                        <button
                            type="submit"
                            class="rounded-lg bg-slate-900 px-3 py-1.5 text-xs font-semibold text-white transition hover:bg-slate-800"
                        >{{ $t('tickets.apply') }}</button>
                        <button
                            type="button"
                            class="inline-flex items-center gap-1 rounded-lg border agent-border px-2.5 py-1.5 text-xs font-medium agent-text-muted transition agent-hover-surface"
                            @click="showMoreFilters = !showMoreFilters"
                        >
                            <svg class="h-3.5 w-3.5 transition" :class="showMoreFilters ? 'rotate-180' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                            </svg>
                            {{ $t('tickets.more') }}
                            <span
                                v-if="extraFilterCount"
                                class="rounded-full bg-blue-100 px-1.5 py-0.5 text-[10px] font-semibold text-blue-700 dark:text-blue-300"
                            >
                                {{ extraFilterCount }}
                            </span>
                        </button>
                        <button
                            type="button"
                            class="rounded-lg border agent-border px-2.5 py-1.5 text-xs font-medium agent-text-muted transition agent-hover-surface"
                            @click="openSaveView"
                        >{{ $t('tickets.save_view') }}</button>
                    </div>
                </div>

                <div v-if="showMoreFilters" class="mt-3 border-t border-slate-100 dark:border-slate-800 pt-3">
                    <div class="grid gap-2 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6">
                        <select v-model="filterForm.channel_id" :aria-label="$t('tickets.channel')" :class="selectClass">
                            <option value="">{{ $t('tickets.all_channels') }}</option>
                            <option v-for="channel in channels" :key="channel.id" :value="channel.id">{{ channel.name }}</option>
                        </select>

                        <select v-model="filterForm.department_id" :aria-label="$t('tickets.department')" :class="selectClass" @change="filterForm.team_id = ''">
                            <option value="">{{ $t('tickets.all_departments') }}</option>
                            <option v-for="department in departments" :key="department.id" :value="department.id">{{ department.name }}</option>
                        </select>

                        <select v-model="filterForm.team_id" :aria-label="$t('tickets.team')" :class="selectClass">
                            <option value="">{{ $t('tickets.all_teams') }}</option>
                            <option v-for="team in filteredTeams" :key="team.id" :value="team.id">{{ team.name }}</option>
                        </select>

                        <input v-model="filterForm.contact" type="text" :placeholder="$t('tickets.customer')" :aria-label="$t('tickets.customer')" :class="inputClass" />

                        <input v-model="filterForm.created_from" type="date" :aria-label="$t('tickets.created_from')" :class="inputClass" />

                        <input v-model="filterForm.created_to" type="date" :aria-label="$t('tickets.created_to')" :class="inputClass" />
                    </div>

                    <label class="mt-2 flex cursor-pointer items-center gap-2 text-xs font-medium text-slate-600 dark:text-slate-400">
                        <input v-model="filterForm.watching" type="checkbox" class="rounded border-slate-300 dark:border-slate-700 text-blue-600 focus:ring-blue-500/30" />
                        {{ $t('tickets.watching_only') }}
                    </label>
                </div>

                <ActiveFilterChips
                    v-if="activeFilterLabels.length"
                    class="mt-3 border-t border-slate-100 dark:border-slate-800 pt-3"
                    :items="activeFilterLabels"
                    @remove="removeFilter"
                    @clear="clearFilters"
                />
            </form>
        </div>

        <div
            v-if="selectedIds.length"
            class="flex flex-wrap items-center gap-2 rounded-xl border border-blue-200 dark:border-blue-900/60 bg-blue-50 dark:bg-blue-950/40 px-4 py-3"
        >
            <span class="text-sm font-medium text-blue-900">{{ selectedIds.length }} selected</span>
            <button type="button" class="rounded-lg agent-panel px-3 py-1.5 text-xs font-semibold text-slate-700 ring-1 agent-border dark:text-slate-300" @click="openBulkModal('assign')">{{ $t('tickets.assign') }}</button>
            <button type="button" class="rounded-lg agent-panel px-3 py-1.5 text-xs font-semibold text-slate-700 ring-1 agent-border dark:text-slate-300" @click="openBulkModal('status')">{{ $t('tickets.status') }}</button>
            <button type="button" class="rounded-lg agent-panel px-3 py-1.5 text-xs font-semibold text-slate-700 ring-1 agent-border dark:text-slate-300" @click="openBulkModal('priority')">{{ $t('tickets.priority') }}</button>
            <button type="button" class="rounded-lg agent-panel px-3 py-1.5 text-xs font-semibold text-slate-700 ring-1 agent-border dark:text-slate-300" @click="openBulkModal('snooze')">{{ $t('tickets.snooze') }}</button>
            <button type="button" class="rounded-lg bg-slate-900 px-3 py-1.5 text-xs font-semibold text-white" @click="bulkForm.action = 'close'; submitBulk()">{{ $t('tickets.close') }}</button>
            <button type="button" class="ml-auto text-xs font-medium text-blue-700 dark:text-blue-300" @click="selectedIds = []">{{ $t('tickets.clear') }}</button>
        </div>

        <DataTable>
            <thead class="agent-panel-muted">
                <tr>
                    <th class="px-4 py-3">
                        <input
                            type="checkbox"
                            class="rounded border-slate-300 dark:border-slate-700"
                            :checked="allVisibleSelected"
                            @change="toggleSelectAll"
                        >
                    </th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">{{ $t('tickets.ticket') }}</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">{{ $t('tickets.customer') }}</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">{{ $t('tickets.status') }}</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">{{ $t('tickets.priority') }}</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">{{ $t('tickets.channel') }}</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">{{ $t('tickets.assignee') }}</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">{{ $t('tickets.updated') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                <tr v-for="ticket in tickets.data" :key="ticket.id" class="agent-hover-row">
                    <td class="px-4 py-3">
                        <input
                            type="checkbox"
                            class="rounded border-slate-300 dark:border-slate-700"
                            :checked="selectedIds.includes(ticket.id)"
                            @change="toggleTicket(ticket.id)"
                        >
                    </td>
                    <td class="px-4 py-3">
                        <Link :href="`/tickets/${ticket.id}`" class="block">
                            <div class="flex items-center gap-2">
                                <span class="text-sm font-semibold text-blue-600 hover:text-blue-700 dark:hover:text-blue-300 dark:text-blue-300">{{ ticket.number }}</span>
                                <UnreadBadge :count="ticket.unread_count ?? 0" />
                            </div>
                            <span class="mt-0.5 block max-w-md truncate text-sm agent-text">{{ ticket.subject }}</span>
                        </Link>
                    </td>
                    <td class="px-4 py-3">
                        <span class="block text-sm font-medium text-slate-800 dark:text-slate-200">{{ ticket.contact?.name || '—' }}</span>
                        <span v-if="ticket.contact?.email" class="text-xs text-slate-500 dark:text-slate-400">{{ ticket.contact.email }}</span>
                    </td>
                    <td class="px-4 py-3">
                        <StatusBadge :label="ticket.status?.name" :color="ticket.status?.color" />
                    </td>
                    <td class="px-4 py-3 text-sm text-slate-600 dark:text-slate-400">{{ ticket.priority?.name }}</td>
                    <td class="px-4 py-3 text-sm text-slate-600 dark:text-slate-400">{{ ticket.channel?.name || '—' }}</td>
                    <td class="px-4 py-3 text-sm text-slate-600 dark:text-slate-400">{{ ticket.assignee?.name || 'Unassigned' }}</td>
                    <td class="px-4 py-3 text-sm text-slate-500 dark:text-slate-400">{{ formatDate(ticket.updated_at) }}</td>
                </tr>
                <tr v-if="!tickets.data?.length">
                    <td colspan="8" class="px-4 py-12 text-center">
                        <p class="text-sm font-medium text-slate-700 dark:text-slate-300">{{ $t('tickets.no_tickets_match_these_filters') }}</p>
                        <button type="button" class="mt-3 text-sm font-medium text-blue-600 hover:text-blue-700 dark:hover:text-blue-300 dark:text-blue-300" @click="clearFilters">{{ $t('tickets.clear_all_filters') }}</button>
                    </td>
                </tr>
            </tbody>
            <template #footer>
                <PaginationLinks
                    :links="tickets.links"
                    :from="tickets.from"
                    :to="tickets.to"
                    :total="tickets.total"
                />
            </template>
        </DataTable>

        <AppModal
            :open="showSaveView"
            :title="$t('tickets.save_ticket_view')"
            :description="$t('tickets.save_the_current_filters_for_yourself_or_share_them_with_your_team')"
            @close="showSaveView = false"
        >
            <form id="save-ticket-view-form" class="space-y-4" @submit.prevent="saveView">
                <FilterField :label="$t('tickets.view_name')">
                    <input v-model="saveViewForm.name" type="text" required :placeholder="$t('tickets.my_open_tickets')" class="max-w-md" :class="inputClass" />
                </FilterField>

                <FilterField label="Who can use this view?">
                    <div class="flex flex-wrap gap-3">
                        <label class="flex cursor-pointer items-center gap-2 rounded-lg border agent-border px-3 py-2 text-sm dark:text-slate-300">
                            <input v-model="saveViewForm.visibility" type="radio" value="private" />
                            {{ $t('tickets.only_me') }}
                        </label>
                        <label class="flex cursor-pointer items-center gap-2 rounded-lg border border-slate-200 dark:border-slate-800 px-3 py-2 text-sm" :class="!userTeams.length ? 'opacity-50' : ''">
                            <input v-model="saveViewForm.visibility" type="radio" value="team" :disabled="!userTeams.length" />
                            {{ $t('tickets.share_with_team') }}
                        </label>
                    </div>
                </FilterField>

                <FilterField v-if="saveViewForm.visibility === 'team'" :label="$t('tickets.team')">
                    <select v-model="saveViewForm.team_id" required class="max-w-md" :class="inputClass">
                        <option v-for="team in userTeams" :key="team.id" :value="team.id">{{ team.name }}</option>
                    </select>
                </FilterField>

                <label v-if="saveViewForm.visibility === 'private'" class="flex items-center gap-2 text-sm text-slate-700 dark:text-slate-300">
                    <input v-model="saveViewForm.is_default" type="checkbox" class="rounded border-slate-300 dark:border-slate-700" />
                    {{ $t('tickets.set_as_my_default_view') }}
                </label>

                <div v-if="activeFilterLabels.length" class="rounded-lg border agent-border agent-panel-muted px-3 py-3">
                    <p class="text-sm font-medium text-slate-700 dark:text-slate-300">{{ $t('tickets.filters_to_save') }}</p>
                    <div class="mt-2 flex flex-wrap gap-1.5">
                        <span
                            v-for="chip in activeFilterLabels"
                            :key="`save-${chip.key}`"
                            class="rounded-full agent-panel px-2 py-0.5 text-xs agent-text-muted ring-1 agent-border"
                        >
                            {{ chip.label }}
                        </span>
                    </div>
                </div>
            </form>

            <template #footer>
                <div class="flex justify-end gap-2">
                    <button type="button" class="rounded-lg border border-slate-300 dark:border-slate-700 px-4 py-2 text-sm font-medium text-slate-700 dark:text-slate-300" @click="showSaveView = false">{{ $t('tickets.cancel') }}</button>
                    <button type="submit" form="save-ticket-view-form" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700" :disabled="saveViewForm.processing">{{ $t('tickets.save_view') }}</button>
                </div>
            </template>
        </AppModal>

        <AppModal
            :open="showBulkModal"
            :title="`Bulk ${bulkForm.action}`"
            :description="`Apply to ${selectedIds.length} ticket(s).`"
            @close="showBulkModal = false"
        >
            <form id="bulk-ticket-form" class="space-y-4" @submit.prevent="submitBulk">
                <div v-if="bulkForm.action === 'assign'">
                    <label class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-300">{{ $t('tickets.assignee') }}</label>
                    <select v-model="bulkForm.assigned_to" :class="selectClass">
                        <option value="">{{ $t('tickets.unassigned') }}</option>
                        <option v-for="agent in agents" :key="agent.id" :value="agent.id">{{ agent.name }}</option>
                    </select>
                </div>

                <div v-if="bulkForm.action === 'status'">
                    <label class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-300">{{ $t('tickets.status') }}</label>
                    <select v-model="bulkForm.ticket_status_id" :class="selectClass" required>
                        <option v-for="status in statuses" :key="status.id" :value="status.id">{{ status.name }}</option>
                    </select>
                </div>

                <div v-if="bulkForm.action === 'priority'">
                    <label class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-300">{{ $t('tickets.priority') }}</label>
                    <select v-model="bulkForm.ticket_priority_id" :class="selectClass" required>
                        <option v-for="priority in priorities" :key="priority.id" :value="priority.id">{{ priority.name }}</option>
                    </select>
                </div>

                <div v-if="bulkForm.action === 'snooze'">
                    <label class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-300">{{ $t('tickets.snooze_for_minutes') }}</label>
                    <input v-model.number="bulkForm.minutes" type="number" min="15" max="10080" :class="inputClass" required />
                </div>

                <p v-if="bulkForm.action === 'close'" class="text-sm text-slate-600 dark:text-slate-400">{{ $t('tickets.close_all_selected_tickets') }}</p>
            </form>

            <template #footer>
                <div class="flex justify-end gap-2">
                    <button type="button" class="rounded-lg border border-slate-300 dark:border-slate-700 px-4 py-2 text-sm font-medium text-slate-700 dark:text-slate-300" @click="showBulkModal = false">{{ $t('tickets.cancel') }}</button>
                    <button type="submit" form="bulk-ticket-form" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700" :disabled="bulkForm.processing">{{ $t('tickets.apply') }}</button>
                </div>
            </template>
        </AppModal>
        </div>
    </AgentLayout>
</template>
