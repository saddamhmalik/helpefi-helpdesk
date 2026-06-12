<script setup>
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import AgentLayout from '../../Layouts/AgentLayout.vue';
import AgentCopilotPanel from '../../Components/AgentCopilotPanel.vue';
import TicketComposerDock from '../../Components/TicketComposerDock.vue';
import TicketConversation from '../../Components/TicketConversation.vue';
import TicketDetailsSidebar from '../../Components/TicketDetailsSidebar.vue';
import AppCollapse from '../../Components/AppCollapse.vue';
import TicketExportMenu from '../../Components/TicketExportMenu.vue';
import TicketCollisionBanner from '../../Components/TicketCollisionBanner.vue';
import { useTicketPresence } from '../../composables/useTicketPresence.js';
import { useTicketRealtimeMessages } from '../../composables/useTicketRealtimeMessages.js';
import { useTicketPolling } from '../../composables/useTicketPolling.js';
import { useI18n } from 'vue-i18n';

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
    currentUserId: Number,
    csat: Object,
    lifecycle: Array,
    customFieldDefinitions: { type: Array, default: () => [] },
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
});

const { t } = useI18n();

const page = usePage();
const aiEnabled = computed(() => page.props.ai?.enabled ?? false);
const editorKey = ref(0);
const mobileDetailsOpen = ref(false);

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

const replyForm = useForm({
    body: '',
    is_internal: false,
    attachments: [],
});

const ticketId = computed(() => props.ticket.id);
const messages = ref([...(props.ticket.messages ?? [])]);
const composing = computed(() => !isReplyEmpty(replyForm.body) && !replyForm.processing);
const { viewers } = useTicketPresence(ticketId, composing);

useTicketRealtimeMessages(ticketId, messages);

const { resetPollCursor } = useTicketPolling(ticketId, messages, {
    viewersRef: viewers,
});

watch(() => props.ticket.messages, (next) => {
    messages.value = [...(next ?? [])];
    resetPollCursor();
});

