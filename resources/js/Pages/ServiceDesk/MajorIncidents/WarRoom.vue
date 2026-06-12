<script setup>
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import AgentLayout from '../../../Layouts/AgentLayout.vue';
import ServiceDeskNav from '../../../Components/ServiceDeskNav.vue';
import TicketComposerDock from '../../../Components/TicketComposerDock.vue';
import TicketConversation from '../../../Components/TicketConversation.vue';
import TicketLifecycle from '../../../Components/TicketLifecycle.vue';
import TicketSlaPanel from '../../../Components/TicketSlaPanel.vue';
import { useTicketPolling } from '../../../composables/useTicketPolling.js';
import { useTicketRealtimeMessages } from '../../../composables/useTicketRealtimeMessages.js';
import { useI18n } from 'vue-i18n';

const props = defineProps({
    ticket: Object,
    majorIncident: Object,
    sla: Object,
    statuses: Array,
    priorities: Array,
    agents: Array,
    currentUserId: Number,
    lifecycle: Array,
});

const { t } = useI18n();
const page = usePage();
const aiEnabled = computed(() => page.props.ai?.enabled ?? false);
const editorKey = ref(0);

const ticketId = computed(() => props.ticket.id);
const messages = ref([...(props.ticket.messages ?? [])]);

useTicketRealtimeMessages(ticketId, messages);

const { resetPollCursor } = useTicketPolling(ticketId, messages);

watch(() => props.ticket.messages, (next) => {
    messages.value = [...(next ?? [])];
    resetPollCursor();
});

const isActive = computed(() => props.majorIncident?.status === 'active');
const isResolved = computed(() => props.majorIncident?.status === 'resolved');
const isClosed = computed(() => props.majorIncident?.status === 'closed');
const canReply = computed(() => !isClosed.value);

const warRoomForm = useForm({
    coordinator_user_ids: props.majorIncident?.coordinator_user_ids ?? [],
    war_room_notes: props.majorIncident?.war_room_notes ?? '',
});

const resolveForm = useForm({});
const completeForm = useForm({
    summary: props.majorIncident?.summary ?? '',
    timeline: props.majorIncident?.timeline ?? '',
    lessons_learned: props.majorIncident?.lessons_learned ?? '',
    action_items: props.majorIncident?.action_items ?? '',
});

