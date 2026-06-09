<script setup>
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import AgentLayout from '../../Layouts/AgentLayout.vue';
import TicketComposerDock from '../../Components/TicketComposerDock.vue';
import TicketConversation from '../../Components/TicketConversation.vue';
import TicketDetailsSidebar from '../../Components/TicketDetailsSidebar.vue';
import TicketExportMenu from '../../Components/TicketExportMenu.vue';
import TicketCollisionBanner from '../../Components/TicketCollisionBanner.vue';
import { useTicketPresence } from '../../composables/useTicketPresence.js';
import { useTicketRealtimeMessages } from '../../composables/useTicketRealtimeMessages.js';
import { useTicketPolling } from '../../composables/useTicketPolling.js';

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
});

const page = usePage();
const aiEnabled = computed(() => page.props.ai?.enabled ?? false);
const editorKey = ref(0);

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
</script>

<template>
    <Head :title="ticket.number" />
    <AgentLayout>
        <div
            v-if="ticket.merged_into"
            class="mx-6 mb-0 shrink-0 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-900"
        >
            Merged into
            <Link :href="`/tickets/${ticket.merged_into.id}`" class="font-medium underline">{{ ticket.merged_into.number }}</Link>
        </div>

        <div class="-m-6 flex h-[calc(100dvh-8rem)] min-h-0 flex-col overflow-hidden lg:h-[calc(100dvh-4rem)] xl:flex-row">
            <div class="flex min-h-0 min-w-0 flex-1 flex-col overflow-hidden border-slate-200 bg-white xl:border-r">
                <div class="flex shrink-0 items-center gap-2 border-b border-slate-200 bg-white px-3 py-2">
                    <Link href="/tickets" class="shrink-0 text-xs font-medium text-blue-600 hover:text-blue-700" title="Back to tickets">
                        ←
                    </Link>
                    <span class="shrink-0 font-mono text-xs font-semibold text-blue-600">{{ ticket.number }}</span>
                    <span class="hidden h-3.5 w-px bg-slate-200 sm:block" />
                    <h1 class="min-w-0 truncate text-sm font-semibold text-slate-900" :title="ticket.subject">
                        {{ ticket.subject }}
                    </h1>
                    <span v-if="ticket.channel" class="hidden shrink-0 text-[10px] text-slate-400 lg:inline">· {{ ticket.channel.name }}</span>
                    <span v-if="messages.length" class="hidden shrink-0 text-[10px] text-slate-400 md:inline">· {{ messages.length }} msgs</span>
                    <div class="ml-auto flex shrink-0 items-center gap-1.5">
                        <TicketExportMenu
                            :ticket-id="ticket.id"
                            :default-email="ticket.contact?.email ?? ''"
                        />
                        <span class="rounded-full bg-emerald-50 px-2 py-0.5 text-[10px] font-semibold text-emerald-700 ring-1 ring-inset ring-emerald-600/15">
                            {{ ticket.status?.name }}
                        </span>
                    </div>
                </div>

                <TicketCollisionBanner :viewers="viewers" />

                <div class="relative min-h-0 flex-1 overflow-hidden bg-[#e5ddd5]/30 px-3 py-2">
                    <TicketConversation
                        :messages="messages"
                        :current-user-id="currentUserId"
                        fill
                    />
                </div>

                <form class="shrink-0 px-2 pb-2 pt-1" @submit.prevent="reply" @keydown="onReplyKeydown">
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
            </div>

            <aside class="hidden min-h-0 w-72 shrink-0 flex-col overflow-hidden border-l border-slate-200 bg-white xl:flex">
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
                    :lifecycle="lifecycle ?? []"
                    :side-conversations="sideConversations"
                    :time-tracking="timeTracking"
                    :external-issues="externalIssues"
                />
            </aside>
        </div>

        <div class="mt-4 xl:hidden">
            <TicketDetailsSidebar
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
                :lifecycle="lifecycle ?? []"
                :side-conversations="sideConversations"
                :time-tracking="timeTracking"
                :external-issues="externalIssues"
            />
        </div>
    </AgentLayout>
</template>
