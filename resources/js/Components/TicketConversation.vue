<script setup>
import { computed, nextTick, onMounted, ref, watch } from 'vue';
import { useDateTime } from '../composables/useDateTime.js';
import AppAvatar from './AppAvatar.vue';
import TicketAttachmentPreview from './TicketAttachmentPreview.vue';
import TicketMessageContent from './TicketMessageContent.vue';
import { messageAvatar, messagePlainText, messageSide, useTicketMessage } from './ticketMessage.js';

const { formatDateTime } = useDateTime();
const { messageAuthor, formatRelativeTime } = useTicketMessage();

const props = defineProps({
    messages: { type: Array, default: () => [] },
    currentUserId: { type: Number, default: null },
    fill: { type: Boolean, default: false },
    autoScroll: { type: Boolean, default: true },
});

const copiedMessageId = ref(null);
const scrollContainer = ref(null);
const showJumpToBottom = ref(false);
const unreadBelow = ref(0);
const lastSeenCount = ref(0);

const sortedMessages = computed(() =>
    [...props.messages].sort((left, right) => {
        const byTime = new Date(left.created_at).getTime() - new Date(right.created_at).getTime();

        if (byTime !== 0) {
            return byTime;
        }

        return left.id - right.id;
    }),
);

const showMergeDivider = (message, index) => {
    if (!message.merged_from_ticket_id || !message.merged_from_ticket) {
        return false;
    }

    const previous = sortedMessages.value[index - 1];

    return !previous || previous.merged_from_ticket_id !== message.merged_from_ticket_id;
};

const alignment = (message) => {
    if (message.is_internal) {
        return 'center';
    }

    return messageSide(message, props.currentUserId) === 'agent' ? 'right' : 'left';
};

const rowClass = (message) => {
    const side = alignment(message);

    if (side === 'center') {
        return 'justify-center';
    }

    return side === 'right' ? 'justify-end' : 'justify-start';
};

const bubbleClass = (message) => {
    if (message.is_internal) {
        return 'rounded-2xl border border-amber-200 dark:border-amber-900/60/90 bg-amber-50 dark:bg-amber-950/40 text-amber-950 dark:text-amber-100 shadow-sm ring-1 ring-amber-100/80 dark:ring-amber-900/40';
    }

    if (alignment(message) === 'right') {
        return 'rounded-2xl rounded-br-md bg-blue-600 text-white shadow-md shadow-blue-600/15';
    }

    return 'rounded-2xl rounded-bl-md border border-slate-200 dark:border-slate-800/90 bg-white dark:bg-slate-900 text-slate-800 dark:text-slate-200 shadow-sm';
};

const messageHasBody = (message) => (message.body ?? '').replace(/<[^>]+>/g, '').trim() !== '';

const isNearBottom = () => {
    const container = scrollContainer.value;

    if (!container) {
        return true;
    }

    return container.scrollHeight - container.scrollTop - container.clientHeight < 96;
};

const scrollToBottom = (behavior = 'smooth') => {
    nextTick(() => {
        const container = scrollContainer.value;

        if (!container) {
            return;
        }

        container.scrollTo({
            top: container.scrollHeight,
            behavior,
        });
        showJumpToBottom.value = false;
        unreadBelow.value = 0;
        lastSeenCount.value = props.messages.length;
    });
};

const onScroll = () => {
    const nearBottom = isNearBottom();
    showJumpToBottom.value = !nearBottom;

    if (nearBottom) {
        unreadBelow.value = 0;
        lastSeenCount.value = props.messages.length;
    }
};

watch(
    () => props.messages.length,
    (count, previous) => {
        if (!props.autoScroll) {
            return;
        }

        if (isNearBottom() || count <= (previous ?? 0)) {
            scrollToBottom(previous === 0 ? 'auto' : 'smooth');
            return;
        }

        unreadBelow.value = count - lastSeenCount.value;
        showJumpToBottom.value = true;
    },
);

onMounted(() => {
    lastSeenCount.value = props.messages.length;

    if (props.autoScroll) {
        scrollToBottom('auto');
    }
});

const copyMessage = async (message) => {
    const text = messagePlainText(message.body);

    if (!text) {
        return;
    }

    await navigator.clipboard.writeText(text);
    copiedMessageId.value = message.id;

    window.setTimeout(() => {
        if (copiedMessageId.value === message.id) {
            copiedMessageId.value = null;
        }
    }, 1600);
};

const relativeTime = (value) => formatRelativeTime(value);
</script>

