<script setup>
import { Link, router, useForm } from '@inertiajs/vue3';
import { computed, onMounted, ref } from 'vue';
import AppAvatar from './AppAvatar.vue';
import CcEmailField from './CcEmailField.vue';
import CustomFields from './CustomFields.vue';
import RequesterField from './RequesterField.vue';
import TicketSlaPanel from './TicketSlaPanel.vue';
import TicketLifecycle from './TicketLifecycle.vue';
import TicketSideConversationsPanel from './TicketSideConversationsPanel.vue';
import TicketTimeTrackingPanel from './TicketTimeTrackingPanel.vue';
import TicketExternalIssuesPanel from './TicketExternalIssuesPanel.vue';

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
    props.departments.find((item) => item.id === props.ticket.department_id)?.name ?? 'None',
);

const teamName = computed(() =>
    props.teams.find((item) => item.id === props.ticket.team_id)?.name ?? 'None',
);

const slaSummary = computed(() => {
    if (!props.sla?.active) {
        return null;
    }

    const first = props.sla.first_response;
    const resolution = props.sla.resolution;

    return {
        first: first?.status === 'met' ? 'Met' : first?.status === 'breached' ? 'Breached' : 'Pending',
        resolution: resolution?.status === 'met' ? 'Met' : resolution?.status === 'breached' ? 'Breached' : 'Pending',
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
const linkAsset = () => assetForm.post(`/tickets/${props.ticket.id}/assets`, {
    preserveScroll: true,
    onSuccess: () => assetForm.reset('asset_id'),
});
const unlinkAsset = (assetId) => router.delete(`/tickets/${props.ticket.id}/assets/${assetId}`, { preserveScroll: true });

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
                <h2 class="text-sm font-semibold text-slate-900">Ticket details</h2>
            </div>

            <div class="min-h-0 flex-1 overflow-y-auto divide-y divide-slate-100">
                <section v-if="ticket.contact" class="px-4 py-3">
                    <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-400">Requester</p>
                    <div class="mt-2 flex items-center gap-2">
                        <AppAvatar :name="ticket.contact.name" :email="ticket.contact.email" size="sm" />
                        <div class="min-w-0">
                            <p class="truncate text-sm font-medium text-slate-900">{{ ticket.contact.name || ticket.contact.email }}</p>
                            <p v-if="ticket.contact.email" class="truncate text-xs text-slate-500">{{ ticket.contact.email }}</p>
                        </div>
                    </div>
                </section>

                <section v-if="ticket.ccs?.length" class="px-4 py-3">
                    <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-400">CC</p>
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
                            <dt class="text-xs text-slate-500">Status</dt>
                            <dd>
                                <span class="rounded-full px-2 py-0.5 text-xs font-medium" :class="statusBadgeClass(ticket.status?.name)">
                                    {{ ticket.status?.name || '—' }}
                                </span>
                            </dd>
                        </div>
                        <div class="flex items-center justify-between gap-3">
                            <dt class="text-xs text-slate-500">Priority</dt>
                            <dd>
                                <span class="rounded-full px-2 py-0.5 text-xs font-medium" :class="priorityBadgeClass(ticket.priority?.name)">
                                    {{ ticket.priority?.name || '—' }}
                                </span>
                            </dd>
                        </div>
                        <div class="flex items-center justify-between gap-3">
                            <dt class="text-xs text-slate-500">Department</dt>
                            <dd class="truncate text-xs font-medium text-slate-800">{{ departmentName }}</dd>
                        </div>
                        <div class="flex items-center justify-between gap-3">
                            <dt class="text-xs text-slate-500">Team</dt>
                            <dd class="truncate text-xs font-medium text-slate-800">{{ teamName }}</dd>
                        </div>
                        <div class="flex items-center justify-between gap-3">
                            <dt class="text-xs text-slate-500">Assignee</dt>
                            <dd class="flex min-w-0 items-center gap-1.5">
                                <AppAvatar
                                    v-if="assignee"
                                    :name="assignee.name"
                                    :email="assignee.email"
                                    size="sm"
                                />
                                <span class="truncate text-xs font-medium text-slate-800">{{ assignee?.name || 'Unassigned' }}</span>
                            </dd>
                        </div>
                    </dl>
                </section>

                <section v-if="ticketCustomFields.length" class="px-4 py-3">
                    <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-400">Custom fields</p>
                    <dl class="mt-2 space-y-1.5">
                        <div v-for="field in ticketCustomFields" :key="field.name" class="flex items-start justify-between gap-3 text-xs">
                            <dt class="text-slate-500">{{ field.label }}</dt>
                            <dd class="max-w-[55%] text-right font-medium text-slate-800">{{ ticket.custom_fields[field.name] }}</dd>
                        </div>
                    </dl>
                </section>

                <section v-if="slaSummary" class="px-4 py-3">
                    <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-400">SLA</p>
                    <dl class="mt-2 space-y-1.5">
                        <div class="flex items-center justify-between gap-3 text-xs">
                            <dt class="text-slate-500">First response</dt>
                            <dd class="font-medium" :class="slaSummary.first === 'Met' ? 'text-emerald-700' : slaSummary.first === 'Breached' ? 'text-red-600' : 'text-slate-800'">{{ slaSummary.first }}</dd>
                        </div>
                        <div class="flex items-center justify-between gap-3 text-xs">
                            <dt class="text-slate-500">Resolution</dt>
                            <dd class="font-medium" :class="slaSummary.resolution === 'Met' ? 'text-emerald-700' : slaSummary.resolution === 'Breached' ? 'text-red-600' : 'text-slate-800'">{{ slaSummary.resolution }}</dd>
                        </div>
                    </dl>
                </section>

                <section class="flex items-center justify-between gap-3 px-4 py-3">
                    <div class="min-w-0">
                        <p class="text-xs text-slate-500">Watchers</p>
                        <p class="text-sm font-medium text-slate-800">{{ ticket.watchers?.length || 0 }}</p>
                    </div>
                    <button
                        type="button"
                        class="rounded-md border border-slate-200 px-2.5 py-1 text-xs font-medium text-slate-700 transition hover:bg-slate-50"
                        @click="toggleWatch"
                    >
                        {{ isWatching ? 'Unwatch' : 'Watch' }}
                    </button>
                </section>

                <section v-if="ticket.assets?.length" class="px-4 py-3">
                    <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-400">Assets</p>
                    <ul class="mt-2 space-y-1">
                        <li v-for="asset in ticket.assets.slice(0, 3)" :key="asset.id">
                            <Link :href="`/assets/${asset.id}`" class="truncate text-xs text-blue-600 hover:text-blue-700">{{ asset.asset_tag }}</Link>
                        </li>
                        <li v-if="ticket.assets.length > 3" class="text-xs text-slate-500">+{{ ticket.assets.length - 3 }} more</li>
                    </ul>
                </section>

                <section v-if="csat?.submitted" class="px-4 py-3">
                    <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-400">CSAT</p>
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
                        <span class="text-sm font-medium text-slate-900">Edit details</span>
                        <svg
                            class="h-4 w-4 shrink-0 text-slate-400 transition-transform duration-200"
                            :class="isPanelOpen('edit') ? 'rotate-180' : ''"
                            fill="none"
                            viewBox="0 0 24 24"
                            stroke="currentColor"
                            stroke-width="2"
                        >
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                        </svg>
                    </button>
                    <div v-show="isPanelOpen('edit')" class="border-t border-slate-100 px-4 pb-4 pt-3">
                        <form class="space-y-3" @submit.prevent="update">
                            <div>
                                <label class="mb-1 block text-xs font-medium text-slate-600">Requester</label>
                                <RequesterField
                                    v-model:contact-id="updateForm.contact_id"
                                    v-model:requester-email="updateForm.requester_email"
                                    v-model:requester-name="updateForm.requester_name"
                                    :initial-contact="ticket.contact"
                                    :error="updateForm.errors.contact_id || updateForm.errors.requester_email"
                                />
                            </div>
                            <div>
                                <label class="mb-1 block text-xs font-medium text-slate-600">CC</label>
                                <CcEmailField v-model="updateForm.cc_emails" :error="updateForm.errors.cc_emails" />
                            </div>
                            <div>
                                <label class="mb-1 block text-xs font-medium text-slate-600">Status</label>
                                <select v-model="updateForm.ticket_status_id" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                                    <option v-for="status in statuses" :key="status.id" :value="status.id">{{ status.name }}</option>
                                </select>
                            </div>
                            <div>
                                <label class="mb-1 block text-xs font-medium text-slate-600">Priority</label>
                                <select v-model="updateForm.ticket_priority_id" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                                    <option v-for="priority in priorities" :key="priority.id" :value="priority.id">{{ priority.name }}</option>
                                </select>
                            </div>
                            <div>
                                <label class="mb-1 block text-xs font-medium text-slate-600">Department</label>
                                <select v-model="updateForm.department_id" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                                    <option value="">None</option>
                                    <option v-for="department in departments" :key="department.id" :value="department.id">{{ department.name }}</option>
                                </select>
                            </div>
                            <div>
                                <label class="mb-1 block text-xs font-medium text-slate-600">Team</label>
                                <select v-model="updateForm.team_id" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                                    <option value="">None</option>
                                    <option v-for="team in filteredTeams" :key="team.id" :value="team.id">{{ team.name }}</option>
                                </select>
                            </div>
                            <div>
                                <label class="mb-1 block text-xs font-medium text-slate-600">Assignee</label>
                                <select v-model="updateForm.assigned_to" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                                    <option value="">Unassigned</option>
                                    <option v-for="agent in agents" :key="agent.id" :value="agent.id">{{ agent.name }}</option>
                                </select>
                            </div>
                            <CustomFields
                                v-model="updateForm.custom_fields"
                                :definitions="customFieldDefinitions"
                                :errors="updateForm.errors"
                            />
                            <button type="submit" class="w-full rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50" :disabled="updateForm.processing">Save changes</button>
                        </form>
                    </div>
                </section>

                <section v-if="sla?.active">
                    <button
                        type="button"
                        class="flex w-full items-center justify-between px-4 py-2.5 text-left transition hover:bg-slate-50"
                        @click="togglePanel('sla')"
                    >
                        <span class="text-sm font-medium text-slate-900">SLA details</span>
                        <svg
                            class="h-4 w-4 shrink-0 text-slate-400 transition-transform duration-200"
                            :class="isPanelOpen('sla') ? 'rotate-180' : ''"
                            fill="none"
                            viewBox="0 0 24 24"
                            stroke="currentColor"
                            stroke-width="2"
                        >
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                        </svg>
                    </button>
                    <div v-show="isPanelOpen('sla')" class="border-t border-slate-100 px-4 pb-4 pt-3">
                        <TicketSlaPanel :sla="sla" />
                    </div>
                </section>

                <section>
                    <button
                        type="button"
                        class="flex w-full items-center justify-between px-4 py-2.5 text-left transition hover:bg-slate-50"
                        @click="togglePanel('assets')"
                    >
                        <span class="text-sm font-medium text-slate-900">Linked assets</span>
                        <svg
                            class="h-4 w-4 shrink-0 text-slate-400 transition-transform duration-200"
                            :class="isPanelOpen('assets') ? 'rotate-180' : ''"
                            fill="none"
                            viewBox="0 0 24 24"
                            stroke="currentColor"
                            stroke-width="2"
                        >
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                        </svg>
                    </button>
                    <div v-show="isPanelOpen('assets')" class="border-t border-slate-100 px-4 pb-4 pt-3">
                        <ul class="space-y-2 text-sm">
                            <li v-for="asset in ticket.assets" :key="asset.id" class="flex items-center justify-between gap-2">
                                <Link :href="`/assets/${asset.id}`" class="text-blue-600 hover:text-blue-700">{{ asset.asset_tag }} — {{ asset.name }}</Link>
                                <button type="button" class="text-xs text-red-600 hover:text-red-700" @click="unlinkAsset(asset.id)">Remove</button>
                            </li>
                            <li v-if="!ticket.assets?.length" class="text-slate-500">No linked assets.</li>
                        </ul>
                        <form class="mt-3 flex gap-2" @submit.prevent="linkAsset">
                            <select v-model="assetForm.asset_id" required class="min-w-0 flex-1 rounded-lg border border-slate-300 px-3 py-2 text-sm">
                                <option value="">Link asset...</option>
                                <option v-for="asset in assetOptions" :key="asset.id" :value="asset.id">{{ asset.asset_tag }} — {{ asset.name }}</option>
                            </select>
                            <button type="submit" class="rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-700 hover:bg-slate-50" :disabled="assetForm.processing">Link</button>
                        </form>
                    </div>
                </section>

                <section>
                    <button
                        type="button"
                        class="flex w-full items-center justify-between px-4 py-2.5 text-left transition hover:bg-slate-50"
                        @click="togglePanel('watchers')"
                    >
                        <span class="text-sm font-medium text-slate-900">All watchers</span>
                        <svg
                            class="h-4 w-4 shrink-0 text-slate-400 transition-transform duration-200"
                            :class="isPanelOpen('watchers') ? 'rotate-180' : ''"
                            fill="none"
                            viewBox="0 0 24 24"
                            stroke="currentColor"
                            stroke-width="2"
                        >
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                        </svg>
                    </button>
                    <div v-show="isPanelOpen('watchers')" class="border-t border-slate-100 px-4 pb-4 pt-3">
                        <ul class="space-y-1 text-sm text-slate-700">
                            <li v-for="watcher in ticket.watchers" :key="watcher.id">{{ watcher.name }}</li>
                            <li v-if="!ticket.watchers?.length" class="text-slate-500">No watchers.</li>
                        </ul>
                    </div>
                </section>

                <section v-if="hasMergeSection">
                    <button
                        type="button"
                        class="flex w-full items-center justify-between px-4 py-2.5 text-left transition hover:bg-slate-50"
                        @click="togglePanel('merge')"
                    >
                        <span class="text-sm font-medium text-slate-900">Merge ticket</span>
                        <svg
                            class="h-4 w-4 shrink-0 text-slate-400 transition-transform duration-200"
                            :class="isPanelOpen('merge') ? 'rotate-180' : ''"
                            fill="none"
                            viewBox="0 0 24 24"
                            stroke="currentColor"
                            stroke-width="2"
                        >
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                        </svg>
                    </button>
                    <div v-show="isPanelOpen('merge')" class="border-t border-slate-100 px-4 pb-4 pt-3">
                        <form class="space-y-2" @submit.prevent="merge">
                            <select v-model="mergeForm.source_ticket_id" required class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                                <option value="">Select ticket</option>
                                <option v-for="candidate in mergeCandidates" :key="candidate.id" :value="candidate.id">{{ candidate.number }} — {{ candidate.subject }}</option>
                            </select>
                            <button type="submit" class="w-full rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50" :disabled="mergeForm.processing">Merge</button>
                        </form>
                    </div>
                </section>

                <section v-if="hasSplitSection">
                    <button
                        type="button"
                        class="flex w-full items-center justify-between px-4 py-2.5 text-left transition hover:bg-slate-50"
                        @click="togglePanel('split')"
                    >
                        <span class="text-sm font-medium text-slate-900">Split ticket</span>
                        <svg
                            class="h-4 w-4 shrink-0 text-slate-400 transition-transform duration-200"
                            :class="isPanelOpen('split') ? 'rotate-180' : ''"
                            fill="none"
                            viewBox="0 0 24 24"
                            stroke="currentColor"
                            stroke-width="2"
                        >
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                        </svg>
                    </button>
                    <div v-show="isPanelOpen('split')" class="border-t border-slate-100 px-4 pb-4 pt-3">
                        <form class="space-y-2" @submit.prevent="split">
                            <select v-model="splitForm.from_message_id" required class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                                <option value="">From message</option>
                                <option v-for="message in ticket.messages" :key="message.id" :value="message.id">
                                    {{ message.user?.name || 'System' }} — {{ message.body.slice(0, 40) }}...
                                </option>
                            </select>
                            <input v-model="splitForm.subject" type="text" placeholder="New ticket subject (optional)" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" />
                            <button type="submit" class="w-full rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50" :disabled="splitForm.processing">Split</button>
                        </form>
                    </div>
                </section>

                <section v-if="ticket.merged_tickets?.length" class="px-4 py-3">
                    <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-400">Merged tickets</p>
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
                        <span class="text-sm font-medium text-slate-900">Activity</span>
                        <div class="flex items-center gap-2">
                            <span v-if="lifecycle.length" class="text-[10px] text-slate-400">{{ lifecycle.length }}</span>
                            <svg
                                class="h-4 w-4 shrink-0 text-slate-400 transition-transform duration-200"
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
                    <div v-show="isPanelOpen('activity')" class="border-t border-slate-100 px-4 pb-4 pt-3">
                        <TicketLifecycle compact :lifecycle="lifecycle" />
                    </div>
                </section>
            </div>
        </div>
    </component>
</template>
