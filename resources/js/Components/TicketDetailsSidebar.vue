<script setup>
import { Link, router, useForm } from '@inertiajs/vue3';
import { computed, onMounted, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import AppConfirmDialog from './AppConfirmDialog.vue';
import AppCollapse from './AppCollapse.vue';
import AppAvatar from './AppAvatar.vue';
import { useAssetDeleteConfirm } from '../composables/useAssetDeleteConfirm.js';
import CcEmailField from './CcEmailField.vue';
import CustomFields from './CustomFields.vue';
import RequesterField from './RequesterField.vue';
import TicketSlaPanel from './TicketSlaPanel.vue';
import TicketLifecycle from './TicketLifecycle.vue';
import TicketSideConversationsPanel from './TicketSideConversationsPanel.vue';
import TicketTimeTrackingPanel from './TicketTimeTrackingPanel.vue';
import TicketExternalIssuesPanel from './TicketExternalIssuesPanel.vue';
import CustomerContextPanel from './CustomerContextPanel.vue';
import TicketApprovalPanel from './TicketApprovalPanel.vue';
import TicketChangePanel from './TicketChangePanel.vue';
import TicketProblemPanel from './TicketProblemPanel.vue';
import TicketMajorIncidentPanel from './TicketMajorIncidentPanel.vue';

const { t } = useI18n();

const props = defineProps({
    ticket: Object,
    sla: Object,
    statuses: Array,
    priorities: Array,
    agents: Array,
    departments: Array,
    teams: Array,
    mergeCandidates: Array,
    assetOptions: Array,
    csat: Object,
    currentUserId: Number,
    customFieldDefinitions: { type: Array, default: () => [] },
    lifecycle: { type: Array, default: () => [] },
    sideConversations: { type: Array, default: () => [] },
    timeTracking: { type: Object, default: () => ({ total_minutes: 0, entries: [] }) },
    externalIssues: { type: Array, default: () => [] },
    approval: { type: Object, default: null },
    canDecideApproval: { type: Boolean, default: false },
    changeRecord: { type: Object, default: null },
    problemRecord: { type: Object, default: null },
    incidentCandidates: { type: Array, default: () => [] },
    changeRiskOptions: { type: Array, default: () => [] },
    majorIncident: { type: Object, default: null },
    canDeclareMajorIncident: { type: Boolean, default: false },
    embedded: { type: Boolean, default: false },
});

const PANEL_STORAGE_KEY = 'ticket-sidebar-panels';

const defaultPanels = () => ({
    edit: false,
    sla: false,
    assets: false,
    watchers: false,
    merge: false,
    split: false,
    activity: false,
});

const openPanels = ref(defaultPanels());

onMounted(() => {
    try {
        const stored = JSON.parse(localStorage.getItem(PANEL_STORAGE_KEY) || '{}');

        openPanels.value = { ...defaultPanels(), ...stored };
    } catch {
        openPanels.value = defaultPanels();
    }
});

const togglePanel = (panelId) => {
    openPanels.value[panelId] = !openPanels.value[panelId];
    localStorage.setItem(PANEL_STORAGE_KEY, JSON.stringify(openPanels.value));
};

const isPanelOpen = (panelId) => openPanels.value[panelId] ?? false;

const updateForm = useForm({
    subject: props.ticket.subject,
    description: props.ticket.description,
    contact_id: props.ticket.contact_id,
    requester_email: '',
    requester_name: '',
    cc_emails: (props.ticket.ccs ?? []).map((cc) => cc.email),
    assigned_to: props.agents.some((agent) => agent.id === props.ticket.assigned_to)
        ? props.ticket.assigned_to
        : '',
    department_id: props.ticket.department_id ?? '',
    team_id: props.ticket.team_id ?? '',
    ticket_status_id: props.ticket.ticket_status_id,
    ticket_priority_id: props.ticket.ticket_priority_id,
    custom_fields: { ...(props.ticket.custom_fields ?? {}) },
});

const mergeForm = useForm({ source_ticket_id: '' });
const splitForm = useForm({ from_message_id: '', subject: '' });
const assetForm = useForm({ asset_id: '' });

const filteredTeams = computed(() =>
    props.teams.filter((team) => !updateForm.department_id || team.department_id === Number(updateForm.department_id)),
);

const isWatching = computed(() =>
    (props.ticket.watchers ?? []).some((watcher) => watcher.id === props.currentUserId),
);

const assignee = computed(() =>
    props.agents.find((agent) => agent.id === props.ticket.assigned_to) ?? props.ticket.assignee,
);

const departmentName = computed(() =>
    props.departments.find((item) => item.id === props.ticket.department_id)?.name ?? t('components.none'),
);

const teamName = computed(() =>
    props.teams.find((item) => item.id === props.ticket.team_id)?.name ?? t('components.none'),
);

const slaStatusLabel = (status) => {
    if (status === 'met') {
        return t('components.sla_met');
    }

    if (status === 'breached') {
        return t('components.sla_breached');
    }

    return t('components.sla_pending');
};

const slaSummary = computed(() => {
    if (!props.sla?.active) {
        return null;
    }

    return {
        first: props.sla.first_response?.status ?? null,
        resolution: props.sla.resolution?.status ?? null,
    };
});

const hasMergeSection = computed(() => !props.ticket.merged_into_ticket_id && (props.mergeCandidates?.length ?? 0) > 0);
const hasSplitSection = computed(() => (props.ticket.messages?.length ?? 0) > 0 && !props.ticket.merged_into_ticket_id);

const ticketCustomFields = computed(() =>
    (props.customFieldDefinitions ?? []).filter((field) => {
        const value = props.ticket.custom_fields?.[field.name];

        return value !== null && value !== undefined && value !== '';
    }),
);

const update = () => updateForm.put(`/tickets/${props.ticket.id}`);
const toggleWatch = () => {
    if (isWatching.value) {
        router.delete(`/tickets/${props.ticket.id}/watchers/${props.currentUserId}`, { preserveScroll: true });
    } else {
        router.post(`/tickets/${props.ticket.id}/watchers`, {}, { preserveScroll: true });
    }
};
const merge = () => mergeForm.post(`/tickets/${props.ticket.id}/merge`);
const split = () => splitForm.post(`/tickets/${props.ticket.id}/split`);
const { state: confirm, close: closeConfirm, confirm: onConfirm, confirmUnlinkFromTicket } = useAssetDeleteConfirm();

const linkAsset = () => assetForm.post(`/tickets/${props.ticket.id}/assets`, {
    preserveScroll: true,
    onSuccess: () => assetForm.reset('asset_id'),
});

const unlinkAsset = (asset) => {
    confirmUnlinkFromTicket(asset, () => {
        router.delete(`/tickets/${props.ticket.id}/assets/${asset.id}`, { preserveScroll: true });
    });
};

const statusBadgeClass = (name) => {
    const value = (name || '').toLowerCase();

    if (value.includes('open')) return 'bg-emerald-100 text-emerald-800';
    if (value.includes('pending')) return 'bg-amber-100 text-amber-800';
    if (value.includes('closed') || value.includes('resolved')) return 'bg-slate-200 text-slate-700';

    return 'bg-slate-100 text-slate-700';
};

const priorityBadgeClass = (name) => {
    const value = (name || '').toLowerCase();

    if (value.includes('urgent') || value.includes('critical')) return 'bg-red-100 text-red-800';
    if (value.includes('high')) return 'bg-orange-100 text-orange-800';
    if (value.includes('low')) return 'bg-slate-100 text-slate-600';

    return 'bg-blue-100 text-blue-800';
};
</script>

<template>
    <component
        :is="embedded ? 'div' : 'aside'"
        :class="embedded ? 'flex h-full min-h-0 flex-col' : 'lg:sticky lg:top-6 lg:self-start'"
    >
        <div
            :class="embedded
                ? 'flex min-h-0 flex-1 flex-col overflow-hidden bg-white'
                : 'overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm'"
        >
            <div
                class="shrink-0 border-b border-slate-100 bg-white px-4 py-2.5"
                :class="embedded ? 'sticky top-0 z-10' : ''"
            >
                <h2 class="text-sm font-semibold text-slate-900">{{ $t('components.ticket_details') }}</h2>
            </div>

            <div class="min-h-0 flex-1 overflow-y-auto divide-y divide-slate-100">
                <CustomerContextPanel v-if="ticket.contact" :ticket-id="ticket.id" />
                <TicketApprovalPanel v-if="approval" :approval="approval" :can-decide="canDecideApproval" />
                <div v-if="canDeclareMajorIncident || majorIncident" class="p-4">
                    <TicketMajorIncidentPanel
                        :ticket-id="ticket.id"
                        :major-incident="majorIncident"
                        :can-declare="canDeclareMajorIncident"
                    />
                </div>
                <div v-if="changeRecord" class="p-4">
                    <TicketChangePanel
                        :ticket-id="ticket.id"
                        :change-record="changeRecord"
                        :agents="agents"
                        :risk-options="changeRiskOptions"
                    />
                </div>
                <div v-if="problemRecord" class="p-4">
                    <TicketProblemPanel
                        :ticket-id="ticket.id"
                        :problem-record="problemRecord"
                        :incident-candidates="incidentCandidates"
                    />
                </div>

                <section v-if="ticket.contact" class="px-4 py-3">
                    <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-400">{{ $t('components.requester') }}</p>
                    <div class="mt-2 flex items-center gap-2">
                        <AppAvatar :name="ticket.contact.name" :email="ticket.contact.email" size="sm" />
                        <div class="min-w-0">
                            <p class="truncate text-sm font-medium text-slate-900">{{ ticket.contact.name || ticket.contact.email }}</p>
                            <p v-if="ticket.contact.email" class="truncate text-xs text-slate-500">{{ ticket.contact.email }}</p>
                        </div>
                    </div>
                </section>

                <section v-if="ticket.ccs?.length" class="px-4 py-3">
                    <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-400">{{ $t('components.cc') }}</p>
                    <ul class="mt-2 space-y-1">
                        <li v-for="cc in ticket.ccs" :key="cc.id" class="truncate text-xs text-slate-600">
                            {{ cc.email }}
                        </li>
                    </ul>
                </section>

                <TicketSideConversationsPanel
                    :ticket-id="ticket.id"
                    :conversations="sideConversations"
                />

                <TicketTimeTrackingPanel
                    :ticket-id="ticket.id"
                    :time-tracking="timeTracking"
                />

                <TicketExternalIssuesPanel
                    :ticket-id="ticket.id"
                    :issues="externalIssues"
                />

                <section class="px-4 py-3">
                    <dl class="space-y-2.5">
                        <div class="flex items-center justify-between gap-3">
                            <dt class="text-xs text-slate-500">{{ $t('components.status') }}</dt>
                            <dd>
                                <span class="rounded-full px-2 py-0.5 text-xs font-medium" :class="statusBadgeClass(ticket.status?.name)">
                                    {{ ticket.status?.name || $t('components.em_dash') }}
                                </span>
                            </dd>
                        </div>
                        <div class="flex items-center justify-between gap-3">
                            <dt class="text-xs text-slate-500">{{ $t('components.priority') }}</dt>
                            <dd>
                                <span class="rounded-full px-2 py-0.5 text-xs font-medium" :class="priorityBadgeClass(ticket.priority?.name)">
                                    {{ ticket.priority?.name || $t('components.em_dash') }}
                                </span>
                            </dd>
                        </div>
                        <div class="flex items-center justify-between gap-3">
                            <dt class="text-xs text-slate-500">{{ $t('components.department') }}</dt>
                            <dd class="truncate text-xs font-medium text-slate-800">{{ departmentName }}</dd>
                        </div>
                        <div class="flex items-center justify-between gap-3">
                            <dt class="text-xs text-slate-500">{{ $t('components.team') }}</dt>
                            <dd class="truncate text-xs font-medium text-slate-800">{{ teamName }}</dd>
                        </div>
                        <div class="flex items-center justify-between gap-3">
                            <dt class="text-xs text-slate-500">{{ $t('components.assignee') }}</dt>
                            <dd class="flex min-w-0 items-center gap-1.5">
                                <AppAvatar
                                    v-if="assignee"
                                    :name="assignee.name"
                                    :email="assignee.email"
                                    size="sm"
                                />
                                <span class="truncate text-xs font-medium text-slate-800">{{ assignee?.name || $t('components.unassigned') }}</span>
                            </dd>
                        </div>
                    </dl>
                </section>

                <section v-if="ticketCustomFields.length" class="px-4 py-3">
                    <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-400">{{ $t('components.custom_fields') }}</p>
                    <dl class="mt-2 space-y-1.5">
                        <div v-for="field in ticketCustomFields" :key="field.name" class="flex items-start justify-between gap-3 text-xs">
                            <dt class="text-slate-500">{{ field.label }}</dt>
                            <dd class="max-w-[55%] text-right font-medium text-slate-800">{{ ticket.custom_fields[field.name] }}</dd>
                        </div>
                    </dl>
                </section>

                <section v-if="slaSummary" class="px-4 py-3">
                    <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-400">{{ $t('components.sla') }}</p>
                    <dl class="mt-2 space-y-1.5">
                        <div class="flex items-center justify-between gap-3 text-xs">
                            <dt class="text-slate-500">{{ $t('components.first_response') }}</dt>
                            <dd class="font-medium" :class="slaSummary.first === 'met' ? 'text-emerald-700' : slaSummary.first === 'breached' ? 'text-red-600' : 'text-slate-800'">{{ slaStatusLabel(slaSummary.first) }}</dd>
                        </div>
                        <div class="flex items-center justify-between gap-3 text-xs">
                            <dt class="text-slate-500">{{ $t('components.resolution') }}</dt>
                            <dd class="font-medium" :class="slaSummary.resolution === 'met' ? 'text-emerald-700' : slaSummary.resolution === 'breached' ? 'text-red-600' : 'text-slate-800'">{{ slaStatusLabel(slaSummary.resolution) }}</dd>
                        </div>
                    </dl>
                </section>

                <section class="flex items-center justify-between gap-3 px-4 py-3">
                    <div class="min-w-0">
                        <p class="text-xs text-slate-500">{{ $t('components.watchers') }}</p>
                        <p class="text-sm font-medium text-slate-800">{{ ticket.watchers?.length || 0 }}</p>
                    </div>
                    <button
                        type="button"
                        class="rounded-md border border-slate-200 px-2.5 py-1 text-xs font-medium text-slate-700 transition hover:bg-slate-50"
                        @click="toggleWatch"
                    >
                        {{ isWatching ? $t('components.unwatch') : $t('components.watch') }}
                    </button>
                </section>

                <section v-if="ticket.assets?.length" class="px-4 py-3">
                    <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-400">{{ $t('components.assets') }}</p>
                    <ul class="mt-2 space-y-1">
                        <li v-for="asset in ticket.assets.slice(0, 3)" :key="asset.id">
                            <Link :href="`/assets/${asset.id}`" class="truncate text-xs text-blue-600 hover:text-blue-700">{{ asset.asset_tag }}</Link>
                        </li>
                        <li v-if="ticket.assets.length > 3" class="text-xs text-slate-500">{{ $t('components.more_count', { count: ticket.assets.length - 3 }) }}</li>
                    </ul>
                </section>

                <section v-if="csat?.submitted" class="px-4 py-3">
                    <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-400">{{ $t('components.csat') }}</p>
                    <div class="mt-2 flex items-center gap-1">
                        <span v-for="star in 5" :key="star" class="text-base" :class="star <= csat.submitted.rating ? 'text-amber-400' : 'text-slate-300'">★</span>
                        <span class="ml-1 text-xs text-slate-600">{{ csat.submitted.rating }}/5</span>
                    </div>
                </section>

                <section>
                    <button
                        type="button"
                        class="flex w-full items-center justify-between px-4 py-2.5 text-left transition hover:bg-slate-50"
                        @click="togglePanel('edit')"
                    >
                        <span class="text-sm font-medium text-slate-900">{{ $t('components.edit_details') }}</span>
                        <svg
                            class="h-4 w-4 shrink-0 text-slate-400 transition-transform duration-300 ease-out"
                            :class="isPanelOpen('edit') ? 'rotate-180' : ''"
                            fill="none"
                            viewBox="0 0 24 24"
                            stroke="currentColor"
                            stroke-width="2"
                        >
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                        </svg>
                    </button>
                    <AppCollapse :open="isPanelOpen('edit')">
                        <div class="border-t border-slate-100 px-4 pb-4 pt-3">
                        <form class="space-y-3" @submit.prevent="update">
                            <div>
                                <label class="mb-1 block text-xs font-medium text-slate-600">{{ $t('components.requester') }}</label>
                                <RequesterField
                                    v-model:contact-id="updateForm.contact_id"
                                    v-model:requester-email="updateForm.requester_email"
                                    v-model:requester-name="updateForm.requester_name"
                                    :initial-contact="ticket.contact"
                                    :error="updateForm.errors.contact_id || updateForm.errors.requester_email"
                                />
                            </div>
                            <div>
                                <label class="mb-1 block text-xs font-medium text-slate-600">{{ $t('components.cc') }}</label>
                                <CcEmailField v-model="updateForm.cc_emails" :error="updateForm.errors.cc_emails" />
                            </div>
                            <div>
                                <label class="mb-1 block text-xs font-medium text-slate-600">{{ $t('components.status') }}</label>
                                <select v-model="updateForm.ticket_status_id" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                                    <option v-for="status in statuses" :key="status.id" :value="status.id">{{ status.name }}</option>
                                </select>
                            </div>
                            <div>
                                <label class="mb-1 block text-xs font-medium text-slate-600">{{ $t('components.priority') }}</label>
                                <select v-model="updateForm.ticket_priority_id" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                                    <option v-for="priority in priorities" :key="priority.id" :value="priority.id">{{ priority.name }}</option>
                                </select>
                            </div>
                            <div>
                                <label class="mb-1 block text-xs font-medium text-slate-600">{{ $t('components.department') }}</label>
                                <select v-model="updateForm.department_id" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                                    <option value="">{{ $t('components.none') }}</option>
                                    <option v-for="department in departments" :key="department.id" :value="department.id">{{ department.name }}</option>
                                </select>
                            </div>
                            <div>
                                <label class="mb-1 block text-xs font-medium text-slate-600">{{ $t('components.team') }}</label>
                                <select v-model="updateForm.team_id" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                                    <option value="">{{ $t('components.none') }}</option>
                                    <option v-for="team in filteredTeams" :key="team.id" :value="team.id">{{ team.name }}</option>
                                </select>
                            </div>
                            <div>
                                <label class="mb-1 block text-xs font-medium text-slate-600">{{ $t('components.assignee') }}</label>
                                <select v-model="updateForm.assigned_to" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                                    <option value="">{{ $t('components.unassigned') }}</option>
                                    <option v-for="agent in agents" :key="agent.id" :value="agent.id">{{ agent.name }}</option>
                                </select>
                            </div>
                            <CustomFields
                                v-model="updateForm.custom_fields"
                                :definitions="customFieldDefinitions"
                                :errors="updateForm.errors"
                            />
                            <button type="submit" class="w-full rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50" :disabled="updateForm.processing">{{ $t('components.save_changes') }}</button>
                        </form>
                        </div>
                    </AppCollapse>
                </section>

                <section v-if="sla?.active">
                    <button
                        type="button"
                        class="flex w-full items-center justify-between px-4 py-2.5 text-left transition hover:bg-slate-50"
                        @click="togglePanel('sla')"
                    >
                        <span class="text-sm font-medium text-slate-900">{{ $t('components.sla_details') }}</span>
                        <svg
                            class="h-4 w-4 shrink-0 text-slate-400 transition-transform duration-300 ease-out"
                            :class="isPanelOpen('sla') ? 'rotate-180' : ''"
                            fill="none"
                            viewBox="0 0 24 24"
                            stroke="currentColor"
                            stroke-width="2"
                        >
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                        </svg>
                    </button>
                    <AppCollapse :open="isPanelOpen('sla')">
                        <div class="border-t border-slate-100 px-4 pb-4 pt-3">
                        <TicketSlaPanel :sla="sla" />
                        </div>
                    </AppCollapse>
                </section>

                <section>
                    <button
                        type="button"
                        class="flex w-full items-center justify-between px-4 py-2.5 text-left transition hover:bg-slate-50"
                        @click="togglePanel('assets')"
                    >
                        <span class="text-sm font-medium text-slate-900">{{ $t('components.linked_assets') }}</span>
                        <svg
                            class="h-4 w-4 shrink-0 text-slate-400 transition-transform duration-300 ease-out"
                            :class="isPanelOpen('assets') ? 'rotate-180' : ''"
                            fill="none"
                            viewBox="0 0 24 24"
                            stroke="currentColor"
                            stroke-width="2"
                        >
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                        </svg>
                    </button>
                    <AppCollapse :open="isPanelOpen('assets')">
                        <div class="border-t border-slate-100 px-4 pb-4 pt-3">
                        <ul class="space-y-2 text-sm">
                            <li v-for="asset in ticket.assets" :key="asset.id" class="flex items-center justify-between gap-2">
                                <Link :href="`/assets/${asset.id}`" class="text-blue-600 hover:text-blue-700">{{ asset.asset_tag }} — {{ asset.name }}</Link>
                                <button type="button" class="text-xs text-red-600 hover:text-red-700" @click="unlinkAsset(asset)">{{ $t('components.remove') }}</button>
                            </li>
                            <li v-if="!ticket.assets?.length" class="text-slate-500">{{ $t('components.no_linked_assets') }}</li>
                        </ul>
                        <form class="mt-3 flex gap-2" @submit.prevent="linkAsset">
                            <select v-model="assetForm.asset_id" required class="min-w-0 flex-1 rounded-lg border border-slate-300 px-3 py-2 text-sm">
                                <option value="">{{ $t('components.link_asset') }}</option>
                                <option v-for="asset in assetOptions" :key="asset.id" :value="asset.id">{{ asset.asset_tag }} — {{ asset.name }}</option>
                            </select>
                            <button type="submit" class="rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-700 hover:bg-slate-50" :disabled="assetForm.processing">{{ $t('components.link') }}</button>
                        </form>
                        </div>
                    </AppCollapse>
                </section>

                <section>
                    <button
                        type="button"
                        class="flex w-full items-center justify-between px-4 py-2.5 text-left transition hover:bg-slate-50"
                        @click="togglePanel('watchers')"
                    >
                        <span class="text-sm font-medium text-slate-900">{{ $t('components.all_watchers') }}</span>
                        <svg
                            class="h-4 w-4 shrink-0 text-slate-400 transition-transform duration-300 ease-out"
                            :class="isPanelOpen('watchers') ? 'rotate-180' : ''"
                            fill="none"
                            viewBox="0 0 24 24"
                            stroke="currentColor"
                            stroke-width="2"
                        >
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                        </svg>
                    </button>
                    <AppCollapse :open="isPanelOpen('watchers')">
                        <div class="border-t border-slate-100 px-4 pb-4 pt-3">
                        <ul class="space-y-1 text-sm text-slate-700">
                            <li v-for="watcher in ticket.watchers" :key="watcher.id">{{ watcher.name }}</li>
                            <li v-if="!ticket.watchers?.length" class="text-slate-500">{{ $t('components.no_watchers') }}</li>
                        </ul>
                        </div>
                    </AppCollapse>
                </section>

                <section v-if="hasMergeSection">
                    <button
                        type="button"
                        class="flex w-full items-center justify-between px-4 py-2.5 text-left transition hover:bg-slate-50"
                        @click="togglePanel('merge')"
                    >
                        <span class="text-sm font-medium text-slate-900">{{ $t('components.merge_ticket') }}</span>
                        <svg
                            class="h-4 w-4 shrink-0 text-slate-400 transition-transform duration-300 ease-out"
                            :class="isPanelOpen('merge') ? 'rotate-180' : ''"
                            fill="none"
                            viewBox="0 0 24 24"
                            stroke="currentColor"
                            stroke-width="2"
                        >
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                        </svg>
                    </button>
                    <AppCollapse :open="isPanelOpen('merge')">
                        <div class="border-t border-slate-100 px-4 pb-4 pt-3">
                        <form class="space-y-2" @submit.prevent="merge">
                            <select v-model="mergeForm.source_ticket_id" required class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                                <option value="">{{ $t('components.select_ticket') }}</option>
                                <option v-for="candidate in mergeCandidates" :key="candidate.id" :value="candidate.id">{{ candidate.number }} — {{ candidate.subject }}</option>
                            </select>
                            <button type="submit" class="w-full rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50" :disabled="mergeForm.processing">{{ $t('components.merge') }}</button>
                        </form>
                        </div>
                    </AppCollapse>
                </section>

                <section v-if="hasSplitSection">
                    <button
                        type="button"
                        class="flex w-full items-center justify-between px-4 py-2.5 text-left transition hover:bg-slate-50"
                        @click="togglePanel('split')"
                    >
                        <span class="text-sm font-medium text-slate-900">{{ $t('components.split_ticket') }}</span>
                        <svg
                            class="h-4 w-4 shrink-0 text-slate-400 transition-transform duration-300 ease-out"
                            :class="isPanelOpen('split') ? 'rotate-180' : ''"
                            fill="none"
                            viewBox="0 0 24 24"
                            stroke="currentColor"
                            stroke-width="2"
                        >
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                        </svg>
                    </button>
                    <AppCollapse :open="isPanelOpen('split')">
                        <div class="border-t border-slate-100 px-4 pb-4 pt-3">
                        <form class="space-y-2" @submit.prevent="split">
                            <select v-model="splitForm.from_message_id" required class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                                <option value="">{{ $t('components.from_message') }}</option>
                                <option v-for="message in ticket.messages" :key="message.id" :value="message.id">
                                    {{ message.user?.name || $t('components.system') }} — {{ message.body.slice(0, 40) }}...
                                </option>
                            </select>
                            <input v-model="splitForm.subject" type="text" :placeholder="$t('components.new_ticket_subject_optional')" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" />
                            <button type="submit" class="w-full rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50" :disabled="splitForm.processing">{{ $t('components.split') }}</button>
                        </form>
                        </div>
                    </AppCollapse>
                </section>

                <section v-if="ticket.merged_tickets?.length" class="px-4 py-3">
                    <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-400">{{ $t('components.merged_tickets') }}</p>
                    <ul class="mt-2 space-y-1 text-sm">
                        <li v-for="merged in ticket.merged_tickets" :key="merged.id">
                            <Link :href="`/tickets/${merged.id}`" class="text-blue-600 hover:text-blue-700">{{ merged.number }}</Link>
                            — {{ merged.subject }}
                        </li>
                    </ul>
                </section>

                <section>
                    <button
                        type="button"
                        class="flex w-full items-center justify-between px-4 py-2.5 text-left transition hover:bg-slate-50"
                        @click="togglePanel('activity')"
                    >
                        <span class="text-sm font-medium text-slate-900">{{ $t('components.activity') }}</span>
                        <div class="flex items-center gap-2">
                            <span v-if="lifecycle.length" class="text-[10px] text-slate-400">{{ lifecycle.length }}</span>
                            <svg
                                class="h-4 w-4 shrink-0 text-slate-400 transition-transform duration-300 ease-out"
                                :class="isPanelOpen('activity') ? 'rotate-180' : ''"
                                fill="none"
                                viewBox="0 0 24 24"
                                stroke="currentColor"
                                stroke-width="2"
                            >
                                <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                            </svg>
                        </div>
                    </button>
                    <AppCollapse :open="isPanelOpen('activity')">
                        <div class="border-t border-slate-100 px-4 pb-4 pt-3">
                        <TicketLifecycle compact :lifecycle="lifecycle" />
                        </div>
                    </AppCollapse>
                </section>
            </div>
        </div>

        <AppConfirmDialog
            :open="confirm.open"
            :title="confirm.title"
            :message="confirm.message"
            :confirm-label="confirm.confirmLabel"
            :variant="confirm.variant"
            @close="closeConfirm"
            @confirm="onConfirm"
        />
    </component>
</template>
