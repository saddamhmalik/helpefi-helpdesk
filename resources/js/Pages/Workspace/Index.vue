<script setup>
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
import AgentLayout from '../../Layouts/AgentLayout.vue';
import AppAvatar from '../../Components/AppAvatar.vue';
import TicketComposerDock from '../../Components/TicketComposerDock.vue';
import TicketConversation from '../../Components/TicketConversation.vue';
import TicketDetailsSidebar from '../../Components/TicketDetailsSidebar.vue';
import TicketCollisionBanner from '../../Components/TicketCollisionBanner.vue';
import { isEmptyRichText } from '../../composables/useRichText.js';
import UnreadBadge from '../../Components/UnreadBadge.vue';
import { useTicketPolling } from '../../composables/useTicketPolling.js';
import { useTicketRealtimeMessages } from '../../composables/useTicketRealtimeMessages.js';
import { applyUnreadCounts, bumpQueueUnread, clearQueueUnread, markTicketRead } from '../../composables/useTicketMarkRead.js';
import { getSharedRealtimeClient } from '../../lib/realtimeClient.js';
import { ticketChannel, workspaceChannel } from '../../lib/realtimeChannels.js';
import { csrfHeaders } from '../../support/csrf.js';
import { useI18n } from 'vue-i18n';
import { useDateTime } from '../../composables/useDateTime.js';

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
    lifecycle: { type: Array, default: () => [] },
    mergeCandidates: { type: Array, default: () => [] },
    assetOptions: { type: Array, default: () => [] },
    csat: Object,
    customFieldDefinitions: { type: Array, default: () => [] },
    sideConversations: { type: Array, default: () => [] },
    timeTracking: { type: Object, default: null },
    externalIssues: { type: Array, default: () => [] },
});

const { formatDateTime, formatDate } = useDateTime();

const { t } = useI18n();

const page = usePage();
const aiEnabled = computed(() => page.props.ai?.enabled ?? false);
const filtersOpen = ref(true);

