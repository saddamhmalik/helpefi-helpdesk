<script setup>
import { Link } from '@inertiajs/vue3';
import { nextTick, ref, watch } from 'vue';
import { useTicketCopilot } from '../composables/useTicketCopilot.js';

const props = defineProps({
    aiBasePath: { type: String, required: true },
    onInsertReply: { type: Function, default: null },
});

const open = ref(false);
const draft = ref('');
const scrollRef = ref(null);

const {
    messages,
    loading,
    error,
    articles,
    provider,
    sendMessage,
    clearHistory,
    loadHistory,
} = useTicketCopilot(props.aiBasePath);

const quickPrompts = [
    'Summarize this ticket and suggest next steps.',
    'Draft a reply to the customer.',
    'What KB articles might help?',
    'What is the likely root cause?',
];

const toggle = () => {
    open.value = !open.value;

    if (open.value) {
        loadHistory();
    }
};

const submit = async () => {
    const message = draft.value;
    draft.value = '';

    await sendMessage(message);
    await nextTick();
    scrollRef.value?.scrollTo({ top: scrollRef.value.scrollHeight, behavior: 'smooth' });
};

const usePrompt = async (prompt) => {
    draft.value = prompt;
    await submit();
};

const insertReply = (content) => {
    if (props.onInsertReply) {
        props.onInsertReply(content);
    }
};

watch(open, async (isOpen) => {
    if (isOpen) {
        await nextTick();
        scrollRef.value?.scrollTo({ top: scrollRef.value.scrollHeight });
    }
});
</script>

<template>
    <div class="pointer-events-none fixed bottom-4 right-4 z-40 flex flex-col items-end gap-2">
        <div
            v-if="open"
            class="pointer-events-auto flex h-[min(32rem,calc(100vh-6rem))] w-[min(24rem,calc(100vw-2rem))] flex-col overflow-hidden rounded-2xl border border-violet-200 bg-white shadow-2xl shadow-violet-900/10"
        >
            <div class="flex items-center justify-between border-b border-violet-100 bg-gradient-to-r from-violet-600 to-indigo-600 px-4 py-3 text-white">
                <div>
                    <p class="text-sm font-semibold">AI Copilot</p>
                    <p v-if="provider" class="text-[11px] text-violet-100">{{ provider }}</p>
                </div>
                <div class="flex items-center gap-1">
                    <button
                        type="button"
                        class="rounded-md px-2 py-1 text-[11px] font-medium text-violet-100 transition hover:bg-white/10"
                        :disabled="loading || messages.length === 0"
                        @click="clearHistory"
                    >
                        Clear
                    </button>
                    <button
                        type="button"
                        class="rounded-md p-1 text-violet-100 transition hover:bg-white/10"
                        @click="open = false"
                    >
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
            </div>

            <div ref="scrollRef" class="min-h-0 flex-1 space-y-3 overflow-y-auto bg-slate-50/80 px-3 py-3">
                <div v-if="messages.length === 0 && !loading" class="rounded-xl border border-dashed border-violet-200 bg-white px-3 py-4 text-center text-xs text-slate-500">
                    Ask about this ticket, draft replies, or find relevant knowledge base articles.
                </div>

                <div
                    v-for="message in messages"
                    :key="message.id"
                    class="flex"
                    :class="message.role === 'user' ? 'justify-end' : 'justify-start'"
                >
                    <div
                        class="max-w-[90%] rounded-2xl px-3 py-2 text-sm whitespace-pre-wrap"
                        :class="message.role === 'user'
                            ? 'bg-violet-600 text-white'
                            : 'border border-violet-100 bg-white text-slate-800 shadow-sm'"
                    >
                        {{ message.content }}
                        <button
                            v-if="message.role === 'assistant' && onInsertReply"
                            type="button"
                            class="mt-2 block text-[11px] font-semibold text-violet-700 hover:text-violet-900"
                            @click="insertReply(message.content)"
                        >
                            Insert into reply
                        </button>
                    </div>
                </div>

                <div v-if="loading" class="text-xs text-slate-500">Thinking...</div>
                <p v-if="error" class="text-xs text-red-600">{{ error }}</p>

                <div v-if="articles.length" class="rounded-xl border border-violet-100 bg-white p-3">
                    <p class="text-[11px] font-semibold uppercase tracking-wide text-violet-700">Related articles</p>
                    <div class="mt-2 space-y-1">
                        <Link
                            v-for="article in articles"
                            :key="article.id"
                            :href="article.url"
                            target="_blank"
                            class="block text-xs font-medium text-violet-800 hover:underline"
                        >
                            {{ article.title }}
                        </Link>
                    </div>
                </div>
            </div>

            <div class="border-t border-slate-200 bg-white p-3">
                <div class="mb-2 flex flex-wrap gap-1">
                    <button
                        v-for="prompt in quickPrompts"
                        :key="prompt"
                        type="button"
                        class="rounded-full border border-violet-100 bg-violet-50 px-2 py-0.5 text-[10px] font-medium text-violet-700 transition hover:bg-violet-100"
                        :disabled="loading"
                        @click="usePrompt(prompt)"
                    >
                        {{ prompt }}
                    </button>
                </div>
                <form class="flex items-end gap-2" @submit.prevent="submit">
                    <textarea
                        v-model="draft"
                        rows="2"
                        class="min-h-[2.75rem] flex-1 resize-none rounded-xl border border-slate-200 px-3 py-2 text-sm text-slate-900 focus:border-violet-500 focus:outline-none focus:ring-2 focus:ring-violet-500/20"
                        placeholder="Ask the copilot..."
                        :disabled="loading"
                    />
                    <button
                        type="submit"
                        class="rounded-xl bg-violet-600 px-3 py-2 text-sm font-semibold text-white transition hover:bg-violet-700 disabled:opacity-50"
                        :disabled="loading || !draft.trim()"
                    >
                        Send
                    </button>
                </form>
            </div>
        </div>

        <button
            type="button"
            class="pointer-events-auto flex items-center gap-2 rounded-full bg-gradient-to-r from-violet-600 to-indigo-600 px-4 py-3 text-sm font-semibold text-white shadow-lg shadow-violet-600/30 transition hover:from-violet-700 hover:to-indigo-700"
            @click="toggle"
        >
            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904L9 18.75l-.813-2.846a4.5 4.5 0 00-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 003.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 003.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 00-3.09 3.09zM18.259 8.715L18 9.75l-.259-1.035a3.375 3.375 0 00-2.455-2.456L14.25 6l1.036-.259a3.375 3.375 0 002.455-2.456L18 2.25l.259 1.035a3.375 3.375 0 002.456 2.456L21.75 6l-1.035.259a3.375 3.375 0 00-2.456 2.456zM16.898 20.562L16.5 21.75l-.398-1.188a2.25 2.25 0 00-1.423-1.423L13.5 18.75l1.179-.398a2.25 2.25 0 001.423-1.423l.398-1.179.398 1.179a2.25 2.25 0 001.423 1.423l1.179.398-1.179.398a2.25 2.25 0 00-1.423 1.423z" />
            </svg>
            {{ open ? 'Close copilot' : 'AI Copilot' }}
        </button>
    </div>
</template>
