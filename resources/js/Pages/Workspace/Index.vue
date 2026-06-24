<script setup>
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
import AgentLayout from '../../Layouts/AgentLayout.vue';
import AgentCopilotPanel from '../../Components/AgentCopilotPanel.vue';
import AppAvatar from '../../Components/AppAvatar.vue';
import AppBadge from '../../Components/ui/AppBadge.vue';
import AppDropdown from '../../Components/ui/AppDropdown.vue';
import TicketComposerDock from '../../Components/TicketComposerDock.vue';
import TicketConversation from '../../Components/TicketConversation.vue';
import TicketDetailsSidebar from '../../Components/TicketDetailsSidebar.vue';
import TicketCollisionBanner from '../../Components/TicketCollisionBanner.vue';
import OnboardingTooltip from '../../Components/OnboardingTooltip.vue';
import { isEmptyRichText } from '../../composables/useRichText.js';
import UnreadBadge from '../../Components/UnreadBadge.vue';
import { useTicketPolling } from '../../composables/useTicketPolling.js';
import { applyUnreadCounts, bumpQueueUnread, clearQueueUnread, markTicketRead } from '../../composables/useTicketMarkRead.js';
import { getSharedRealtimeClient } from '../../lib/realtimeClient.js';
import { workspaceChannel } from '../../lib/realtimeChannels.js';
import { fetchTicketRealtimeToken } from '../../lib/realtimeTokens.js';
import { csrfHeaders } from '../../support/csrf.js';
import { useI18n } from 'vue-i18n';
import { useTicketLazyPanels } from '../../composables/useTicketLazyPanels.js';
import { useDateTime } from '../../composables/useDateTime.js';
import { useWorkspaceQueueKeyboard } from '../../composables/useWorkspaceQueueKeyboard.js';
import { ticketPriorityBadgeVariant, ticketPriorityDotClass, ticketStatusBadgeVariant } from '../../composables/useTicketBadgeVariants.js';

const props = defineProps({
    queue: Object,
    selectedTicket: Object,
    draft: Object,
    ticketViews: Array,
    statuses: Array,
    priorities: Array,
    agents: Array,
    departments: Array,
    teams: Array,
    filters: Object,
    activeViewId: Number,
    currentUserId: Number,
    sla: Object,
    mergeCandidates: { type: Array, default: () => [] },
    assetOptions: { type: Array, default: () => [] },
    customFieldDefinitions: { type: Array, default: () => [] },
    issueProviders: { type: Array, default: () => [] },
    approval: { type: Object, default: null },
    canDecideApproval: { type: Boolean, default: false },
    changeRecord: { type: Object, default: null },
    problemRecord: { type: Object, default: null },
    incidentCandidates: { type: Array, default: () => [] },
    changeRiskOptions: { type: Array, default: () => [] },
    majorIncident: { type: Object, default: null },
    canDeclareMajorIncident: { type: Boolean, default: false },
});

const { formatDateTime, formatDate } = useDateTime();

const { t } = useI18n();

const page = usePage();
const aiEnabled = computed(() => page.props.ai?.enabled ?? false);
const filtersOpen = ref(true);
const mobilePanel = ref('queue');

const filterForm = useForm({
    status_id: props.filters?.status_id ?? '',
    priority_id: props.filters?.priority_id ?? '',
    assigned_to: props.filters?.assigned_to ?? '',
    unassigned: props.filters?.unassigned ?? false,
    mine: props.filters?.mine ?? false,
    search: props.filters?.search ?? '',
    watching: props.filters?.watching ?? false,
});

const queueItems = ref([...(props.queue?.data ?? [])]);
const ticket = ref(props.selectedTicket);
const messages = ref(props.selectedTicket?.messages ?? []);
const composerBody = ref(props.draft?.body ?? '');
const composerInternal = ref(props.draft?.is_internal ?? false);
const composerAttachments = ref([]);
const editorKey = ref(0);
const sending = ref(false);
const draftSaving = ref(false);
const viewers = ref([]);

let draftTimer = null;
let presenceTimer = null;
let queuePollTimer = null;
let subscribedTicketChannel = null;
let ticketRealtimeHandler = null;
let workspaceRealtimeHandler = null;

const jsonHeaders = () => ({
    Accept: 'application/json',
    'Content-Type': 'application/json',
    ...csrfHeaders(),
});

const selectedId = computed(() => ticket.value?.id ?? null);
const { panels: lazyPanels } = useTicketLazyPanels(selectedId);

const isComposing = computed(() => !isEmptyRichText(composerBody.value) && !sending.value);

