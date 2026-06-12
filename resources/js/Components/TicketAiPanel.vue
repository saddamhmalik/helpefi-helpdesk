<script setup>
import { Link } from '@inertiajs/vue3';
import { ref } from 'vue';
import { useTicketAi } from '../composables/useTicketAi';

const props = defineProps({
    ticketId: Number,
    basePath: String,
    aiEnabled: Boolean,
    onSuggestReply: Function,
    compact: { type: Boolean, default: false },
    dock: { type: Boolean, default: false },
});

const resultsOpen = ref(false);

const {
    aiLoading,
    aiError,
    aiSummary,
    aiArticles,
    suggestReply,
    summarize,
    kbAssist,
} = useTicketAi(props.basePath);

const handleSuggestReply = async () => {
    const reply = await suggestReply();
    if (reply && props.onSuggestReply) {
        props.onSuggestReply(reply);
    }
};

const hasResults = () => Boolean(aiError || aiSummary || aiArticles.length);

const iconButtonClass = 'flex h-8 w-8 items-center justify-center rounded-md text-violet-700 dark:text-violet-300 transition hover:bg-violet-100 disabled:opacity-50';

const actionClass = 'inline-flex items-center gap-1 rounded-md px-2 py-1 text-[11px] font-medium transition disabled:opacity-50';
</script>

<template>
    <div v-if="aiEnabled">
        <div
            v-if="dock && hasResults() && resultsOpen"
            class="mb-1.5 max-h-24 overflow-y-auto rounded-lg border border-violet-100 dark:border-violet-900/50 bg-violet-50 dark:bg-violet-950/40/80 px-2.5 py-2 text-xs text-slate-700 dark:text-slate-300"
        >
            <p v-if="aiError" class="text-red-600">{{ aiError }}</p>
            <p v-if="aiSummary" class="whitespace-pre-wrap">{{ aiSummary }}</p>
            <div v-if="aiArticles.length" class="mt-1 space-y-1">
                <Link v-for="article in aiArticles" :key="article.id" :href="article.url" target="_blank" class="block font-medium text-violet-800 hover:underline">
                    {{ article.title }}
                </Link>
            </div>
        </div>

        <div v-if="dock" class="flex items-center gap-1.5">
            <slot name="leading" />
            <div class="flex shrink-0 items-center gap-0.5">
                <button type="button" :class="iconButtonClass" :title="$t('components.suggest_reply')" :disabled="!!aiLoading" @click="handleSuggestReply">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z" />
                    </svg>
                </button>
                <button type="button" :class="iconButtonClass" :title="$t('components.summarize')" :disabled="!!aiLoading" @click="summarize">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h7" />
                    </svg>
                </button>
                <button type="button" :class="iconButtonClass" :title="$t('components.kb_articles')" :disabled="!!aiLoading" @click="kbAssist">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />
                    </svg>
                </button>
                <button
                    v-if="hasResults()"
                    type="button"
                    class="flex h-8 w-8 items-center justify-center rounded-md text-violet-600 transition hover:bg-violet-100"
                    :title="resultsOpen ? $t('components.hide_ai_results') : $t('components.show_ai_results')"
                    @click="resultsOpen = !resultsOpen"
                >
                    <svg class="h-4 w-4 transition" :class="resultsOpen ? 'rotate-180' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                    </svg>
                </button>
            </div>
            <div class="min-w-0 flex-1">
                <slot name="composer" />
            </div>
            <slot name="trailing" />
        </div>

        <template v-else>
            <div :class="compact ? 'flex min-w-0 flex-1 flex-col gap-1' : 'rounded-lg border border-violet-200 dark:border-violet-900/60 dark:border-violet-800 bg-violet-50 dark:bg-violet-950/40 p-3'">
                <div :class="compact ? 'flex flex-wrap items-center gap-1' : 'flex flex-wrap items-center gap-2'">
                    <span class="inline-flex items-center gap-1 font-semibold uppercase tracking-wide text-violet-700 dark:text-violet-300" :class="compact ? 'text-[10px]' : 'text-xs'">
                        {{ $t('components.ai') }}
                    </span>
                    <button type="button" :class="[actionClass, compact ? 'bg-violet-100/80 text-violet-800 hover:bg-violet-100' : 'border border-violet-200 dark:border-violet-900/60 dark:border-violet-800 bg-white dark:bg-slate-900 text-violet-800 hover:bg-violet-50 dark:bg-violet-950/40']" :disabled="!!aiLoading" @click="handleSuggestReply">
                        {{ aiLoading === 'suggest-reply' ? $t('components.loading_ellipsis_short') : $t('components.suggest_reply') }}
                    </button>
                    <button type="button" :class="[actionClass, compact ? 'bg-violet-100/80 text-violet-800 hover:bg-violet-100' : 'border border-violet-200 dark:border-violet-900/60 dark:border-violet-800 bg-white dark:bg-slate-900 text-violet-800 hover:bg-violet-50 dark:bg-violet-950/40']" :disabled="!!aiLoading" @click="summarize">
                        {{ aiLoading === 'summarize' ? $t('components.loading_ellipsis_short') : $t('components.summarize') }}
                    </button>
                    <button type="button" :class="[actionClass, compact ? 'bg-violet-100/80 text-violet-800 hover:bg-violet-100' : 'border border-violet-200 dark:border-violet-900/60 dark:border-violet-800 bg-white dark:bg-slate-900 text-violet-800 hover:bg-violet-50 dark:bg-violet-950/40']" :disabled="!!aiLoading" @click="kbAssist">
                        {{ aiLoading === 'kb-assist' ? $t('components.loading_ellipsis_short') : $t('components.kb_articles') }}
                    </button>
                    <button v-if="compact && hasResults()" type="button" class="ml-auto text-[10px] font-medium text-violet-600 hover:text-violet-800" @click="resultsOpen = !resultsOpen">
                        {{ resultsOpen ? $t('components.hide_results') : $t('components.show_results') }}
                    </button>
                </div>

                <template v-if="!compact || resultsOpen">
                    <p v-if="aiError" class="mt-1.5 text-[11px] text-red-600">{{ aiError }}</p>
                    <div v-if="aiSummary" class="mt-1.5 whitespace-pre-wrap text-xs text-slate-700 dark:text-slate-300" :class="compact ? 'max-h-20 overflow-y-auto rounded-md bg-violet-50 dark:bg-violet-950/40/80 px-2 py-1.5' : 'rounded-md border border-violet-100 dark:border-violet-900/50 bg-white dark:bg-slate-900 p-3 text-sm'">
                        {{ aiSummary }}
                    </div>
                    <div v-if="aiArticles.length" class="mt-1.5 space-y-1" :class="compact ? 'max-h-24 overflow-y-auto' : ''">
                        <Link v-for="article in aiArticles" :key="article.id" :href="article.url" target="_blank" class="block text-xs font-medium text-violet-800 hover:underline">
                            {{ article.title }}
                        </Link>
                    </div>
                </template>
            </div>
        </template>
    </div>
</template>
