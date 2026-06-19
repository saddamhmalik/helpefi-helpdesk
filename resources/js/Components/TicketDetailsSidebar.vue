<script setup>
import { Link, router, useForm } from '@inertiajs/vue3';
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import AppConfirmDialog from './AppConfirmDialog.vue';
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
import TicketSidebarSection from './TicketSidebarSection.vue';
import { formInputClass, formSelectClass } from '../composables/useFormControls.js';

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
    issueProviders: { type: Array, default: () => [] },
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
    sla: false,
    assets: false,
    watchers: false,
    merge: false,
    split: false,
    activity: true,
});

const openPanels = ref(defaultPanels());

onMounted(() => {
    try {
        const stored = JSON.parse(localStorage.getItem(PANEL_STORAGE_KEY) || '{}');

        openPanels.value = { ...defaultPanels(), ...stored };
    } catch {
        openPanels.value = defaultPanels();
    }

    resetSaveBaseline();
    autoSaveReady.value = true;
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

updateForm.transform((data) => ({ ...data, _autosave: true }));

const saveStatus = ref('idle');
const autoSaveReady = ref(false);
let autoSaveTimer = null;
let savedFadeTimer = null;
let pendingAutoSave = false;
let savedBaseline = '';

const buildSavePayload = () => ({
    subject: updateForm.subject,
    description: updateForm.description,
    contact_id: updateForm.contact_id || '',
    requester_email: updateForm.requester_email || '',
    requester_name: updateForm.requester_name || '',
    cc_emails: [...updateForm.cc_emails],
    assigned_to: updateForm.assigned_to || '',
    department_id: updateForm.department_id || '',
    team_id: updateForm.team_id || '',
    ticket_status_id: updateForm.ticket_status_id,
    ticket_priority_id: updateForm.ticket_priority_id,
    custom_fields: { ...updateForm.custom_fields },
});

const serializeSavePayload = (payload) => JSON.stringify(payload);

const resetSaveBaseline = () => {
    savedBaseline = serializeSavePayload(buildSavePayload());
};

const syncUpdateFormFromTicket = () => {
    updateForm.subject = props.ticket.subject;
    updateForm.description = props.ticket.description;
    updateForm.contact_id = props.ticket.contact_id;
    updateForm.requester_email = '';
    updateForm.requester_name = '';
    updateForm.cc_emails = (props.ticket.ccs ?? []).map((cc) => cc.email);
    updateForm.assigned_to = props.agents.some((agent) => agent.id === props.ticket.assigned_to)
        ? props.ticket.assigned_to
        : '';
    updateForm.department_id = props.ticket.department_id ?? '';
    updateForm.team_id = props.ticket.team_id ?? '';
    updateForm.ticket_status_id = props.ticket.ticket_status_id;
    updateForm.ticket_priority_id = props.ticket.ticket_priority_id;
    updateForm.custom_fields = { ...(props.ticket.custom_fields ?? {}) };
    resetSaveBaseline();
    saveStatus.value = 'idle';
};

const runAutoSave = () => {
    const payload = serializeSavePayload(buildSavePayload());

    if (!autoSaveReady.value || payload === savedBaseline) {
        return;
    }

    if (updateForm.processing) {
        pendingAutoSave = true;
        return;
    }

    saveStatus.value = 'saving';

    updateForm.put(`/tickets/${props.ticket.id}`, {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
            resetSaveBaseline();
            saveStatus.value = 'saved';
            clearTimeout(savedFadeTimer);
            savedFadeTimer = setTimeout(() => {
                if (saveStatus.value === 'saved') {
                    saveStatus.value = 'idle';
                }
            }, 2000);
        },
        onError: () => {
            saveStatus.value = 'error';
        },
        onFinish: () => {
            if (pendingAutoSave) {
                pendingAutoSave = false;
                scheduleAutoSave();
            }
        },
    });
};

const scheduleAutoSave = () => {
    clearTimeout(autoSaveTimer);
    autoSaveTimer = setTimeout(runAutoSave, 600);
};

const filteredTeams = computed(() =>
    props.teams.filter((team) => !updateForm.department_id || team.department_id === Number(updateForm.department_id)),
);

watch(
    () => buildSavePayload(),
    () => {
        if (!autoSaveReady.value) {
            return;
        }

        scheduleAutoSave();
    },
    { deep: true },
);

watch(
    () => props.ticket.id,
    () => {
        clearTimeout(autoSaveTimer);
        pendingAutoSave = false;
        syncUpdateFormFromTicket();
    },
);

watch(
    () => updateForm.department_id,
    () => {
        if (!updateForm.team_id) {
            return;
        }

        const teamStillValid = filteredTeams.value.some((team) => team.id === Number(updateForm.team_id));

        if (!teamStillValid) {
            updateForm.team_id = '';
        }
    },
);

onUnmounted(() => {
    clearTimeout(autoSaveTimer);
    clearTimeout(savedFadeTimer);
});

const mergeForm = useForm({ source_ticket_id: '', import_conversation: true });
const splitForm = useForm({ from_message_id: '', subject: '' });
const assetForm = useForm({ asset_id: '' });

const isWatching = computed(() =>
    (props.ticket.watchers ?? []).some((watcher) => watcher.id === props.currentUserId),
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

const sidebarSelectClass = `${formSelectClass} py-2 text-xs`;

const saveStatusLabel = computed(() => {
    if (saveStatus.value === 'saving') return t('components.saving');
    if (saveStatus.value === 'saved') return t('components.saved');
    if (saveStatus.value === 'error') return t('components.save_failed');

    return '';
});

const saveStatusClass = computed(() => {
    if (saveStatus.value === 'saving') return 'text-slate-500 dark:text-slate-400';
    if (saveStatus.value === 'saved') return 'text-emerald-600 dark:text-emerald-400';
    if (saveStatus.value === 'error') return 'text-red-600 dark:text-red-400';

    return '';
});
const statusBadgeClass = (name) => {
    const value = (name || '').toLowerCase();

    if (value.includes('open')) return 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/50 dark:text-emerald-300';
    if (value.includes('pending')) return 'bg-amber-100 text-amber-800 dark:bg-amber-950/50 dark:text-amber-300';
    if (value.includes('closed') || value.includes('resolved')) return 'bg-slate-200 text-slate-700 dark:bg-slate-800 dark:text-slate-300';

    return 'bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300';
};

const priorityBadgeClass = (name) => {
    const value = (name || '').toLowerCase();

    if (value.includes('urgent') || value.includes('critical')) return 'bg-red-100 text-red-800 dark:bg-red-950/50 dark:text-red-300';
    if (value.includes('high')) return 'bg-orange-100 text-orange-800 dark:bg-orange-950/50 dark:text-orange-300';
    if (value.includes('low')) return 'bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400';

    return 'bg-blue-100 text-blue-800 dark:bg-blue-950/50 dark:text-blue-300';
};
</script>

<template>
    <component
        :is="embedded ? 'div' : 'aside'"
        :class="embedded ? 'flex h-full min-h-0 flex-col' : 'lg:sticky lg:top-6 lg:self-start'"
    >
        <div
            :class="embedded
                ? 'flex min-h-0 flex-1 flex-col overflow-hidden bg-white dark:bg-slate-900'
                : 'overflow-hidden rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 shadow-sm'"
        >
            <div
                class="relative shrink-0 overflow-hidden border-b border-slate-100 bg-gradient-to-br from-slate-50 via-white to-blue-50/40 px-4 py-3.5 dark:border-slate-800 dark:from-slate-900 dark:via-slate-900 dark:to-blue-950/20"
                :class="embedded ? 'sticky top-0 z-10' : ''"
            >
                <div class="pointer-events-none absolute inset-0 bg-[radial-gradient(circle_at_top_right,rgba(59,130,246,0.08),transparent_55%)] dark:bg-[radial-gradient(circle_at_top_right,rgba(59,130,246,0.12),transparent_55%)]" />
                <div class="relative flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <p class="text-[10px] font-bold uppercase tracking-[0.14em] text-slate-400 dark:text-slate-500">{{ $t('components.ticket_details') }}</p>
                        <h2 class="mt-0.5 truncate font-mono text-sm font-semibold text-slate-900 dark:text-slate-100">{{ ticket.number }}</h2>
                    </div>
                    <div class="flex shrink-0 items-center gap-2">
                        <span
                            v-if="saveStatus !== 'idle'"
                            class="inline-flex items-center gap-1.5 rounded-full border border-slate-200/80 bg-white/90 px-2.5 py-1 text-[10px] font-semibold shadow-sm backdrop-blur dark:border-slate-700 dark:bg-slate-900/90"
                            :class="saveStatusClass"
                        >
                            <span
                                class="h-1.5 w-1.5 rounded-full"
                                :class="{
                                    'animate-pulse bg-slate-400': saveStatus === 'saving',
                                    'bg-emerald-500': saveStatus === 'saved',
                                    'bg-red-500': saveStatus === 'error',
                                }"
                            />
                            {{ saveStatusLabel }}
                        </span>
                        <span
                            class="rounded-full px-2.5 py-1 text-[10px] font-semibold uppercase tracking-wide"
                            :class="statusBadgeClass(ticket.status?.name)"
                        >
                            {{ ticket.status?.name || $t('components.em_dash') }}
                        </span>
                    </div>
                </div>
            </div>

            <div class="min-h-0 flex-1 overflow-y-auto overflow-x-hidden">
                <CustomerContextPanel v-if="ticket.contact" :ticket-id="ticket.id" />
                <TicketApprovalPanel v-if="approval" :approval="approval" :can-decide="canDecideApproval" />
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

                <section class="border-b border-slate-100 p-4 dark:border-slate-800">
                    <div class="rounded-2xl border border-slate-200/80 bg-gradient-to-br from-white to-slate-50/80 p-3.5 shadow-sm dark:border-slate-800 dark:from-slate-900 dark:to-slate-900/60">
                        <p class="mb-3 text-[10px] font-bold uppercase tracking-[0.14em] text-slate-400 dark:text-slate-500">{{ $t('components.requester') }}</p>
                        <RequesterField
                            v-model:contact-id="updateForm.contact_id"
                            v-model:requester-email="updateForm.requester_email"
                            v-model:requester-name="updateForm.requester_name"
                            :initial-contact="ticket.contact"
                            :error="updateForm.errors.contact_id || updateForm.errors.requester_email"
                        />
                    </div>
                </section>

                <section class="space-y-4 border-b border-slate-100 p-4 dark:border-slate-800">
                    <CcEmailField v-model="updateForm.cc_emails" :error="updateForm.errors.cc_emails" />

                    <div class="rounded-2xl border border-slate-200/80 bg-white p-3.5 shadow-sm dark:border-slate-800 dark:bg-slate-900/70">
                        <p class="mb-3 text-[10px] font-bold uppercase tracking-[0.14em] text-slate-400 dark:text-slate-500">{{ $t('components.routing') }}</p>
                        <div class="grid gap-3">
                            <div class="grid grid-cols-2 gap-3">
                                <div>
                                    <label class="mb-1.5 flex items-center justify-between gap-2 text-[11px] font-medium text-slate-500 dark:text-slate-400">
                                        <span>{{ $t('components.status') }}</span>
                                        <span class="rounded-full px-1.5 py-0.5 text-[10px] font-semibold" :class="statusBadgeClass(statuses.find((item) => item.id === updateForm.ticket_status_id)?.name)">
                                            {{ statuses.find((item) => item.id === updateForm.ticket_status_id)?.name || $t('components.em_dash') }}
                                        </span>
                                    </label>
                                    <select v-model="updateForm.ticket_status_id" :class="sidebarSelectClass">
                                        <option v-for="status in statuses" :key="status.id" :value="status.id">{{ status.name }}</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="mb-1.5 flex items-center justify-between gap-2 text-[11px] font-medium text-slate-500 dark:text-slate-400">
                                        <span>{{ $t('components.priority') }}</span>
                                        <span class="rounded-full px-1.5 py-0.5 text-[10px] font-semibold" :class="priorityBadgeClass(priorities.find((item) => item.id === updateForm.ticket_priority_id)?.name)">
                                            {{ priorities.find((item) => item.id === updateForm.ticket_priority_id)?.name || $t('components.em_dash') }}
                                        </span>
                                    </label>
                                    <select v-model="updateForm.ticket_priority_id" :class="sidebarSelectClass">
                                        <option v-for="priority in priorities" :key="priority.id" :value="priority.id">{{ priority.name }}</option>
                                    </select>
                                </div>
                            </div>
                            <div>
                                <label class="mb-1.5 block text-[11px] font-medium text-slate-500 dark:text-slate-400">{{ $t('components.department') }}</label>
                                <select v-model="updateForm.department_id" :class="sidebarSelectClass">
                                    <option value="">{{ $t('components.none') }}</option>
                                    <option v-for="department in departments" :key="department.id" :value="department.id">{{ department.name }}</option>
                                </select>
                            </div>
                            <div>
                                <label class="mb-1.5 block text-[11px] font-medium text-slate-500 dark:text-slate-400">{{ $t('components.team') }}</label>
                                <select v-model="updateForm.team_id" :class="sidebarSelectClass">
                                    <option value="">{{ $t('components.none') }}</option>
                                    <option v-for="team in filteredTeams" :key="team.id" :value="team.id">{{ team.name }}</option>
                                </select>
                            </div>
                            <div>
                                <label class="mb-1.5 block text-[11px] font-medium text-slate-500 dark:text-slate-400">{{ $t('components.assignee') }}</label>
                                <div class="flex items-center gap-2">
                                    <AppAvatar
                                        v-if="agents.find((agent) => agent.id === updateForm.assigned_to)"
                                        :name="agents.find((agent) => agent.id === updateForm.assigned_to)?.name"
                                        :email="agents.find((agent) => agent.id === updateForm.assigned_to)?.email"
                                        size="sm"
                                    />
                                    <select v-model="updateForm.assigned_to" :class="[sidebarSelectClass, 'min-w-0 flex-1']">
                                        <option value="">{{ $t('components.unassigned') }}</option>
                                        <option v-for="agent in agents" :key="agent.id" :value="agent.id">{{ agent.name }}</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>

                    <CustomFields
                        v-if="customFieldDefinitions.length"
                        v-model="updateForm.custom_fields"
                        :definitions="customFieldDefinitions"
                        :errors="updateForm.errors"
                    />
                </section>

                <section v-if="slaSummary" class="border-b border-slate-100 px-4 py-3 dark:border-slate-800">
                    <div class="grid grid-cols-2 gap-2">
                        <div class="rounded-xl border border-slate-200/80 bg-slate-50/80 px-3 py-2.5 dark:border-slate-800 dark:bg-slate-900/60">
                            <p class="text-[10px] font-semibold uppercase tracking-wide text-slate-400 dark:text-slate-500">{{ $t('components.first_response') }}</p>
                            <p class="mt-1 text-xs font-semibold" :class="slaSummary.first === 'met' ? 'text-emerald-700 dark:text-emerald-300' : slaSummary.first === 'breached' ? 'text-red-600' : 'text-slate-800 dark:text-slate-200'">
                                {{ slaStatusLabel(slaSummary.first) }}
                            </p>
                        </div>
                        <div class="rounded-xl border border-slate-200/80 bg-slate-50/80 px-3 py-2.5 dark:border-slate-800 dark:bg-slate-900/60">
                            <p class="text-[10px] font-semibold uppercase tracking-wide text-slate-400 dark:text-slate-500">{{ $t('components.resolution') }}</p>
                            <p class="mt-1 text-xs font-semibold" :class="slaSummary.resolution === 'met' ? 'text-emerald-700 dark:text-emerald-300' : slaSummary.resolution === 'breached' ? 'text-red-600' : 'text-slate-800 dark:text-slate-200'">
                                {{ slaStatusLabel(slaSummary.resolution) }}
                            </p>
                        </div>
                    </div>
                </section>

                <section class="border-b border-slate-100 px-4 py-3 dark:border-slate-800">
                    <div class="flex items-center justify-between gap-3 rounded-2xl border border-slate-200/80 bg-gradient-to-r from-slate-50 to-white px-3.5 py-3 dark:border-slate-800 dark:from-slate-900 dark:to-slate-900/60">
                        <div class="flex min-w-0 items-center gap-3">
                            <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-violet-100 text-violet-600 dark:bg-violet-950/50 dark:text-violet-300">
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                </svg>
                            </span>
                            <div>
                                <p class="text-xs font-semibold text-slate-900 dark:text-slate-100">{{ $t('components.watchers') }}</p>
                                <p class="text-[11px] text-slate-500 dark:text-slate-400">{{ ticket.watchers?.length || 0 }}</p>
                            </div>
                        </div>
                        <button
                            type="button"
                            class="rounded-xl px-3 py-1.5 text-xs font-semibold transition"
                            :class="isWatching
                                ? 'bg-violet-600 text-white shadow-sm shadow-violet-600/20 hover:bg-violet-700'
                                : 'border border-slate-200 bg-white text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 dark:hover:bg-slate-800'"
                            @click="toggleWatch"
                        >
                            {{ isWatching ? $t('components.unwatch') : $t('components.watch') }}
                        </button>
                    </div>
                </section>

                <TicketSideConversationsPanel
                    :ticket-id="ticket.id"
                    :conversations="sideConversations"
                />

                <TicketTimeTrackingPanel
                    :ticket-id="ticket.id"
                    :time-tracking="timeTracking"
                />

                <TicketMajorIncidentPanel
                    v-if="canDeclareMajorIncident || majorIncident"
                    :ticket-id="ticket.id"
                    :major-incident="majorIncident"
                    :can-declare="canDeclareMajorIncident"
                />

                <TicketExternalIssuesPanel
                    :ticket-id="ticket.id"
                    :issues="externalIssues"
                    :issue-providers="issueProviders"
                />

                <section v-if="csat?.submitted" class="border-b border-slate-100 px-4 py-3 dark:border-slate-800">
                    <div class="rounded-2xl border border-amber-200/70 bg-gradient-to-br from-amber-50 to-white px-3.5 py-3 dark:border-amber-900/40 dark:from-amber-950/20 dark:to-slate-900/60">
                        <p class="text-[10px] font-bold uppercase tracking-[0.14em] text-amber-700 dark:text-amber-300">{{ $t('components.csat') }}</p>
                        <div class="mt-2 flex items-center gap-1">
                            <span v-for="star in 5" :key="star" class="text-lg" :class="star <= csat.submitted.rating ? 'text-amber-400' : 'text-slate-300 dark:text-slate-600'">★</span>
                            <span class="ml-1 text-sm font-semibold text-amber-800 dark:text-amber-200">{{ csat.submitted.rating }}/5</span>
                        </div>
                    </div>
                </section>

                <TicketSidebarSection
                    v-if="sla?.active"
                    :title="$t('components.sla_details')"
                    :open="isPanelOpen('sla')"
                    tone="emerald"
                    @toggle="togglePanel('sla')"
                >
                    <template #icon>
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </template>
                    <div class="border-t border-slate-100 px-4 pb-4 pt-3 dark:border-slate-800">
                        <TicketSlaPanel :sla="sla" />
                    </div>
                </TicketSidebarSection>

                <TicketSidebarSection
                    :title="$t('components.linked_assets')"
                    :open="isPanelOpen('assets')"
                    :badge="ticket.assets?.length || 0"
                    tone="amber"
                    @toggle="togglePanel('assets')"
                >
                    <template #icon>
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
                        </svg>
                    </template>
                    <div class="border-t border-slate-100 px-4 pb-4 pt-3 dark:border-slate-800">
                        <ul class="space-y-2">
                            <li
                                v-for="asset in ticket.assets"
                                :key="asset.id"
                                class="flex items-center justify-between gap-2 rounded-xl border border-slate-200/80 bg-slate-50/80 px-3 py-2.5 dark:border-slate-800 dark:bg-slate-900/60"
                            >
                                <Link :href="`/assets/${asset.id}`" class="min-w-0 truncate text-sm font-medium text-blue-600 hover:text-blue-700 dark:text-blue-300">{{ asset.asset_tag }} — {{ asset.name }}</Link>
                                <button type="button" class="shrink-0 text-xs font-medium text-red-600 hover:text-red-700 dark:text-red-400" @click="unlinkAsset(asset)">{{ $t('components.remove') }}</button>
                            </li>
                            <li v-if="!ticket.assets?.length" class="rounded-xl border border-dashed border-slate-200 px-3 py-4 text-center text-xs text-slate-500 dark:border-slate-700 dark:text-slate-400">{{ $t('components.no_linked_assets') }}</li>
                        </ul>
                        <form class="mt-3 flex gap-2" @submit.prevent="linkAsset">
                            <select v-model="assetForm.asset_id" required :class="[sidebarSelectClass, 'min-w-0 flex-1']">
                                <option value="">{{ $t('components.link_asset') }}</option>
                                <option v-for="asset in assetOptions" :key="asset.id" :value="asset.id">{{ asset.asset_tag }} — {{ asset.name }}</option>
                            </select>
                            <button type="submit" class="rounded-xl bg-slate-900 px-3 py-2 text-xs font-semibold text-white transition hover:bg-slate-800 disabled:opacity-50 dark:bg-slate-100 dark:text-slate-900 dark:hover:bg-white" :disabled="assetForm.processing">{{ $t('components.link') }}</button>
                        </form>
                    </div>
                </TicketSidebarSection>

                <TicketSidebarSection
                    :title="$t('components.all_watchers')"
                    :open="isPanelOpen('watchers')"
                    :badge="ticket.watchers?.length || 0"
                    tone="violet"
                    @toggle="togglePanel('watchers')"
                >
                    <template #icon>
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z" />
                        </svg>
                    </template>
                    <div class="border-t border-slate-100 px-4 pb-4 pt-3 dark:border-slate-800">
                        <ul class="space-y-2">
                            <li
                                v-for="watcher in ticket.watchers"
                                :key="watcher.id"
                                class="flex items-center gap-2 rounded-xl border border-slate-200/80 bg-slate-50/80 px-3 py-2 dark:border-slate-800 dark:bg-slate-900/60"
                            >
                                <AppAvatar :name="watcher.name" :email="watcher.email" size="sm" />
                                <span class="truncate text-sm text-slate-700 dark:text-slate-300">{{ watcher.name }}</span>
                            </li>
                            <li v-if="!ticket.watchers?.length" class="rounded-xl border border-dashed border-slate-200 px-3 py-4 text-center text-xs text-slate-500 dark:border-slate-700 dark:text-slate-400">{{ $t('components.no_watchers') }}</li>
                        </ul>
                    </div>
                </TicketSidebarSection>

                <TicketSidebarSection
                    v-if="hasMergeSection"
                    :title="$t('components.merge_ticket')"
                    :open="isPanelOpen('merge')"
                    tone="blue"
                    @toggle="togglePanel('merge')"
                >
                    <template #icon>
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4" />
                        </svg>
                    </template>
                    <div class="border-t border-slate-100 px-4 pb-4 pt-3 dark:border-slate-800">
                        <form class="space-y-3" @submit.prevent="merge">
                            <select v-model="mergeForm.source_ticket_id" required :class="sidebarSelectClass">
                                <option value="">{{ $t('components.select_ticket') }}</option>
                                <option v-for="candidate in mergeCandidates" :key="candidate.id" :value="candidate.id">{{ candidate.number }} — {{ candidate.subject }}</option>
                            </select>
                            <label class="flex items-start gap-2.5 rounded-xl border border-slate-200/80 bg-slate-50/80 px-3 py-2.5 dark:border-slate-800 dark:bg-slate-900/60">
                                <input
                                    v-model="mergeForm.import_conversation"
                                    type="checkbox"
                                    class="mt-0.5 rounded border-slate-300 text-blue-600 focus:ring-blue-500 dark:border-slate-600 dark:bg-slate-900"
                                />
                                <span class="min-w-0">
                                    <span class="block text-xs font-semibold text-slate-800 dark:text-slate-200">{{ $t('components.import_merged_conversation') }}</span>
                                    <span class="mt-0.5 block text-[11px] leading-snug text-slate-500 dark:text-slate-400">{{ $t('components.import_merged_conversation_help') }}</span>
                                </span>
                            </label>
                            <button type="submit" class="w-full rounded-xl bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-blue-700 disabled:opacity-50" :disabled="mergeForm.processing">{{ $t('components.merge') }}</button>
                        </form>
                    </div>
                </TicketSidebarSection>

                <TicketSidebarSection
                    v-if="hasSplitSection"
                    :title="$t('components.split_ticket')"
                    :open="isPanelOpen('split')"
                    tone="rose"
                    @toggle="togglePanel('split')"
                >
                    <template #icon>
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M8 7h12m0 0l-4-4m4 4l-4 4M16 17H4m0 0l4 4m-4-4l4-4" />
                        </svg>
                    </template>
                    <div class="border-t border-slate-100 px-4 pb-4 pt-3 dark:border-slate-800">
                        <form class="space-y-2" @submit.prevent="split">
                            <select v-model="splitForm.from_message_id" required :class="sidebarSelectClass">
                                <option value="">{{ $t('components.from_message') }}</option>
                                <option v-for="message in ticket.messages" :key="message.id" :value="message.id">
                                    {{ message.user?.name || $t('components.system') }} — {{ message.body.slice(0, 40) }}...
                                </option>
                            </select>
                            <input v-model="splitForm.subject" type="text" :placeholder="$t('components.new_ticket_subject_optional')" :class="`${formInputClass} text-xs`" />
                            <button type="submit" class="w-full rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 transition hover:bg-slate-50 disabled:opacity-50 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 dark:hover:bg-slate-800" :disabled="splitForm.processing">{{ $t('components.split') }}</button>
                        </form>
                    </div>
                </TicketSidebarSection>

                <section v-if="ticket.merged_tickets?.length" class="border-t border-slate-100 px-4 py-3 dark:border-slate-800">
                    <p class="text-[10px] font-bold uppercase tracking-[0.14em] text-slate-400 dark:text-slate-500">{{ $t('components.merged_tickets') }}</p>
                    <ul class="mt-2 space-y-2">
                        <li
                            v-for="merged in ticket.merged_tickets"
                            :key="merged.id"
                            class="rounded-xl border border-slate-200/80 bg-slate-50/80 px-3 py-2 text-sm dark:border-slate-800 dark:bg-slate-900/60"
                        >
                            <Link :href="`/tickets/${merged.id}`" class="font-medium text-blue-600 hover:text-blue-700 dark:text-blue-300">{{ merged.number }}</Link>
                            <span class="text-slate-500 dark:text-slate-400"> — {{ merged.subject }}</span>
                        </li>
                    </ul>
                </section>

                <TicketSidebarSection
                    :title="$t('components.activity')"
                    :open="isPanelOpen('activity')"
                    :badge="lifecycle.length || ''"
                    tone="blue"
                    @toggle="togglePanel('activity')"
                >
                    <template #icon>
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z" />
                        </svg>
                    </template>
                    <div class="border-t border-slate-100 px-4 pb-4 pt-3 dark:border-slate-800">
                        <TicketLifecycle compact :lifecycle="lifecycle" />
                    </div>
                </TicketSidebarSection>
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