const queueCount = computed(() => queueItems.value.length);

const hasFilters = computed(() => {
    return filterForm.status_id
        || filterForm.priority_id
        || filterForm.assigned_to
        || filterForm.unassigned
        || filterForm.mine
        || filterForm.search
        || filterForm.watching;
});

const assigneeScope = computed({
    get() {
        if (filterForm.mine) {
            return 'mine';
        }

        if (filterForm.unassigned) {
            return 'unassigned';
        }

        return filterForm.assigned_to || '';
    },
    set(value) {
        filterForm.mine = value === 'mine';
        filterForm.unassigned = value === 'unassigned';
        filterForm.assigned_to = value && value !== 'mine' && value !== 'unassigned' ? Number(value) : '';
    },
});

const filterParams = () => ({
    status_id: filterForm.status_id || undefined,
    priority_id: filterForm.priority_id || undefined,
    assigned_to: filterForm.assigned_to || undefined,
    unassigned: filterForm.unassigned || undefined,
    mine: filterForm.mine || undefined,
    search: filterForm.search || undefined,
    watching: filterForm.watching || undefined,
    view_id: props.activeViewId || undefined,
});

const plainReplyToHtml = (text) => {
    const escaped = text
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;');

    return escaped
        .split(/\n{2,}/)
        .map((paragraph) => `<p>${paragraph.replace(/\n/g, '<br>')}</p>`)
        .join('');
};

const syncFromProps = () => {
    queueItems.value = [...(props.queue?.data ?? [])];
    ticket.value = props.selectedTicket;
    messages.value = props.selectedTicket?.messages ?? [];
    composerBody.value = props.draft?.body ?? '';
    composerInternal.value = props.draft?.is_internal ?? false;
    viewers.value = [];
    resetPollCursor();
    resubscribeTicketRealtime();
};

watch(selectedId, (id, previous) => {
    if (previous && previous !== id) {
        leavePresenceFor(previous);
    }

    if (id) {
        sendPresence();
        resubscribeTicketRealtime();
    } else {
        unsubscribeTicketRealtime();

        if (typeof window !== 'undefined' && window.innerWidth < 1024) {
            mobilePanel.value = 'queue';
        }
    }
});

watch(isComposing, () => {
    sendPresence();
});

watch(() => [props.selectedTicket, props.queue, props.draft], syncFromProps);

watch(
    () => props.filters,
    (filters) => {
        filterForm.status_id = filters?.status_id ?? '';
        filterForm.priority_id = filters?.priority_id ?? '';
        filterForm.assigned_to = filters?.assigned_to ?? '';
        filterForm.unassigned = filters?.unassigned ?? false;
        filterForm.mine = filters?.mine ?? false;
        filterForm.search = filters?.search ?? '';
        filterForm.watching = filters?.watching ?? false;
    },
    { deep: true },
);

watch([composerBody, composerInternal], () => {
    if (!selectedId.value) {
        return;
    }
    clearTimeout(draftTimer);
    draftTimer = setTimeout(saveDraft, 600);
});