const filterForm = useForm({
    status_id: props.filters?.status_id ?? '',
    priority_id: props.filters?.priority_id ?? '',
    assigned_to: props.filters?.assigned_to ?? '',
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

const isComposing = computed(() => !isEmptyRichText(composerBody.value) && !sending.value);

const queueCount = computed(() => queueItems.value.length);

const hasFilters = computed(() => {
    return filterForm.status_id || filterForm.priority_id || filterForm.assigned_to || filterForm.search || filterForm.watching;
});

const filterParams = () => ({
    status_id: filterForm.status_id || undefined,
    priority_id: filterForm.priority_id || undefined,
    assigned_to: filterForm.assigned_to || undefined,
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
    }
});

watch(isComposing, () => {
    sendPresence();
});

watch(() => [props.selectedTicket, props.queue, props.draft], syncFromProps);

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

    router.get(`/workspace/tickets/${ticketId}`, filterParams(), {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
};

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

const { appendMessage: appendRealtimeMessage } = useTicketRealtimeMessages(selectedId, messages);

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

const resubscribeTicketRealtime = () => {
    unsubscribeTicketRealtime();

    const client = getSharedRealtimeClient(page.props.realtime);
    const ticketId = selectedId.value;

    if (!client || !ticketId) {
        return;
    }

    subscribedTicketChannel = ticketChannel(page.props.tenantId, ticketId);
    ticketRealtimeHandler = handleRealtimeEvent;
    client.subscribe(subscribedTicketChannel, ticketRealtimeHandler);
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

const mergeQueueUpdates = (updates) => {
    if (!updates?.length) {
        return;
    }
    const map = new Map(queueItems.value.map((item) => [item.id, item]));
    updates.forEach((item) => map.set(item.id, { ...map.get(item.id), ...item }));
    queueItems.value = Array.from(map.values()).sort((a, b) => new Date(b.updated_at) - new Date(a.updated_at));
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

const snoozeOpen = ref(false);

const snoozeTicket = async (minutes) => {
    if (!selectedId.value) {
        return;
    }

    snoozeOpen.value = false;

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

const statusBadgeClass = (name) => {
    const value = (name || '').toLowerCase();
    if (value.includes('open')) return 'bg-emerald-100 text-emerald-800 ring-emerald-600/10';
    if (value.includes('pending')) return 'bg-amber-100 text-amber-800 ring-amber-600/10';
    if (value.includes('closed') || value.includes('resolved')) return 'bg-slate-200 text-slate-700 ring-slate-600/10';

    return 'bg-slate-100 text-slate-700 ring-slate-600/10';
};

const priorityBadgeClass = (name) => {
    const value = (name || '').toLowerCase();
    if (value.includes('urgent') || value.includes('critical')) return 'bg-red-100 text-red-800 ring-red-600/10';
    if (value.includes('high')) return 'bg-orange-100 text-orange-800 ring-orange-600/10';
    if (value.includes('low')) return 'bg-slate-100 text-slate-600 ring-slate-600/10';

    return 'bg-blue-100 text-blue-800 ring-blue-600/10';
};

const priorityDotClass = (name) => {
    const value = (name || '').toLowerCase();
    if (value.includes('urgent') || value.includes('critical')) return 'bg-red-500';
    if (value.includes('high')) return 'bg-orange-500';
    if (value.includes('low')) return 'bg-slate-400';

    return 'bg-blue-500';
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
        <div class="-mb-4 flex h-0 min-h-0 flex-1 basis-0 overflow-hidden bg-slate-100 sm:-mb-6">
            <aside class="flex w-[min(100%,20rem)] shrink-0 flex-col overflow-hidden border-r border-slate-200 bg-white lg:w-80">
                <div class="shrink-0 border-b border-slate-100 bg-slate-50/80 px-3 py-2.5">
                    <div class="flex items-center justify-between gap-2">
                        <div class="flex items-center gap-2">
                            <h2 class="text-sm font-semibold text-slate-900">{{ $t('workspace.queue') }}</h2>
                            <span class="rounded-full bg-slate-200/80 px-2 py-0.5 text-xs font-semibold tabular-nums text-slate-600">{{ queueCount }}</span>
                        </div>
                        <button
                            type="button"
                            class="rounded-lg p-1.5 text-slate-400 transition hover:bg-white hover:text-slate-600"
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
                                :class="!activeViewId ? 'bg-slate-900 text-white' : 'bg-white text-slate-600 ring-1 ring-slate-200 hover:bg-slate-50'"
                                @click="clearFilters"
                            >{{ $t('workspace.all') }}</button>
                            <button
                                v-for="view in ticketViews"
                                :key="view.id"
                                type="button"
                                class="rounded-full px-2.5 py-1 text-xs font-semibold transition"
                                :class="activeViewId === view.id ? 'bg-slate-900 text-white' : 'bg-white text-slate-600 ring-1 ring-slate-200 hover:bg-slate-50'"
                                @click="loadView(view.id)"
                            >
                                {{ view.name }}
                            </button>
                        </div>

                        <form class="space-y-2" @submit.prevent="applyFilters">
                            <div class="relative">
                                <svg class="pointer-events-none absolute left-2.5 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                                </svg>
                                <input
                                    v-model="filterForm.search"
                                    type="text"
                                    :placeholder="$t('workspace.search_tickets_ellipsis')"
                                    class="w-full rounded-lg border border-slate-200 bg-white py-2 pl-9 pr-3 text-sm placeholder:text-slate-400 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20"
                                />
                            </div>

                            <div class="grid grid-cols-2 gap-2">
                                <select v-model="filterForm.status_id" class="rounded-lg border border-slate-200 bg-white px-2 py-1.5 text-xs focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20">
                                    <option value="">{{ $t('workspace.all_statuses') }}</option>
                                    <option v-for="status in statuses" :key="status.id" :value="status.id">{{ status.name }}</option>
                                </select>
                                <select v-model="filterForm.priority_id" class="rounded-lg border border-slate-200 bg-white px-2 py-1.5 text-xs focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20">
                                    <option value="">{{ $t('workspace.all_priorities') }}</option>
                                    <option v-for="priority in priorities" :key="priority.id" :value="priority.id">{{ priority.name }}</option>
                                </select>
                            </div>

                            <select v-model="filterForm.assigned_to" class="w-full rounded-lg border border-slate-200 bg-white px-2 py-1.5 text-xs focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20">
                                <option value="">{{ $t('workspace.all_assignees') }}</option>
                                <option v-for="agent in agents" :key="agent.id" :value="agent.id">{{ agent.name }}</option>
                            </select>

                            <div class="flex items-center justify-between gap-2">
                                <label class="flex cursor-pointer items-center gap-2 text-xs font-medium text-slate-600">
                                    <input v-model="filterForm.watching" type="checkbox" class="rounded border-slate-300 text-blue-600 focus:ring-blue-500/30" />
                                    {{ $t('workspace.watching_only') }}
                                </label>
                                <div class="flex items-center gap-2">
                                    <button v-if="hasFilters" type="button" class="text-xs font-medium text-slate-500 hover:text-slate-700" @click="clearFilters">{{ $t('workspace.clear') }}</button>
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
                        :class="selectedId === item.id ? 'bg-blue-50 ring-1 ring-blue-500/25' : 'hover:bg-slate-50'"
                        @click="selectTicket(item.id)"
                    >
                        <div class="flex items-start gap-2">
                            <span class="mt-1.5 h-2 w-2 shrink-0 rounded-full" :class="priorityDotClass(item.priority?.name)" />
                            <div class="min-w-0 flex-1">
                                <div class="flex items-start justify-between gap-2">
                                    <p class="line-clamp-2 text-sm font-medium leading-snug text-slate-900">{{ item.subject }}</p>
                                    <div class="flex shrink-0 items-center gap-1.5">
                                        <UnreadBadge :count="item.unread_count ?? 0" />
                                        <span class="font-mono text-[10px] text-slate-400">{{ item.number }}</span>
                                    </div>
                                </div>
                                <div class="mt-1.5 flex flex-wrap items-center gap-1">
                                    <span class="rounded-full px-1.5 py-0.5 text-[10px] font-medium ring-1 ring-inset" :class="statusBadgeClass(item.status?.name)">{{ item.status?.name }}</span>
                                    <span class="rounded-full px-1.5 py-0.5 text-[10px] font-medium ring-1 ring-inset" :class="priorityBadgeClass(item.priority?.name)">{{ item.priority?.name }}</span>
                                </div>
                                <div class="mt-1.5 flex items-center justify-between gap-2">
                                    <span class="truncate text-xs text-slate-500">{{ item.assignee?.name || 'Unassigned' }}</span>
                                    <span class="shrink-0 text-[10px] text-slate-400">{{ formatRelative(item.updated_at) }}</span>
                                </div>
                            </div>
                        </div>
                    </li>
                    <li v-if="!queueItems.length" class="px-3 py-10 text-center">
                        <p class="text-sm font-medium text-slate-700">{{ $t('workspace.queue_is_empty') }}</p>
                        <p class="mt-1 text-xs text-slate-500">{{ $t('workspace.adjust_filters_or_create_a_ticket') }}</p>
                    </li>
                </ul>
            </aside>

            <div class="flex min-h-0 min-w-0 flex-1">
                <section v-if="ticket" class="flex min-h-0 min-w-0 flex-1 basis-0 flex-col overflow-hidden bg-white">
                    <div class="flex shrink-0 flex-wrap items-center gap-2 border-b border-slate-200 px-3 py-2">
                        <span class="shrink-0 font-mono text-xs font-semibold text-blue-600">{{ ticket.number }}</span>
                        <span class="hidden h-3.5 w-px bg-slate-200 sm:block" />
                        <h1 class="min-w-0 flex-1 truncate text-sm font-semibold text-slate-900" :title="ticket.subject">
                            {{ ticket.subject }}
                        </h1>
                        <div class="flex shrink-0 flex-wrap items-center gap-1.5">
                            <select
                                :value="ticket.ticket_status_id"
                                class="rounded-lg border border-slate-200 px-2 py-1 text-xs font-medium focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20"
                                @change="quickUpdate('ticket_status_id', Number($event.target.value))"
                            >
                                <option v-for="status in statuses" :key="status.id" :value="status.id">{{ status.name }}</option>
                            </select>
                            <select
                                :value="ticket.ticket_priority_id"
                                class="rounded-lg border border-slate-200 px-2 py-1 text-xs font-medium focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20"
                                @change="quickUpdate('ticket_priority_id', Number($event.target.value))"
                            >
                                <option v-for="priority in priorities" :key="priority.id" :value="priority.id">{{ priority.name }}</option>
                            </select>
                            <select
                                :value="ticket.assigned_to ?? ''"
                                class="hidden rounded-lg border border-slate-200 px-2 py-1 text-xs font-medium focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20 sm:block"
                                @change="quickUpdate('assigned_to', $event.target.value ? Number($event.target.value) : '')"
                            >
                                <option value="">{{ $t('workspace.unassigned') }}</option>
                                <option v-for="agent in agents" :key="agent.id" :value="agent.id">{{ agent.name }}</option>
                            </select>
                            <div class="relative">
                                <button
                                    type="button"
                                    class="rounded-lg border border-slate-200 px-2.5 py-1 text-xs font-semibold text-slate-700 hover:bg-slate-50"
                                    @click="snoozeOpen = !snoozeOpen"
                                >
                                    {{ ticket.snoozed_until ? 'Snoozed' : 'Snooze' }}
                                </button>
                                <div
                                    v-if="snoozeOpen"
                                    class="absolute right-0 z-20 mt-1 w-40 rounded-lg border border-slate-200 bg-white py-1 shadow-lg"
                                >
                                    <button type="button" class="block w-full px-3 py-1.5 text-left text-xs text-slate-700 hover:bg-slate-50" @click="snoozeTicket(60)">{{ $t('workspace.1_hour') }}</button>
                                    <button type="button" class="block w-full px-3 py-1.5 text-left text-xs text-slate-700 hover:bg-slate-50" @click="snoozeTicket(240)">{{ $t('workspace.4_hours') }}</button>
                                    <button type="button" class="block w-full px-3 py-1.5 text-left text-xs text-slate-700 hover:bg-slate-50" @click="snoozeTicket(1440)">{{ $t('workspace.tomorrow') }}</button>
                                    <button type="button" class="block w-full px-3 py-1.5 text-left text-xs text-slate-700 hover:bg-slate-50" @click="snoozeTicket(10080)">{{ $t('workspace.1_week') }}</button>
                                    <button
                                        v-if="ticket.snoozed_until"
                                        type="button"
                                        class="block w-full border-t border-slate-100 px-3 py-1.5 text-left text-xs text-red-600 hover:bg-slate-50"
                                        @click="unsnoozeTicket"
                                    >{{ $t('workspace.unsnooze') }}</button>
                                </div>
                            </div>
                            <span v-if="snoozeLabel" class="hidden text-[10px] text-amber-700 lg:inline">Until {{ snoozeLabel }}</span>
                            <Link
                                :href="`/tickets/${ticket.id}`"
                                class="rounded-lg border border-slate-200 px-2.5 py-1 text-xs font-semibold text-slate-700 hover:bg-slate-50"
                            >
                                {{ $t('workspace.full_view') }}
                            </Link>
                        </div>
                    </div>

                    <TicketCollisionBanner :viewers="viewers" />

                    <div v-if="ticket.contact" class="flex shrink-0 items-center gap-2 border-b border-slate-100 bg-slate-50/50 px-3 py-1.5">
                        <AppAvatar :name="ticket.contact.name" :email="ticket.contact.email" size="sm" />
                        <div class="min-w-0">
                            <p class="truncate text-xs font-medium text-slate-800">{{ ticket.contact.name || ticket.contact.email }}</p>
                            <p v-if="ticket.contact.email && ticket.contact.name" class="truncate text-[11px] text-slate-500">{{ ticket.contact.email }}</p>
                        </div>
                        <div v-if="ticket.ccs?.length" class="ml-auto hidden items-center gap-1 sm:flex">
                            <span class="text-[10px] font-medium uppercase tracking-wide text-slate-400">{{ $t('workspace.cc') }}</span>
                            <span class="truncate text-xs text-slate-600">{{ ticket.ccs.map((cc) => cc.email).join(', ') }}</span>
                        </div>
                    </div>

                    <div class="relative min-h-0 flex-1 overflow-hidden bg-[#e5ddd5]/30 px-3 py-2">
                        <TicketConversation
                            :messages="messages"
                            :current-user-id="currentUserId"
                            fill
                        />
                    </div>

                    <form class="shrink-0 border-t border-slate-200 bg-white px-2 pb-2 pt-1" @submit.prevent="sendReply" @keydown="onComposerKeydown">
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
                        <p v-if="draftSaving" class="mt-1 px-1 text-[11px] text-slate-400">{{ $t('workspace.saving_draft_ellipsis') }}</p>
                    </form>
                </section>

                <div v-else class="flex min-h-0 flex-1 flex-col items-center justify-center bg-white px-6 text-center">
                    <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-blue-50 text-blue-500">
                        <svg class="h-7 w-7" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" />
                        </svg>
                    </div>
                    <h3 class="mt-3 text-base font-semibold text-slate-900">{{ $t('workspace.select_a_ticket') }}</h3>
                    <p class="mt-1 max-w-xs text-sm text-slate-500">{{ $t('workspace.choose_a_conversation_from_the_queue_to_read_and_reply') }}</p>
                </div>

                <aside v-if="ticket" class="hidden min-h-0 w-72 shrink-0 flex-col overflow-hidden border-l border-slate-200 bg-white xl:flex">
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
                        :csat="csat"
                        :current-user-id="currentUserId"
                        :custom-field-definitions="customFieldDefinitions"
                        :lifecycle="lifecycle"
                        :side-conversations="sideConversations"
                        :time-tracking="timeTracking ?? { total_minutes: 0, entries: [] }"
                        :external-issues="externalIssues"
                    />
                </aside>
            </div>
        </div>
    </AgentLayout>
</template>