<template>
    <div class="relative min-h-0" :class="fill ? 'h-full' : ''">
        <div
            ref="scrollContainer"
            class="conversation-scroll space-y-3 overflow-y-auto overscroll-y-contain scroll-smooth px-1 py-1"
            :class="fill ? 'absolute inset-0' : 'max-h-[32rem]'"
            @scroll="onScroll"
        >
            <template v-for="(message, index) in sortedMessages" :key="message.id">
                <div
                    v-if="showMergeDivider(message, index)"
                    class="flex justify-center py-1"
                >
                    <div class="max-w-md rounded-2xl border border-indigo-200/80 bg-indigo-50/90 px-4 py-2.5 text-center shadow-sm dark:border-indigo-900/50 dark:bg-indigo-950/30">
                        <p class="text-[11px] font-semibold uppercase tracking-wide text-indigo-700 dark:text-indigo-300">
                            {{ $t('components.merged_from_ticket', { number: message.merged_from_ticket.number }) }}
                        </p>
                        <p v-if="message.merged_from_ticket.subject" class="mt-0.5 truncate text-xs text-indigo-900/80 dark:text-indigo-200/80">
                            {{ message.merged_from_ticket.subject }}
                        </p>
                    </div>
                </div>

                <div
                    class="message-item flex gap-2.5"
                    :class="rowClass(message)"
                >
                <AppAvatar
                    v-if="alignment(message) === 'left'"
                    v-bind="messageAvatar(message)"
                    size="md"
                    class="mt-1 shrink-0"
                />

                <div
                    class="group/message flex max-w-[min(100%,34rem)] flex-col"
                    :class="[
                        alignment(message) === 'right' ? 'items-end text-right' : '',
                        alignment(message) === 'left' ? 'items-start text-left' : '',
                        alignment(message) === 'center' ? 'items-center text-center' : '',
                    ]"
                >
                    <div
                        class="flex flex-wrap items-center gap-x-2 gap-y-0.5 text-xs text-slate-500 dark:text-slate-400"
                        :class="[
                            alignment(message) === 'right' ? 'justify-end' : '',
                            alignment(message) === 'left' ? 'justify-start' : '',
                            alignment(message) === 'center' ? 'justify-center' : '',
                        ]"
                    >
                        <span class="font-medium text-slate-700 dark:text-slate-300">{{ messageAuthor(message) }}</span>
                        <time
                            :datetime="message.created_at"
                            :title="formatDateTime(message.created_at)"
                            class="cursor-default"
                        >
                            {{ relativeTime(message.created_at) }}
                        </time>
                        <button
                            v-if="messageHasBody(message)"
                            type="button"
                            class="rounded px-1.5 py-0.5 text-[10px] font-medium uppercase tracking-wide text-slate-400 dark:text-slate-500 opacity-0 transition hover:bg-slate-100 dark:bg-slate-900 dark:hover:bg-slate-800 hover:text-slate-600 dark:text-slate-400 group-hover/message:opacity-100"
                            @click="copyMessage(message)"
                        >
                            {{ copiedMessageId === message.id ? $t('components.copied') : $t('components.copy') }}
                        </button>
                    </div>

                    <div
                        class="mt-1 px-4 py-2.5 text-left transition duration-200"
                        :class="bubbleClass(message)"
                    >
                        <div
                            v-if="messageHasBody(message)"
                            :class="[
                                message.is_internal
                                    ? 'text-amber-950 dark:text-amber-100'
                                    : alignment(message) === 'right'
                                        ? 'prose-invert text-white [&_a]:text-blue-100'
                                        : 'text-slate-800 dark:text-slate-200',
                            ]"
                        >
                            <TicketMessageContent
                                :body="message.body"
                                :inverted="!message.is_internal && alignment(message) === 'right'"
                            />
                        </div>

                        <div
                            v-if="message.attachments?.length"
                            class="flex flex-wrap gap-2"
                            :class="[
                                messageHasBody(message) ? 'mt-3' : 'mt-0',
                                alignment(message) === 'right' ? 'justify-end' : 'justify-start',
                                alignment(message) === 'center' ? 'justify-center' : '',
                            ]"
                        >
                            <TicketAttachmentPreview
                                v-for="attachment in message.attachments"
                                :key="attachment.id"
                                :attachment="attachment"
                            />
                        </div>
                    </div>

                    <div
                        class="mt-1 flex flex-wrap gap-2"
                        :class="[
                            alignment(message) === 'right' ? 'justify-end' : '',
                            alignment(message) === 'left' ? 'justify-start' : '',
                            alignment(message) === 'center' ? 'justify-center' : '',
                        ]"
                    >
                        <span v-if="message.is_internal" class="text-[11px] font-medium uppercase tracking-wide text-amber-700 dark:text-amber-300">{{ $t('components.internal_note') }}</span>
                        <span v-if="message.channel" class="rounded-full bg-slate-100 dark:bg-slate-900 px-2 py-0.5 text-[11px] text-slate-600 dark:text-slate-400">{{ message.channel.name }}</span>
                    </div>
                </div>

                <AppAvatar
                    v-if="alignment(message) === 'right'"
                    v-bind="messageAvatar(message)"
                    size="md"
                    class="mt-1 shrink-0"
                />
                </div>
            </template>

            <p v-if="!sortedMessages.length" class="py-8 text-center text-sm text-slate-500 dark:text-slate-400">{{ $t('components.no_messages_yet') }}</p>
        </div>

        <button
            v-if="showJumpToBottom"
            type="button"
            class="absolute bottom-3 left-1/2 z-10 inline-flex -translate-x-1/2 items-center gap-2 rounded-full border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 px-4 py-2 text-xs font-semibold text-slate-700 dark:text-slate-300 shadow-lg transition hover:bg-slate-50 dark:bg-slate-950 dark:hover:bg-slate-800"
            @click="scrollToBottom('smooth')"
        >
            <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M19 14l-7 7m0 0l-7-7m7 7V3" />
            </svg>
            {{ unreadBelow > 0 ? $t('components.new_messages', unreadBelow, { count: unreadBelow }) : $t('components.jump_to_latest') }}
        </button>
    </div>
</template>

<style scoped>
.conversation-scroll {
    scrollbar-width: thin;
    scrollbar-color: rgba(100, 116, 139, 0.35) transparent;
}

.conversation-scroll::-webkit-scrollbar {
    width: 6px;
}

.conversation-scroll::-webkit-scrollbar-thumb {
    background: rgba(100, 116, 139, 0.35);
    border-radius: 999px;
}

.message-item {
    animation: message-in 0.28s ease both;
}

@keyframes message-in {
    from {
        opacity: 0;
        transform: translateY(8px);
    }

    to {
        opacity: 1;
        transform: translateY(0);
    }
}
</style>