const replyForm = useForm({
    body: '',
    is_internal: false,
    attachments: [],
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

const isReplyEmpty = (body) => !body || body.replace(/<[^>]+>/g, '').trim() === '';

const canSendReply = () => !isReplyEmpty(replyForm.body) || replyForm.attachments.length > 0;

const reply = () => {
    if (!canSendReply()) {
        return;
    }

    replyForm.post(`/tickets/${props.ticket.id}/reply`, {
        forceFormData: replyForm.attachments.length > 0,
        preserveScroll: true,
        onSuccess: () => {
            replyForm.reset('body', 'attachments');
            editorKey.value += 1;
            resetPollCursor();
        },
    });
};

const onReplyKeydown = (event) => {
    if ((event.metaKey || event.ctrlKey) && event.key === 'Enter') {
        event.preventDefault();
        reply();
    }
};

const toggleCoordinator = (agentId) => {
    if (isResolved.value || isClosed.value) {
        return;
    }

    const ids = new Set((warRoomForm.coordinator_user_ids ?? []).map(Number));
    const id = Number(agentId);

    if (ids.has(id)) {
        ids.delete(id);
    } else {
        ids.add(id);
    }

    warRoomForm.coordinator_user_ids = Array.from(ids);
};

const saveWarRoom = () => {
    warRoomForm.put(`/tickets/${props.ticket.id}/major-incident`, { preserveScroll: true });
};

const resolve = () => {
    resolveForm.post(`/tickets/${props.ticket.id}/major-incident/resolve`, { preserveScroll: true });
};

const completeReview = () => {
    completeForm.post(`/tickets/${props.ticket.id}/major-incident/complete-review`);
};

const statusBadgeClass = computed(() => {
    if (isActive.value) return 'bg-red-100 text-red-800 dark:text-red-200 ring-red-200';
    if (isResolved.value) return 'bg-amber-100 text-amber-900 ring-amber-200';

    return 'bg-slate-100 dark:bg-slate-900 text-slate-700 dark:text-slate-300 ring-slate-200 dark:ring-slate-700';
});

const coordinatorNames = computed(() => {
    const ids = new Set((props.majorIncident?.coordinator_user_ids ?? []).map(Number));

    return (props.agents ?? []).filter((agent) => ids.has(agent.id));
});
</script>

<template>
    <Head :title="t('service_desk.page_title_war_room', { number: ticket.number })" />
    <AgentLayout>
        <div class="mx-auto max-w-7xl space-y-5">
            <div class="flex flex-wrap items-start gap-x-4 gap-y-2">
                <div class="flex min-w-0 flex-1 flex-wrap items-center gap-2">
                    <span class="font-mono text-sm font-semibold text-blue-600">{{ ticket.number }}</span>
                    <span
                        class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold capitalize ring-1 ring-inset"
                        :class="statusBadgeClass"
                    >
                        {{ majorIncident.status }}
                    </span>
                    <h1 class="min-w-0 text-lg font-semibold text-slate-900 dark:text-slate-100">{{ ticket.subject }}</h1>
                </div>
                <Link :href="`/tickets/${ticket.id}`" class="text-sm text-slate-500 dark:text-slate-400 hover:text-slate-800 dark:text-slate-200">
                    {{ $t('service_desk.full_ticket_view') }}
                </Link>
            </div>

            <ServiceDeskNav />

            <div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_17.5rem]">
                <div class="min-w-0 space-y-5">
                    <section class="flex flex-col overflow-hidden rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 shadow-sm">
                        <div class="flex items-center justify-between border-b border-slate-100 dark:border-slate-800 px-4 py-3">
                            <h2 class="text-sm font-semibold text-slate-900 dark:text-slate-100">{{ $t('service_desk.live_conversation') }}</h2>
                            <span v-if="majorIncident.declared_by" class="text-xs text-slate-500 dark:text-slate-400">
                                {{ $t('service_desk.declared_by_label') }} {{ majorIncident.declared_by }}
                            </span>
                        </div>
                        <div class="min-h-[16rem] max-h-[min(32rem,55vh)] flex-1 overflow-y-auto bg-[#e5ddd5]/30 p-3">
                            <TicketConversation :messages="messages" :current-user-id="currentUserId" />
                        </div>
                        <form
                            v-if="canReply"
                            class="shrink-0 border-t border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 px-2 pb-2 pt-1"
                            @submit.prevent="reply"
                            @keydown="onReplyKeydown"
                        >
                            <TicketComposerDock
                                v-model:body="replyForm.body"
                                v-model:attachments="replyForm.attachments"
                                v-model:is-internal="replyForm.is_internal"
                                :processing="replyForm.processing"
                                :ai-enabled="aiEnabled"
                                :ticket-id="ticket.id"
                                :ai-base-path="`/tickets/${ticket.id}/ai`"
                                :editor-key="editorKey"
                                :on-suggest-reply="(text) => { replyForm.body = plainReplyToHtml(text); replyForm.is_internal = false; }"
                                @submit="reply"
                            />
                        </form>
                        <p v-else class="border-t border-slate-100 dark:border-slate-800 px-4 py-3 text-xs text-slate-500 dark:text-slate-400">
                            {{ $t('service_desk.review_complete') }} — replies are closed for this major incident.
                        </p>
                    </section>

                    <section v-if="isResolved" class="overflow-hidden rounded-xl border border-amber-200 dark:border-amber-900/60 bg-white dark:bg-slate-900 shadow-sm">
                        <div class="border-b border-amber-100 bg-amber-50 dark:bg-amber-950/40 px-5 py-4">
                            <h2 class="text-base font-semibold text-slate-900 dark:text-slate-100">{{ $t('service_desk.post-incident_review') }}</h2>
                            <p class="mt-1 text-sm text-slate-600 dark:text-slate-400">{{ $t('service_desk.document_what_happened_before_closing_this_major_incident') }}</p>
                        </div>

                        <form class="space-y-5 p-5" @submit.prevent="completeReview">
                            <div>
                                <label class="mb-1.5 block text-sm font-medium text-slate-700 dark:text-slate-300">{{ $t('service_desk.summary') }} *</label>
                                <textarea
                                    v-model="completeForm.summary"
                                    rows="4"
                                    required
                                    class="w-full rounded-lg border border-slate-300 dark:border-slate-700 px-3 py-2 text-sm shadow-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20"
                                    :placeholder="$t('service_desk.review_summary_placeholder')"
                                />
                            </div>

                            <div class="grid gap-5 md:grid-cols-2">
                                <div>
                                    <label class="mb-1.5 block text-sm font-medium text-slate-700 dark:text-slate-300">{{ $t('service_desk.timeline') }}</label>
                                    <textarea
                                        v-model="completeForm.timeline"
                                        rows="5"
                                        class="w-full rounded-lg border border-slate-300 dark:border-slate-700 px-3 py-2 text-sm shadow-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20"
                                        :placeholder="$t('service_desk.review_timeline_placeholder')"
                                    />
                                </div>
                                <div>
                                    <label class="mb-1.5 block text-sm font-medium text-slate-700 dark:text-slate-300">{{ $t('service_desk.lessons_learned') }}</label>
                                    <textarea
                                        v-model="completeForm.lessons_learned"
                                        rows="5"
                                        class="w-full rounded-lg border border-slate-300 dark:border-slate-700 px-3 py-2 text-sm shadow-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20"
                                        :placeholder="$t('service_desk.review_lessons_placeholder')"
                                    />
                                </div>
                            </div>

                            <div>
                                <label class="mb-1.5 block text-sm font-medium text-slate-700 dark:text-slate-300">{{ $t('service_desk.action_items') }}</label>
                                <textarea
                                    v-model="completeForm.action_items"
                                    rows="4"
                                    class="w-full rounded-lg border border-slate-300 dark:border-slate-700 px-3 py-2 text-sm shadow-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20"
                                    :placeholder="$t('service_desk.review_actions_placeholder')"
                                />
                            </div>

                            <div class="flex justify-end border-t border-slate-100 dark:border-slate-800 pt-4">
                                <button
                                    type="submit"
                                    class="rounded-lg bg-amber-600 px-5 py-2.5 text-sm font-medium text-white hover:bg-amber-700 disabled:opacity-50"
                                    :disabled="completeForm.processing"
                                >
                                    {{ $t('service_desk.complete_review') }}
                                </button>
                            </div>
                        </form>
                    </section>

                    <section v-if="isClosed" class="rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 p-5 shadow-sm">
                        <h2 class="text-base font-semibold text-slate-900 dark:text-slate-100">{{ $t('service_desk.review_complete') }}</h2>
                        <p class="mt-3 text-sm leading-relaxed text-slate-700 dark:text-slate-300">{{ majorIncident.summary }}</p>
                        <dl v-if="majorIncident.timeline || majorIncident.lessons_learned || majorIncident.action_items" class="mt-5 grid gap-4 md:grid-cols-2">
                            <div v-if="majorIncident.timeline">
                                <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">{{ $t('service_desk.timeline') }}</dt>
                                <dd class="mt-1 whitespace-pre-wrap text-sm text-slate-700 dark:text-slate-300">{{ majorIncident.timeline }}</dd>
                            </div>
                            <div v-if="majorIncident.lessons_learned">
                                <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">{{ $t('service_desk.lessons_learned') }}</dt>
                                <dd class="mt-1 whitespace-pre-wrap text-sm text-slate-700 dark:text-slate-300">{{ majorIncident.lessons_learned }}</dd>
                            </div>
                            <div v-if="majorIncident.action_items" class="md:col-span-2">
                                <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">{{ $t('service_desk.action_items') }}</dt>
                                <dd class="mt-1 whitespace-pre-wrap text-sm text-slate-700 dark:text-slate-300">{{ majorIncident.action_items }}</dd>
                            </div>
                        </dl>
                        <p v-if="majorIncident.review_completed_by" class="mt-4 text-xs text-slate-500 dark:text-slate-400">
                            {{ $t('service_desk.completed_by_label') }} {{ majorIncident.review_completed_by }}
                        </p>
                    </section>

                    <section v-if="lifecycle?.length" class="rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 p-4 shadow-sm">
                        <h3 class="mb-3 text-sm font-semibold text-slate-900 dark:text-slate-100">{{ $t('components.activity') }}</h3>
                        <TicketLifecycle compact :lifecycle="lifecycle" />
                    </section>
                </div>

                <aside class="space-y-4 xl:sticky xl:top-20 xl:self-start">
                    <div class="overflow-hidden rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 shadow-sm">
                        <div v-if="!isClosed" class="divide-y divide-slate-100 dark:divide-slate-800">
                            <div class="px-4 py-3">
                                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">{{ $t('service_desk.coordinators') }}</p>
                                <div v-if="isActive" class="mt-2 flex flex-wrap gap-1.5">
                                    <button
                                        v-for="agent in agents"
                                        :key="agent.id"
                                        type="button"
                                        class="rounded-full px-2.5 py-1 text-xs font-medium ring-1 ring-inset transition-ui"
                                        :class="warRoomForm.coordinator_user_ids.includes(agent.id)
                                            ? 'bg-slate-900 text-white ring-slate-900'
                                            : 'bg-white dark:bg-slate-900 text-slate-600 dark:text-slate-400 ring-slate-200 dark:ring-slate-700 hover:bg-slate-50 dark:hover:bg-slate-800'"
                                        @click="toggleCoordinator(agent.id)"
                                    >
                                        {{ agent.name }}
                                    </button>
                                </div>
                                <p v-else-if="coordinatorNames.length" class="mt-2 flex flex-wrap gap-1.5">
                                    <span
                                        v-for="agent in coordinatorNames"
                                        :key="agent.id"
                                        class="rounded-full bg-slate-100 dark:bg-slate-900 px-2.5 py-1 text-xs font-medium text-slate-700 dark:text-slate-300"
                                    >
                                        {{ agent.name }}
                                    </span>
                                </p>
                                <p v-else class="mt-1 text-xs text-slate-500 dark:text-slate-400">—</p>
                                <p v-if="!agents?.length && isActive" class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ $t('service_desk.no_agents_available') }}</p>
                            </div>

                            <div v-if="sla && isActive" class="px-4 py-3">
                                <TicketSlaPanel :sla="sla" compact />
                            </div>

                            <form v-if="isActive" class="px-4 py-3" @submit.prevent="saveWarRoom">
                                <label class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">{{ $t('service_desk.war_room_notes') }}</label>
                                <textarea
                                    v-model="warRoomForm.war_room_notes"
                                    rows="4"
                                    class="mt-2 w-full rounded-lg border border-slate-300 dark:border-slate-700 px-3 py-2 text-sm shadow-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20"
                                    :placeholder="$t('service_desk.status_updates_actions_in_progress_comms_plan_ellipsis')"
                                />
                                <button
                                    type="submit"
                                    class="mt-2 w-full rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 px-3 py-1.5 text-xs font-medium text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800 dark:bg-slate-950 disabled:opacity-50"
                                    :disabled="warRoomForm.processing"
                                >
                                    {{ $t('service_desk.save_war_room_notes') }}
                                </button>
                            </form>

                            <div v-else-if="majorIncident.war_room_notes" class="px-4 py-3">
                                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">{{ $t('service_desk.war_room_notes') }}</p>
                                <p class="mt-2 whitespace-pre-wrap text-sm text-slate-700 dark:text-slate-300">{{ majorIncident.war_room_notes }}</p>
                            </div>
                        </div>

                        <div v-else class="px-4 py-3">
                            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">{{ $t('service_desk.coordinators') }}</p>
                            <p v-if="coordinatorNames.length" class="mt-2 flex flex-wrap gap-1.5">
                                <span
                                    v-for="agent in coordinatorNames"
                                    :key="agent.id"
                                    class="rounded-full bg-slate-100 dark:bg-slate-900 px-2.5 py-1 text-xs font-medium text-slate-700 dark:text-slate-300"
                                >
                                    {{ agent.name }}
                                </span>
                            </p>
                            <p v-else class="mt-1 text-xs text-slate-500 dark:text-slate-400">—</p>
                        </div>

                        <div v-if="isActive" class="border-t border-slate-100 dark:border-slate-800 p-3">
                            <button
                                type="button"
                                class="w-full rounded-lg bg-emerald-600 px-3 py-2 text-xs font-semibold text-white hover:bg-emerald-700 disabled:opacity-50"
                                :disabled="resolveForm.processing"
                                @click="resolve"
                            >
                                {{ $t('service_desk.mark_incident_resolved') }}
                            </button>
                        </div>

                        <div v-if="isResolved" class="border-t border-slate-100 dark:border-slate-800 bg-amber-50 dark:bg-amber-950/40/50 px-4 py-3">
                            <p class="text-xs font-medium text-amber-900">{{ $t('service_desk.review_pending') }}</p>
                            <p class="mt-0.5 text-xs text-amber-700 dark:text-amber-300">{{ $t('service_desk.complete_review_in_main_panel') }}</p>
                        </div>
                    </div>
                </aside>
            </div>
        </div>
    </AgentLayout>
</template>