const reply = () => {
    if (!canSendReply()) {
        return;
    }

    replyForm.post(`/tickets/${props.ticket.id}/reply`, {
        forceFormData: replyForm.attachments.length > 0,
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

const sidebarProps = computed(() => ({
    ticket: props.ticket,
    sla: props.sla,
    statuses: props.statuses,
    priorities: props.priorities,
    agents: props.agents,
    departments: props.departments,
    teams: props.teams,
    mergeCandidates: props.mergeCandidates,
    assetOptions: props.assetOptions,
    csat: props.csat,
    currentUserId: props.currentUserId,
    customFieldDefinitions: props.customFieldDefinitions,
    lifecycle: props.lifecycle ?? [],
    sideConversations: props.sideConversations,
    timeTracking: props.timeTracking,
    externalIssues: props.externalIssues,
    approval: props.approval,
    canDecideApproval: props.canDecideApproval,
    changeRecord: props.changeRecord,
    problemRecord: props.problemRecord,
    incidentCandidates: props.incidentCandidates,
    changeRiskOptions: props.changeRiskOptions,
    majorIncident: props.majorIncident,
    canDeclareMajorIncident: props.canDeclareMajorIncident,
}));
</script>

<template>
    <Head :title="ticket.number" />
    <AgentLayout>
        <div class="flex h-0 min-h-0 flex-1 flex-col">
        <div
            v-if="ticket.merged_into"
            class="mb-3 shrink-0 rounded-lg border border-amber-200 dark:border-amber-900/60 bg-amber-50 dark:bg-amber-950/40 px-3 py-2 text-sm text-amber-900"
        >
            {{ $t('tickets.merged_into') }}
            <Link :href="`/tickets/${ticket.merged_into.id}`" class="font-medium underline">{{ ticket.merged_into.number }}</Link>
        </div>

        <div class="flex h-0 min-h-0 flex-1 basis-0">
            <div class="flex min-h-0 min-w-0 flex-1">
                <section class="flex min-h-0 min-w-0 flex-1 basis-0 flex-col overflow-hidden agent-panel">
                    <div class="flex shrink-0 items-center gap-2 border-b agent-border agent-panel px-3 py-2">
                        <Link href="/tickets" class="shrink-0 text-xs font-medium text-blue-600 hover:text-blue-700 dark:hover:text-blue-300 dark:text-blue-300" :title="$t('tickets.back_to_tickets')">
                            ←
                        </Link>
                        <span class="shrink-0 font-mono text-xs font-semibold text-blue-600">{{ ticket.number }}</span>
                        <span class="hidden h-3.5 w-px bg-slate-200 dark:bg-slate-700 sm:block" />
                        <h1 class="min-w-0 flex-1 truncate text-sm font-semibold agent-text" :title="ticket.subject">
                            {{ ticket.subject }}
                        </h1>
                        <span v-if="ticket.channel" class="hidden shrink-0 text-[10px] text-slate-400 dark:text-slate-500 lg:inline">· {{ ticket.channel.name }}</span>
                        <span v-if="messages.length" class="hidden shrink-0 text-[10px] text-slate-400 dark:text-slate-500 md:inline">· {{ messages.length }} {{ $t('tickets.message_count_suffix') }}</span>
                        <div class="ml-auto flex shrink-0 items-center gap-1.5">
                            <TicketExportMenu
                                :ticket-id="ticket.id"
                                :default-email="ticket.contact?.email ?? ''"
                            />
                            <span class="rounded-full bg-emerald-50 dark:bg-emerald-950/40 px-2 py-0.5 text-[10px] font-semibold text-emerald-700 dark:text-emerald-300 ring-1 ring-inset ring-emerald-600/15">
                                {{ ticket.status?.name }}
                            </span>
                        </div>
                    </div>

                    <TicketCollisionBanner :viewers="viewers" />

                    <div class="relative min-h-0 flex-1 overflow-hidden bg-[#e5ddd5]/30 px-3 py-2 dark:bg-slate-900/40">
                        <TicketConversation
                            :messages="messages"
                            :current-user-id="currentUserId"
                            fill
                        />
                    </div>

                    <form class="shrink-0 border-t agent-border agent-panel px-2 pb-3 pt-1" @submit.prevent="reply" @keydown="onReplyKeydown">
                        <TicketComposerDock
                            v-model:body="replyForm.body"
                            v-model:attachments="replyForm.attachments"
                            v-model:is-internal="replyForm.is_internal"
                            :processing="replyForm.processing"
                            :ai-enabled="aiEnabled"
                            :ticket-id="ticket.id"
                            :ai-base-path="`/tickets/${ticket.id}/ai`"
                            :editor-key="editorKey"
                            :on-suggest-reply="(reply) => { replyForm.body = plainReplyToHtml(reply); replyForm.is_internal = false; }"
                            @submit="reply"
                        />
                    </form>
                </section>

                <aside class="hidden min-h-0 w-72 shrink-0 flex-col overflow-hidden border-l agent-border agent-panel xl:flex">
                    <TicketDetailsSidebar embedded v-bind="sidebarProps" />
                </aside>
            </div>
        </div>

        <div class="shrink-0 border-t agent-border agent-panel xl:hidden">
            <button
                type="button"
                class="flex w-full items-center justify-between px-4 py-2.5 text-sm font-medium text-slate-700 dark:text-slate-300"
                @click="mobileDetailsOpen = !mobileDetailsOpen"
            >
                <span>{{ $t('tickets.ticket_details') }}</span>
                <svg class="h-4 w-4 text-slate-400 dark:text-slate-500 transition-transform duration-300 ease-out" :class="mobileDetailsOpen ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                </svg>
            </button>
            <AppCollapse :open="mobileDetailsOpen">
                <div class="max-h-[40vh] overflow-y-auto border-t border-slate-100 dark:border-slate-800">
                    <TicketDetailsSidebar v-bind="sidebarProps" />
                </div>
            </AppCollapse>
        </div>
        </div>

        <AgentCopilotPanel
            v-if="aiEnabled"
            :ai-base-path="`/tickets/${ticket.id}/ai`"
            :on-insert-reply="(reply) => { replyForm.body = plainReplyToHtml(reply); replyForm.is_internal = false; }"
        />
    </AgentLayout>
</template>
