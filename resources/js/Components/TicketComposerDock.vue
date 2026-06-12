<script setup>
import { computed, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import TicketReplyComposer from './TicketReplyComposer.vue';
import TicketAiPanel from './TicketAiPanel.vue';
import MacroPicker from './MacroPicker.vue';

const { t } = useI18n();

const body = defineModel('body', { type: String, default: '' });
const attachments = defineModel('attachments', { type: Array, default: () => [] });
const isInternal = defineModel('isInternal', { type: Boolean, default: false });

const props = defineProps({
    placeholder: { type: String, default: undefined },
    processing: { type: Boolean, default: false },
    aiEnabled: { type: Boolean, default: false },
    ticketId: Number,
    aiBasePath: String,
    onSuggestReply: Function,
    editorKey: { type: Number, default: 0 },
});

const emit = defineEmits(['submit']);

const editorLarge = ref(false);

const plainPreview = computed(() => {
    const text = (body.value ?? '').replace(/<[^>]+>/g, '').trim();

    return text.length > 120 ? `${text.slice(0, 120)}…` : text;
});

const canSend = computed(() => plainPreview.value.length > 0 || attachments.value.length > 0);

const internalPlaceholder = computed(() => (
    isInternal.value ? t('components.internal_note_placeholder') : (props.placeholder ?? t('components.write_a_reply'))
));

const expand = () => {
    editorLarge.value = true;
};

const collapse = () => {
    editorLarge.value = false;
};

const toggleExpanded = () => {
    editorLarge.value = !editorLarge.value;
};

const onSubmit = () => {
    emit('submit');
};

const onUserInput = () => {
    if (!editorLarge.value) {
        editorLarge.value = true;
    }
};

watch(
    () => props.editorKey,
    () => {
        editorLarge.value = false;
    },
);

defineExpose({ expand, collapse });
</script>

<template>
    <div
        class="rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 shadow-[0_-4px_24px_rgba(15,23,42,0.06)] transition-shadow"
        :class="editorLarge ? 'ring-1 ring-slate-200 dark:ring-slate-700/80' : ''"
    >
        <button
            v-if="editorLarge"
            type="button"
            class="flex h-1.5 w-full cursor-row-resize items-center justify-center border-b border-slate-100 dark:border-slate-800 bg-slate-50 dark:bg-slate-950/80 hover:bg-slate-100 dark:bg-slate-900 dark:hover:bg-slate-800"
            :title="$t('components.composer_expanded')"
            @click="collapse"
        >
            <span class="h-1 w-10 rounded-full bg-slate-300" />
        </button>

        <TicketAiPanel
            v-if="aiEnabled && editorLarge"
            compact
            class="border-b border-slate-100 dark:border-slate-800 px-3 pt-2"
            :ticket-id="ticketId"
            :base-path="aiBasePath"
            :ai-enabled="aiEnabled"
            :on-suggest-reply="onSuggestReply"
        />

        <div class="flex min-w-0 items-center gap-2 border-b border-slate-100 dark:border-slate-800 px-3 py-2">
            <div class="flex shrink-0 rounded-lg bg-slate-100 dark:bg-slate-900 p-0.5">
                <button
                    type="button"
                    class="rounded-md px-3 py-1.5 text-xs font-semibold transition"
                    :class="!isInternal ? 'bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 shadow-sm' : 'text-slate-500 dark:text-slate-400 hover:text-slate-700 dark:hover:text-slate-300 dark:text-slate-300'"
                    @click="isInternal = false"
                >
                    {{ $t('components.reply') }}
                </button>
                <button
                    type="button"
                    class="rounded-md px-3 py-1.5 text-xs font-semibold transition"
                    :class="isInternal ? 'bg-amber-500 text-white shadow-sm' : 'text-slate-500 dark:text-slate-400 hover:text-slate-700 dark:hover:text-slate-300 dark:text-slate-300'"
                    @click="isInternal = true"
                >
                    {{ $t('components.internal_note') }}
                </button>
            </div>

            <div class="min-w-0 flex-1" />

            <div class="flex shrink-0 items-center gap-1.5">
                <TicketAiPanel
                    v-if="aiEnabled && !editorLarge"
                    dock
                    class="hidden sm:flex"
                    :ticket-id="ticketId"
                    :base-path="aiBasePath"
                    :ai-enabled="aiEnabled"
                    :on-suggest-reply="(reply) => { onSuggestReply?.(reply); expand(); }"
                />

                <button
                    type="button"
                    class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg text-slate-500 dark:text-slate-400 transition hover:bg-slate-100 dark:bg-slate-900 dark:hover:bg-slate-800 hover:text-slate-800 dark:text-slate-200"
                    :title="editorLarge ? $t('components.collapse_composer') : $t('components.expand_composer')"
                    @click="toggleExpanded"
                >
                    <svg class="h-4 w-4 transition-transform duration-200" :class="editorLarge ? 'rotate-180' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
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
                    <span class="hidden sm:inline">{{ $t('components.send') }}</span>
                </button>
            </div>
        </div>

        <div class="px-3 pb-3 pt-1">
            <div v-if="editorLarge" class="mb-2 flex items-center gap-2">
                <MacroPicker
                    v-if="ticketId"
                    :ticket-id="ticketId"
                    @insert="(html) => { body = html; }"
                />
            </div>
            <div
                class="overflow-hidden rounded-xl ring-1 transition"
                :class="isInternal ? 'bg-amber-50 dark:bg-amber-950/40/30 ring-amber-200 focus-within:ring-amber-400' : 'bg-white dark:bg-slate-900 ring-slate-200 dark:ring-slate-700 focus-within:ring-blue-400'"
            >
                <TicketReplyComposer
                    :key="editorKey"
                    :expanded="editorLarge"
                    :compact="!editorLarge"
                    v-model:body="body"
                    v-model:attachments="attachments"
                    :placeholder="internalPlaceholder"
                    @user-input="onUserInput"
                />
            </div>

            <div v-if="editorLarge" class="mt-2 flex items-center justify-between gap-3 px-1">
                <p class="text-xs text-slate-400 dark:text-slate-500">
                    <span class="hidden sm:inline">{{ $t('components.formatting_lists_and_links_available') }} </span>
                    {{ $t('components.send_shortcut_hint') }}
                </p>
                <p v-if="attachments.length" class="text-xs text-slate-500 dark:text-slate-400">{{ $t('components.attachments_count', { count: attachments.length }) }}</p>
            </div>
        </div>
    </div>
</template>
