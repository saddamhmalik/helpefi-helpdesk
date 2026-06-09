<script setup>
import { computed, ref, watch } from 'vue';
import TicketReplyComposer from './TicketReplyComposer.vue';
import TicketAiPanel from './TicketAiPanel.vue';
import MacroPicker from './MacroPicker.vue';

const body = defineModel('body', { type: String, default: '' });
const attachments = defineModel('attachments', { type: Array, default: () => [] });
const isInternal = defineModel('isInternal', { type: Boolean, default: false });

const props = defineProps({
    placeholder: { type: String, default: 'Write a reply…' },
    processing: { type: Boolean, default: false },
    aiEnabled: { type: Boolean, default: false },
    ticketId: Number,
    aiBasePath: String,
    onSuggestReply: Function,
    editorKey: { type: Number, default: 0 },
});

const emit = defineEmits(['submit']);

const expanded = ref(true);

const plainPreview = computed(() => {
    const text = (body.value ?? '').replace(/<[^>]+>/g, '').trim();

    return text.length > 120 ? `${text.slice(0, 120)}…` : text;
});

const hasDraft = computed(() => plainPreview.value.length > 0 || attachments.value.length > 0);

const canSend = computed(() => plainPreview.value.length > 0 || attachments.value.length > 0);

const showEditor = computed(() => expanded.value);

const internalPlaceholder = computed(() => (
    isInternal.value ? 'Write an internal note for your team…' : props.placeholder
));

const expand = () => {
    expanded.value = true;
};

const collapse = () => {
    expanded.value = false;
};

const toggleExpanded = () => {
    expanded.value = !expanded.value;
};

const onSubmit = () => {
    emit('submit');
};

watch(hasDraft, (draft) => {
    if (draft) {
        expanded.value = true;
    }
});

watch(
    () => props.editorKey,
    () => {
        expanded.value = true;
    },
);

defineExpose({ expand, collapse });
</script>

<template>
    <div
        class="overflow-hidden rounded-t-xl border border-b-0 border-slate-200 bg-white shadow-[0_-4px_24px_rgba(15,23,42,0.06)] transition-shadow"
        :class="showEditor ? 'ring-1 ring-slate-200/80' : ''"
    >
        <button
            v-if="showEditor"
            type="button"
            class="flex h-1.5 w-full cursor-row-resize items-center justify-center border-b border-slate-100 bg-slate-50/80 hover:bg-slate-100"
            title="Composer expanded"
            @click="collapse"
        >
            <span class="h-1 w-10 rounded-full bg-slate-300" />
        </button>

        <TicketAiPanel
            v-if="aiEnabled && showEditor"
            compact
            class="border-b border-slate-100 px-3 pt-2"
            :ticket-id="ticketId"
            :base-path="aiBasePath"
            :ai-enabled="aiEnabled"
            :on-suggest-reply="onSuggestReply"
        />

        <div class="flex min-w-0 items-center gap-2 border-b border-slate-100 px-3 py-2">
            <div class="flex shrink-0 rounded-lg bg-slate-100 p-0.5">
                <button
                    type="button"
                    class="rounded-md px-3 py-1.5 text-xs font-semibold transition"
                    :class="!isInternal ? 'bg-white text-slate-900 shadow-sm' : 'text-slate-500 hover:text-slate-700'"
                    @click="isInternal = false"
                >
                    Reply
                </button>
                <button
                    type="button"
                    class="rounded-md px-3 py-1.5 text-xs font-semibold transition"
                    :class="isInternal ? 'bg-amber-500 text-white shadow-sm' : 'text-slate-500 hover:text-slate-700'"
                    @click="isInternal = true"
                >
                    Internal note
                </button>
            </div>

            <div v-if="!showEditor" class="min-w-0 flex-1 overflow-hidden">
                <button
                    type="button"
                    class="flex h-9 w-full min-w-0 items-center overflow-hidden rounded-lg bg-slate-50 px-3 text-left text-sm transition hover:bg-slate-100"
                    :class="isInternal ? 'ring-1 ring-amber-200' : 'ring-1 ring-slate-200'"
                    @click="expand"
                >
                    <span v-if="plainPreview" class="block min-w-0 truncate text-slate-800">{{ plainPreview }}</span>
                    <span v-else class="block min-w-0 truncate text-slate-400">{{ internalPlaceholder }}</span>
                </button>
            </div>

            <div v-else class="min-w-0 flex-1" />

            <div class="flex shrink-0 items-center gap-1.5">
                <TicketAiPanel
                    v-if="aiEnabled && !showEditor"
                    dock
                    class="hidden sm:flex"
                    :ticket-id="ticketId"
                    :base-path="aiBasePath"
                    :ai-enabled="aiEnabled"
                    :on-suggest-reply="(reply) => { onSuggestReply?.(reply); expand(); }"
                />

                <button
                    type="button"
                    class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg text-slate-500 transition hover:bg-slate-100 hover:text-slate-800"
                    :title="showEditor ? 'Collapse composer' : 'Expand composer'"
                    @click="toggleExpanded"
                >
                    <svg class="h-4 w-4 transition-transform duration-200" :class="showEditor ? 'rotate-180' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 15l7-7 7 7" />
                    </svg>
                </button>

                <button
                    type="button"
                    class="inline-flex h-9 shrink-0 items-center gap-1.5 rounded-lg px-4 text-sm font-semibold text-white shadow-sm transition disabled:cursor-not-allowed disabled:opacity-50"
                    :class="isInternal ? 'bg-amber-600 hover:bg-amber-700' : 'bg-blue-600 hover:bg-blue-700'"
                    :disabled="processing || !canSend"
                    @click="onSubmit"
                >
                    <svg v-if="processing" class="h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
                    </svg>
                    <svg v-else class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8" />
                    </svg>
                    <span class="hidden sm:inline">Send</span>
                </button>
            </div>
        </div>

        <div v-if="showEditor" class="px-3 pb-3">
            <div class="mb-2 flex items-center gap-2">
                <MacroPicker
                    v-if="ticketId"
                    :ticket-id="ticketId"
                    @insert="(html) => { body = html; }"
                />
            </div>
            <div
                class="overflow-hidden rounded-xl ring-1 transition"
                :class="isInternal ? 'bg-amber-50/30 ring-amber-200 focus-within:ring-amber-400' : 'bg-white ring-slate-200 focus-within:ring-blue-400'"
                @focusin="expand"
            >
                <TicketReplyComposer
                    :key="editorKey"
                    expanded
                    v-model:body="body"
                    v-model:attachments="attachments"
                    :placeholder="internalPlaceholder"
                />
            </div>

            <div class="mt-2 flex items-center justify-between gap-3 px-1">
                <p class="text-xs text-slate-400">
                    <span class="hidden sm:inline">Formatting, lists, and links available · </span>
                    ⌘/Ctrl + Enter to send
                </p>
                <p v-if="attachments.length" class="text-xs text-slate-500">{{ attachments.length }}/5 attachments</p>
            </div>
        </div>

        <div v-else-if="attachments.length" class="border-t border-slate-100 px-3 py-2">
            <div class="flex flex-wrap gap-1.5">
                <span
                    v-for="(file, index) in attachments"
                    :key="`${file.name}-${index}`"
                    class="inline-flex items-center gap-1 rounded-full bg-slate-100 px-2.5 py-1 text-[11px] text-slate-600"
                >
                    {{ file.name }}
                </span>
            </div>
        </div>
    </div>
</template>
