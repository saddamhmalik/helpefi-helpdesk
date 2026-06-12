<script setup>
import { ref } from 'vue';
import { csrfHeaders } from '../support/csrf.js';

const props = defineProps({
    enabled: { type: Boolean, default: true },
});

const query = ref('');
const loading = ref(false);
const error = ref('');
const answer = ref('');
const articles = ref([]);
const source = ref('');

const samplePrompts = [
    'How does AI deflection work?',
    'What can Agent Copilot do?',
    'Is AI included in the free trial?',
];

const ask = async (text) => {
    const question = (text ?? query.value).trim();

    if (!question || loading.value || !props.enabled) {
        return;
    }

    query.value = question;
    loading.value = true;
    error.value = '';
    answer.value = '';
    articles.value = [];

    try {
        const response = await fetch('/api/marketing/ai-demo', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                ...csrfHeaders(),
            },
            body: JSON.stringify({ query: question }),
        });

        const data = await response.json();

        if (!response.ok) {
            error.value = data.message || data.errors?.query?.[0] || 'Something went wrong. Try again.';
            return;
        }

        answer.value = data.answer;
        articles.value = data.articles ?? [];
        source.value = data.source ?? '';
    } catch {
        error.value = 'Unable to reach the AI demo. Check your connection and try again.';
    } finally {
        loading.value = false;
    }
};
</script>

<template>
    <div class="overflow-hidden rounded-2xl border border-violet-200/80 bg-white shadow-2xl shadow-violet-900/10 ring-1 ring-violet-100">
        <div class="flex items-center justify-between border-b border-violet-100 bg-gradient-to-r from-violet-600 to-indigo-600 px-5 py-4 text-white">
            <div>
                <p class="text-sm font-semibold">Try AI deflection</p>
                <p class="text-[11px] text-violet-100">Live demo — ask about helpefi AI features</p>
            </div>
            <span
                v-if="source"
                class="rounded-full bg-white/15 px-2.5 py-1 text-[10px] font-semibold uppercase tracking-wide text-violet-100 ring-1 ring-white/20"
            >
                {{ source }}
            </span>
        </div>

        <div class="space-y-4 bg-slate-50/80 p-5">
            <div v-if="!answer && !loading && !error" class="rounded-xl border border-dashed border-violet-200 bg-white px-4 py-6 text-center">
                <p class="text-sm font-medium text-slate-700">Ask a support-style question</p>
                <p class="mt-1 text-xs text-slate-500">See how customers get instant answers from your knowledge base.</p>
                <div class="mt-4 flex flex-wrap justify-center gap-2">
                    <button
                        v-for="prompt in samplePrompts"
                        :key="prompt"
                        type="button"
                        class="rounded-full border border-violet-200 bg-violet-50 px-3 py-1.5 text-xs font-medium text-violet-700 transition hover:border-violet-300 hover:bg-violet-100"
                        @click="ask(prompt)"
                    >
                        {{ prompt }}
                    </button>
                </div>
            </div>

            <div v-if="loading" class="flex items-center gap-3 rounded-xl border border-violet-100 bg-white px-4 py-5">
                <span class="flex h-8 w-8 items-center justify-center rounded-full bg-violet-100">
                    <svg class="h-4 w-4 animate-spin text-violet-600" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
                    </svg>
                </span>
                <p class="text-sm text-slate-600">Searching knowledge base and drafting an answer…</p>
            </div>

            <div v-if="error" class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                {{ error }}
            </div>

            <div v-if="answer" class="space-y-3">
                <div class="flex justify-end">
                    <div class="max-w-[85%] rounded-2xl rounded-br-md bg-violet-600 px-4 py-2.5 text-sm text-white">
                        {{ query }}
                    </div>
                </div>
                <div class="flex justify-start">
                    <div class="max-w-[90%] rounded-2xl rounded-bl-md border border-violet-100 bg-white px-4 py-3 text-sm leading-relaxed text-slate-800 shadow-sm whitespace-pre-wrap">
                        {{ answer }}
                    </div>
                </div>
                <div v-if="articles.length" class="rounded-xl border border-slate-200 bg-white p-3">
                    <p class="text-[10px] font-semibold uppercase tracking-wider text-slate-400">Sources</p>
                    <ul class="mt-2 space-y-1.5">
                        <li
                            v-for="article in articles"
                            :key="article.title"
                            class="text-xs text-slate-600"
                        >
                            <span class="font-medium text-violet-700">{{ article.title }}</span>
                            <span v-if="article.excerpt" class="text-slate-500"> — {{ article.excerpt }}</span>
                        </li>
                    </ul>
                </div>
            </div>

            <form class="flex gap-2" @submit.prevent="ask()">
                <input
                    v-model="query"
                    type="text"
                    maxlength="500"
                    :disabled="loading || !enabled"
                    placeholder="e.g. How do AI reply drafts work?"
                    class="min-w-0 flex-1 rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-900 placeholder:text-slate-400 focus:border-violet-400 focus:outline-none focus:ring-2 focus:ring-violet-400/20 disabled:opacity-60"
                />
                <button
                    type="submit"
                    :disabled="loading || !query.trim() || !enabled"
                    class="shrink-0 rounded-xl bg-gradient-to-r from-violet-600 to-indigo-600 px-5 py-3 text-sm font-semibold text-white shadow-md shadow-violet-600/25 transition hover:from-violet-500 hover:to-indigo-500 disabled:cursor-not-allowed disabled:opacity-50"
                >
                    Ask
                </button>
            </form>
        </div>
    </div>
</template>