const selectTicket = (ticketId) => {
    clearQueueUnread(queueItems.value, ticketId);
    markTicketRead(ticketId);

    if (typeof window !== 'undefined' && window.innerWidth < 1024) {
        mobilePanel.value = 'conversation';
    }

    router.get(`/workspace/tickets/${ticketId}`, filterParams(), {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
};

const mobileTabs = computed(() => [
    { id: 'queue', label: t('workspace.mobile_tab_queue'), badge: queueCount.value, disabled: false },
    { id: 'conversation', label: t('workspace.mobile_tab_conversation'), badge: 0, disabled: false },
    { id: 'details', label: t('workspace.mobile_tab_details'), badge: 0, disabled: !selectedId.value },
]);

const setMobilePanel = (panelId) => {
    const tab = mobileTabs.value.find((item) => item.id === panelId);

    if (!tab || tab.disabled) {
        return;
    }

    mobilePanel.value = panelId;
};

useWorkspaceQueueKeyboard({
    enabled: computed(() => true),
    items: queueItems,
    selectedId,
    onSelect: selectTicket,
});

const pollQueueUnread = async () => {
    const ticketIds = queueItems.value.map((item) => item.id);

    if (!ticketIds.length) {
        return;
    }

    const params = new URLSearchParams({
        ticket_ids: ticketIds.join(','),
    });

    try {
        const response = await fetch(`/workspace/queue/poll?${params.toString()}`, {
            headers: jsonHeaders(),
        });

        if (!response.ok) {
            return;
        }

        const data = await response.json();
        applyUnreadCounts(queueItems.value, data.unread_counts ?? {});

        if (data.tickets?.length) {
            mergeQueueUpdates(data.tickets);
        }
    } catch {
    }
};

const applyFilters = () => {
    const path = selectedId.value ? `/workspace/tickets/${selectedId.value}` : '/workspace';
    router.get(path, filterParams(), { preserveState: true, replace: true });
};

const loadView = (viewId) => {
    const path = selectedId.value ? `/workspace/tickets/${selectedId.value}` : '/workspace';
    router.get(path, { view_id: viewId }, { preserveState: true, replace: true });
};

const clearFilters = () => {
    filterForm.reset();
    router.get('/workspace', {}, { preserveState: true, replace: true });
};

const applyTicketUpdate = (snapshot) => {
    if (!snapshot?.id) {
        return;
    }

    if (ticket.value?.id === snapshot.id) {
        ticket.value = { ...ticket.value, ...snapshot };
    }

    mergeQueueUpdates([snapshot]);
};

const appendRealtimeMessage = (message) => {
    if (!message?.id || message.ticket_id !== selectedId.value) {
        return;
    }

    if (messages.value.some((item) => item.id === message.id)) {
        return;
    }

    messages.value = [...messages.value, message];
};

const { resetPollCursor } = useTicketPolling(selectedId, messages, {
    viewersRef: viewers,
    onTicketUpdate: applyTicketUpdate,
});

const handleRealtimeEvent = (payload) => {
    if (payload.event === 'message.created') {
        const message = payload.data?.message;

        if (message?.ticket_id === selectedId.value) {
            appendRealtimeMessage(message);
        } else if (message?.contact_id || message?.author_type === 'visitor') {
            bumpQueueUnread(queueItems.value, message.ticket_id);
        }

        return;
    }

    if (payload.event === 'ticket.updated') {
        applyTicketUpdate(payload.data?.ticket);

        return;
    }

    if (payload.event === 'presence.updated' && payload.data?.ticket_id === selectedId.value) {
        viewers.value = payload.data?.viewers ?? [];
    }
};

const unsubscribeTicketRealtime = () => {
    const client = getSharedRealtimeClient(page.props.realtime);

    if (client && subscribedTicketChannel && ticketRealtimeHandler) {
        client.unsubscribe(subscribedTicketChannel, ticketRealtimeHandler);
    }

    subscribedTicketChannel = null;
    ticketRealtimeHandler = null;
};

const resubscribeTicketRealtime = async () => {
    unsubscribeTicketRealtime();

    const client = getSharedRealtimeClient(page.props.realtime);
    const ticketId = selectedId.value;

    if (!client || !ticketId) {
        return;
    }

    const credentials = await fetchTicketRealtimeToken(ticketId);

    if (!credentials?.token) {
        return;
    }

    subscribedTicketChannel = credentials.channel;
    ticketRealtimeHandler = handleRealtimeEvent;
    client.subscribe(subscribedTicketChannel, ticketRealtimeHandler, credentials.token);
};

const setupWorkspaceRealtime = () => {
    const client = getSharedRealtimeClient(page.props.realtime);

    if (!client) {
        return;
    }

    workspaceRealtimeHandler = (payload) => {
        if (payload.event === 'queue.updated') {
            mergeQueueUpdates([payload.data?.ticket].filter(Boolean));
        }
    };

    client.subscribe(workspaceChannel(page.props.tenantId), workspaceRealtimeHandler);
    resubscribeTicketRealtime();
};

const teardownWorkspaceRealtime = () => {
    const client = getSharedRealtimeClient(page.props.realtime);

    unsubscribeTicketRealtime();

    if (client && workspaceRealtimeHandler) {
        client.unsubscribe(workspaceChannel(page.props.tenantId), workspaceRealtimeHandler);
    }

    workspaceRealtimeHandler = null;
};

const shouldRemoveFromQueue = (item) => {
    if (!item) {
        return true;
    }

    const statusSlug = (item.status?.slug || '').toLowerCase();
    const statusName = (item.status?.name || '').toLowerCase();

    if (item.status?.is_closed || statusSlug === 'closed' || statusName.includes('closed') || statusName.includes('resolved')) {
        return true;
    }

    return Boolean(item.snoozed_until && new Date(item.snoozed_until) > new Date());
};

const mergeQueueUpdates = (updates) => {
    if (!updates?.length) {
        return;
    }

    const map = new Map(queueItems.value.map((item) => [item.id, item]));
    updates.forEach((item) => map.set(item.id, { ...map.get(item.id), ...item }));
    queueItems.value = Array.from(map.values())
        .filter((item) => !shouldRemoveFromQueue(item))
        .sort((a, b) => new Date(b.updated_at) - new Date(a.updated_at));
};

const sendPresence = async () => {
    if (!selectedId.value) {
        return;
    }

    await fetch(`/workspace/tickets/${selectedId.value}/presence`, {
        method: 'POST',
        headers: jsonHeaders(),
        body: JSON.stringify({ composing: isComposing.value }),
    });
};

const leavePresenceFor = async (ticketId) => {
    if (!ticketId) {
        return;
    }

    await fetch(`/workspace/tickets/${ticketId}/presence`, {
        method: 'DELETE',
        headers: jsonHeaders(),
    });
};

const leavePresence = () => leavePresenceFor(selectedId.value);

const saveDraft = async () => {
    if (!selectedId.value) {
        return;
    }
    draftSaving.value = true;
    await fetch(`/workspace/tickets/${selectedId.value}/draft`, {
        method: 'PUT',
        headers: jsonHeaders(),
        body: JSON.stringify({ body: composerBody.value, is_internal: composerInternal.value }),
    });
    draftSaving.value = false;
};

const sendReply = async () => {
    if (isEmptyRichText(composerBody.value) || !selectedId.value || sending.value) {
        return;
    }
    sending.value = true;
    const res = await fetch(`/workspace/tickets/${selectedId.value}/reply`, {
        method: 'POST',
        headers: jsonHeaders(),
        body: JSON.stringify({ body: composerBody.value, is_internal: composerInternal.value }),
    });
    if (res.ok) {
        const data = await res.json();
        ticket.value = data.ticket;
        messages.value = data.ticket.messages ?? [];
        composerBody.value = '';
        composerInternal.value = false;
        composerAttachments.value = [];
        editorKey.value += 1;
        resetPollCursor();
    }
    sending.value = false;
};

const onComposerKeydown = (event) => {
    if ((event.metaKey || event.ctrlKey) && event.key === 'Enter') {
        event.preventDefault();
        sendReply();
    }
};

const quickUpdate = async (field, value) => {
    if (!selectedId.value) {
        return;
    }
    const payload = { [field]: value === '' ? null : value };
    const res = await fetch(`/workspace/tickets/${selectedId.value}`, {
        method: 'PATCH',
        headers: jsonHeaders(),
        body: JSON.stringify(payload),
    });
    if (res.ok) {
        ticket.value = await res.json();
        mergeQueueUpdates([ticket.value]);
    }
};

const snoozeTicket = async (minutes) => {
    if (!selectedId.value) {
        return;
    }

    const res = await fetch(`/workspace/tickets/${selectedId.value}/snooze`, {
        method: 'POST',
        headers: jsonHeaders(),
        body: JSON.stringify({ minutes }),
    });

    if (res.ok) {
        ticket.value = await res.json();
        mergeQueueUpdates([ticket.value]);
    }
};

const unsnoozeTicket = async () => {
    if (!selectedId.value) {
        return;
    }

    const res = await fetch(`/workspace/tickets/${selectedId.value}/snooze`, {
        method: 'DELETE',
        headers: jsonHeaders(),
    });

    if (res.ok) {
        ticket.value = await res.json();
        mergeQueueUpdates([ticket.value]);
    }
};

const snoozeLabel = computed(() => {
    if (!ticket.value?.snoozed_until) {
        return null;
    }

    return formatDateTime(ticket.value.snoozed_until);
});

const formatRelative = (value) => {
    const date = new Date(value);
    const diff = Date.now() - date.getTime();
    const mins = Math.floor(diff / 60000);
    if (mins < 1) return 'Just now';
    if (mins < 60) return `${mins}m ago`;
    const hours = Math.floor(mins / 60);
    if (hours < 24) return `${hours}h ago`;
    const days = Math.floor(hours / 24);
    if (days < 7) return `${days}d ago`;

    return formatDate(date);
};

onMounted(() => {
    setupWorkspaceRealtime();
    presenceTimer = setInterval(sendPresence, 15000);
    queuePollTimer = setInterval(pollQueueUnread, 8000);
    pollQueueUnread();

    if (selectedId.value) {
        sendPresence();
        markTicketRead(selectedId.value);
    }
});

onUnmounted(() => {
    clearInterval(presenceTimer);
    clearInterval(queuePollTimer);
    clearTimeout(draftTimer);
    teardownWorkspaceRealtime();
    leavePresence();
});
</script>

<template>
    <Head :title="$t('workspace.workspace')" />
    <AgentLayout>
        <div class="shrink-0 border-b agent-border-subtle px-3 py-2">
            <OnboardingTooltip tip-key="workspace-inbox" :message="$t('workspace.onboarding_tip')" />
        </div>
        <div class="flex min-h-0 flex-1 flex-col bg-slate-100 dark:bg-slate-900">
            <div class="flex min-h-0 flex-1">
            <aside
                class="shrink-0 flex-col overflow-hidden border-r agent-border agent-panel lg:w-80"
                :class="mobilePanel === 'queue' ? 'flex w-full' : 'hidden lg:flex'"
            >
                <div class="shrink-0 border-b agent-border-subtle agent-panel-muted px-3 py-2.5">
                    <div class="flex items-center justify-between gap-2">
                        <div class="flex items-center gap-2">
                            <h2 class="text-sm font-semibold agent-text">{{ $t('workspace.queue') }}</h2>
                            <span class="rounded-full bg-slate-200/80 px-2 py-0.5 text-xs font-semibold tabular-nums text-slate-600 dark:text-slate-400 dark:bg-slate-700/80 dark:text-slate-300">{{ queueCount }}</span>
                        </div>
                        <button
                            type="button"
                            class="rounded-lg p-1.5 text-slate-400 dark:text-slate-500 transition agent-hover-surface hover:text-slate-600 dark:text-slate-400 dark:hover:text-slate-200"
                            :title="filtersOpen ? 'Hide filters' : 'Show filters'"
                            @click="filtersOpen = !filtersOpen"
                        >
                            <svg class="h-4 w-4 transition-transform" :class="filtersOpen ? 'rotate-180' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                            </svg>
                        </button>
                    </div>

                    <div v-show="filtersOpen" class="mt-3 space-y-2.5">
                        <div class="flex flex-wrap gap-1.5">
                            <button
                                type="button"
                                class="rounded-full px-2.5 py-1 text-xs font-semibold transition"
                                :class="!activeViewId ? 'bg-slate-900 text-white dark:bg-slate-100 dark:bg-slate-900 dark:text-slate-900 dark:text-slate-100' : 'agent-panel agent-text-muted ring-1 agent-border agent-hover-surface'"
                                @click="clearFilters"
                            >{{ $t('workspace.all') }}</button>
                            <button
                                v-for="view in ticketViews"
                                :key="view.id"
                                type="button"
                                class="rounded-full px-2.5 py-1 text-xs font-semibold transition"
                                :class="activeViewId === view.id ? 'bg-slate-900 text-white dark:bg-slate-100 dark:bg-slate-900 dark:text-slate-900 dark:text-slate-100' : 'agent-panel agent-text-muted ring-1 agent-border agent-hover-surface'"
                                @click="loadView(view.id)"
                            >
                                {{ view.name }}
                            </button>
                        </div>

                        <form class="space-y-2" @submit.prevent="applyFilters">
                            <div class="relative">
                                <svg class="pointer-events-none absolute left-2.5 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400 dark:text-slate-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                                </svg>
                                <input
                                    v-model="filterForm.search"
                                    type="text"
                                    :placeholder="$t('workspace.search_tickets_ellipsis')"
                                    class="w-full rounded-lg border py-2 pl-9 pr-3 text-sm agent-input"
                                />
                            </div>

                            <div class="grid grid-cols-2 gap-2">
                                <select v-model="filterForm.status_id" class="rounded-lg border px-2 py-1.5 text-xs agent-input">
                                    <option value="">{{ $t('workspace.all_statuses') }}</option>
                                    <option v-for="status in statuses" :key="status.id" :value="status.id">{{ status.name }}</option>
                                </select>
                                <select v-model="filterForm.priority_id" class="rounded-lg border px-2 py-1.5 text-xs agent-input">
                                    <option value="">{{ $t('workspace.all_priorities') }}</option>
                                    <option v-for="priority in priorities" :key="priority.id" :value="priority.id">{{ priority.name }}</option>
                                </select>
                            </div>

                            <select v-model="assigneeScope" class="w-full rounded-lg border px-2 py-1.5 text-xs agent-input">
                                <option value="">{{ $t('workspace.all_assignees') }}</option>
                                <option value="mine">{{ $t('tickets.assigned_to_me') }}</option>
                                <option value="unassigned">{{ $t('tickets.unassigned') }}</option>
                                <option v-for="agent in agents" :key="agent.id" :value="String(agent.id)">{{ agent.name }}</option>
                            </select>

                            <div class="flex items-center justify-between gap-2">
                                <label class="flex cursor-pointer items-center gap-2 text-xs font-medium agent-text-muted">
                                    <input v-model="filterForm.watching" type="checkbox" class="rounded border-slate-300 dark:border-slate-700 text-blue-600 focus:ring-blue-500/30" />
                                    {{ $t('workspace.watching_only') }}
                                </label>
                                <div class="flex items-center gap-2">
                                    <button v-if="hasFilters" type="button" class="text-xs font-medium text-slate-500 dark:text-slate-400 hover:text-slate-700 dark:hover:text-slate-300 dark:text-slate-300" @click="clearFilters">{{ $t('workspace.clear') }}</button>
                                    <button type="submit" class="rounded-lg bg-slate-900 px-3 py-1.5 text-xs font-semibold text-white hover:bg-slate-800">{{ $t('workspace.apply') }}</button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>

                <ul class="min-h-0 flex-1 space-y-0.5 overflow-y-auto p-2">
                    <li
                        v-for="item in queueItems"
                        :key="item.id"
                        class="cursor-pointer rounded-lg p-2.5 transition"
                        :class="selectedId === item.id ? 'bg-blue-50 ring-1 ring-blue-500/25 dark:bg-blue-950/40 dark:ring-blue-400/30' : 'agent-hover-row'"
                        @click="selectTicket(item.id)"
                    >
                        <div class="flex items-start gap-2">
                            <span class="mt-1.5 h-2 w-2 shrink-0 rounded-full" :class="ticketPriorityDotClass(item.priority?.name)" />
                            <div class="min-w-0 flex-1">
                                <div class="flex items-start justify-between gap-2">
                                    <p class="line-clamp-2 text-sm font-medium leading-snug agent-text">{{ item.subject }}</p>
                                    <div class="flex shrink-0 items-center gap-1.5">
                                        <UnreadBadge :count="item.unread_count ?? 0" />
                                        <span class="font-mono text-[10px] text-slate-400 dark:text-slate-500">{{ item.number }}</span>
                                    </div>
                                </div>
                                <div class="mt-1.5 flex flex-wrap items-center gap-1">
                                    <AppBadge size="sm" :variant="ticketStatusBadgeVariant(item.status?.name)">{{ item.status?.name }}</AppBadge>
                                    <AppBadge size="sm" :variant="ticketPriorityBadgeVariant(item.priority?.name)">{{ item.priority?.name }}</AppBadge>
                                </div>
                                <div class="mt-1.5 flex items-center justify-between gap-2">
                                    <span class="truncate text-xs text-slate-500 dark:text-slate-400">{{ item.assignee?.name || 'Unassigned' }}</span>
                                    <span class="shrink-0 text-[10px] text-slate-400 dark:text-slate-500">{{ formatRelative(item.updated_at) }}</span>
                                </div>
                            </div>
                        </div>
                    </li>
                    <li v-if="!queueItems.length" class="px-3 py-10 text-center">
                        <p class="text-sm font-medium text-slate-700 dark:text-slate-300">{{ $t('workspace.queue_is_empty') }}</p>
                        <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ $t('workspace.adjust_filters_or_create_a_ticket') }}</p>
                    </li>
                </ul>
            </aside>

            <div
                class="flex min-h-0 min-w-0 flex-1"
                :class="mobilePanel === 'details' ? 'hidden lg:flex' : 'flex'"
            >
                <section
                    v-if="ticket"
                    class="flex min-h-0 min-w-0 flex-1 basis-0 flex-col overflow-hidden agent-panel"
                    :class="mobilePanel === 'conversation' ? 'flex' : 'hidden lg:flex'"
                >
                    <div class="flex shrink-0 flex-wrap items-center gap-2 border-b agent-border px-3 py-2">
                        <span class="shrink-0 font-mono text-xs font-semibold text-blue-600">{{ ticket.number }}</span>
                        <span class="hidden h-3.5 w-px bg-slate-200 dark:bg-slate-700 sm:block" />
                        <h1 class="min-w-0 flex-1 truncate text-sm font-semibold agent-text" :title="ticket.subject">
                            {{ ticket.subject }}
                        </h1>
                        <div class="flex shrink-0 flex-wrap items-center gap-1.5">
                            <select
                                :value="ticket.ticket_status_id"
                                class="rounded-lg border px-2 py-1 text-xs font-medium agent-input"
                                @change="quickUpdate('ticket_status_id', Number($event.target.value))"
                            >
                                <option v-for="status in statuses" :key="status.id" :value="status.id">{{ status.name }}</option>
                            </select>
                            <select
                                :value="ticket.ticket_priority_id"
                                class="rounded-lg border px-2 py-1 text-xs font-medium agent-input"
                                @change="quickUpdate('ticket_priority_id', Number($event.target.value))"
                            >
                                <option v-for="priority in priorities" :key="priority.id" :value="priority.id">{{ priority.name }}</option>
                            </select>
                            <select
                                :value="ticket.assigned_to ?? ''"
                                class="hidden rounded-lg border px-2 py-1 text-xs font-medium agent-input sm:block"
                                @change="quickUpdate('assigned_to', $event.target.value ? Number($event.target.value) : '')"
                            >
                                <option value="">{{ $t('workspace.unassigned') }}</option>
                                <option v-for="agent in agents" :key="agent.id" :value="agent.id">{{ agent.name }}</option>
                            </select>
                            <AppDropdown align="end">
                                <template #trigger="{ toggle }">
                                    <button
                                        type="button"
                                        class="rounded-lg border px-2.5 py-1 text-xs font-semibold text-slate-700 agent-hover-surface dark:text-slate-300"
                                        @click="toggle"
                                    >
                                        {{ ticket.snoozed_until ? $t('workspace.snoozed') : $t('workspace.snooze') }}
                                    </button>
                                </template>
                                <template #default="{ close }">
                                    <button type="button" role="menuitem" class="agent-dropdown-item" @click="snoozeTicket(60); close()">{{ $t('workspace.1_hour') }}</button>
                                    <button type="button" role="menuitem" class="agent-dropdown-item" @click="snoozeTicket(240); close()">{{ $t('workspace.4_hours') }}</button>
                                    <button type="button" role="menuitem" class="agent-dropdown-item" @click="snoozeTicket(1440); close()">{{ $t('workspace.tomorrow') }}</button>
                                    <button type="button" role="menuitem" class="agent-dropdown-item" @click="snoozeTicket(10080); close()">{{ $t('workspace.1_week') }}</button>
                                    <button
                                        v-if="ticket.snoozed_until"
                                        type="button"
                                        role="menuitem"
                                        class="agent-dropdown-item-danger border-t agent-border-subtle"
                                        @click="unsnoozeTicket(); close()"
                                    >
                                        {{ $t('workspace.unsnooze') }}
                                    </button>
                                </template>
                            </AppDropdown>
                            <span v-if="snoozeLabel" class="hidden text-[10px] text-amber-700 dark:text-amber-300 lg:inline">Until {{ snoozeLabel }}</span>
                            <Link
                                :href="`/tickets/${ticket.id}`"
                                class="rounded-lg border px-2.5 py-1 text-xs font-semibold text-slate-700 agent-hover-surface dark:text-slate-300"
                            >
                                {{ $t('workspace.full_view') }}
                            </Link>
                        </div>
                    </div>

                    <TicketCollisionBanner :viewers="viewers" />

                    <div v-if="ticket.contact" class="flex shrink-0 items-center gap-2 border-b agent-border-subtle agent-panel-muted px-3 py-1.5">
                        <AppAvatar :name="ticket.contact.name" :email="ticket.contact.email" size="sm" />
                        <div class="min-w-0">
                            <p class="truncate text-xs font-medium text-slate-800 dark:text-slate-200">{{ ticket.contact.name || ticket.contact.email }}</p>
                            <p v-if="ticket.contact.email && ticket.contact.name" class="truncate text-[11px] text-slate-500 dark:text-slate-400">{{ ticket.contact.email }}</p>
                        </div>
                        <div v-if="ticket.ccs?.length" class="ml-auto hidden items-center gap-1 sm:flex">
                            <span class="text-[10px] font-medium uppercase tracking-wide text-slate-400 dark:text-slate-500">{{ $t('workspace.cc') }}</span>
                            <span class="truncate text-xs text-slate-600 dark:text-slate-400">{{ ticket.ccs.map((cc) => cc.email).join(', ') }}</span>
                        </div>
                    </div>

                    <div class="relative min-h-0 flex-1 overflow-hidden bg-[#e5ddd5]/30 px-3 py-2 dark:bg-slate-900/40">
                        <TicketConversation
                            :messages="messages"
                            :current-user-id="currentUserId"
                            fill
                        />
                    </div>

                    <form class="shrink-0 border-t agent-border agent-panel px-2 pb-3 pt-1" @submit.prevent="sendReply" @keydown="onComposerKeydown">
                        <TicketComposerDock
                            v-model:body="composerBody"
                            v-model:attachments="composerAttachments"
                            v-model:is-internal="composerInternal"
                            :processing="sending"
                            :ai-enabled="aiEnabled"
                            :ticket-id="selectedId"
                            :ai-base-path="`/workspace/tickets/${selectedId}/ai`"
                            :editor-key="editorKey"
                            :on-suggest-reply="(reply) => { composerBody = plainReplyToHtml(reply); composerInternal = false; }"
                            @submit="sendReply"
                        />
                        <p v-if="draftSaving" class="mt-1 px-1 text-[11px] text-slate-400 dark:text-slate-500">{{ $t('workspace.saving_draft_ellipsis') }}</p>
                    </form>
                </section>

                <div
                    v-else
                    class="flex min-h-0 flex-1 flex-col items-center justify-center agent-panel px-6 text-center"
                    :class="mobilePanel === 'conversation' ? 'flex' : 'hidden lg:flex'"
                >
                    <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-blue-50 dark:bg-blue-950/40 text-blue-500 dark:bg-blue-950/50 dark:text-blue-400">
                        <svg class="h-7 w-7" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" />
                        </svg>
                    </div>
                    <h3 class="mt-3 text-base font-semibold agent-text">{{ $t('workspace.select_a_ticket') }}</h3>
                    <p class="mt-1 max-w-xs text-sm agent-text-subtle">{{ $t('workspace.choose_a_conversation_from_the_queue_to_read_and_reply') }}</p>
                </div>

                <aside
                    v-if="ticket"
                    class="min-h-0 shrink-0 flex-col overflow-hidden border-l agent-border agent-panel xl:w-80"
                    :class="mobilePanel === 'details' ? 'flex w-full' : 'hidden xl:flex'"
                >
                    <TicketDetailsSidebar
                        embedded
                        :ticket="ticket"
                        :sla="sla"
                        :statuses="statuses"
                        :priorities="priorities"
                        :agents="agents"
                        :departments="departments"
                        :teams="teams"
                        :merge-candidates="mergeCandidates"
                        :asset-options="assetOptions"
                        :csat="lazyPanels.csat"
                        :current-user-id="currentUserId"
                        :custom-field-definitions="customFieldDefinitions"
                        :lifecycle="lazyPanels.lifecycle ?? []"
                        :side-conversations="lazyPanels.sideConversations"
                        :time-tracking="lazyPanels.timeTracking ?? { total_minutes: 0, entries: [] }"
                        :external-issues="lazyPanels.externalIssues"
                        :issue-providers="issueProviders"
                        :approval="approval"
                        :can-decide-approval="canDecideApproval"
                        :change-record="changeRecord"
                        :problem-record="problemRecord"
                        :incident-candidates="incidentCandidates"
                        :change-risk-options="changeRiskOptions"
                        :major-incident="majorIncident"
                        :can-declare-major-incident="canDeclareMajorIncident"
                    />
                </aside>
            </div>
            </div>

            <nav
                class="flex shrink-0 border-t agent-border agent-panel lg:hidden"
                role="tablist"
                :aria-label="$t('workspace.mobile_panels')"
            >
                <button
                    v-for="tab in mobileTabs"
                    :key="tab.id"
                    type="button"
                    role="tab"
                    class="relative flex min-h-[3rem] flex-1 flex-col items-center justify-center gap-0.5 px-2 py-2 text-[11px] font-semibold transition-ui"
                    :class="[
                        mobilePanel === tab.id ? 'text-blue-600 dark:text-blue-400' : 'agent-text-muted',
                        tab.disabled ? 'cursor-not-allowed opacity-40' : '',
                    ]"
                    :aria-selected="mobilePanel === tab.id"
                    :disabled="tab.disabled"
                    @click="setMobilePanel(tab.id)"
                >
                    <span>{{ tab.label }}</span>
                    <span
                        v-if="tab.badge"
                        class="absolute right-3 top-2 rounded-full bg-slate-200 px-1.5 py-0.5 text-[10px] tabular-nums text-slate-700 dark:bg-slate-700 dark:text-slate-200"
                    >
                        {{ tab.badge }}
                    </span>
                </button>
            </nav>
        </div>

        <AgentCopilotPanel
            v-if="aiEnabled && ticket"
            :ai-base-path="`/workspace/tickets/${selectedId}/ai`"
            :on-insert-reply="(reply) => { composerBody = plainReplyToHtml(reply); composerInternal = false; }"
        />
    </AgentLayout>
</template>
