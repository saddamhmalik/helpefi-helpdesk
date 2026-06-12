<script setup>
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    variant: {
        type: String,
        default: 'compact',
    },
    tone: {
        type: String,
        default: 'light',
    },
});

const page = usePage();
const helpCenter = computed(() => page.props.helpCenter);

const inputClass = computed(() => (
    props.tone === 'dark'
        ? 'w-full rounded-xl border border-white/20 bg-white/10 px-3.5 py-2.5 text-sm text-white shadow-sm transition placeholder:text-slate-400 focus:border-blue-400 focus:outline-none focus:ring-2 focus:ring-blue-400/30'
        : 'agent-input w-full rounded-xl px-3.5 py-2.5 text-sm shadow-sm transition'
));
</script>

<template>
    <div
        v-if="helpCenter"
        :class="[
            variant === 'hero' ? 'rounded-2xl border p-5' : '',
            tone === 'dark' ? 'border-white/10 bg-white/5' : variant === 'hero' ? 'border-slate-200/80 dark:border-slate-800/80 bg-slate-50/80 dark:bg-slate-900/80' : '',
        ]"
    >
        <div v-if="variant === 'hero'" class="mb-3 flex flex-wrap items-center justify-between gap-2">
            <div>
                <p class="text-sm font-semibold" :class="tone === 'dark' ? 'text-white' : 'text-slate-900 dark:text-slate-100'">{{ $t('auth.help_center') }}</p>
                <p class="mt-0.5 text-xs" :class="tone === 'dark' ? 'text-slate-400 dark:text-slate-500' : 'text-slate-500 dark:text-slate-400'">
                    <template v-if="helpCenter.articleCount > 0">
                        {{ $t('auth.help_center_article_count', { count: helpCenter.articleCount }) }}
                    </template>
                    <template v-else>
                        {{ $t('auth.help_center_empty') }}
                    </template>
                </p>
            </div>
            <Link
                :href="helpCenter.homeUrl"
                class="text-sm font-medium transition"
                :class="tone === 'dark' ? 'text-blue-400 hover:text-blue-300' : 'text-blue-600 hover:text-blue-700 dark:hover:text-blue-300 dark:text-blue-300'"
            >
                {{ helpCenter.title }} →
            </Link>
        </div>

        <form :action="helpCenter.searchUrl" method="get" class="flex gap-2" :class="variant === 'hero' ? '' : 'max-w-md'">
            <input
                name="q"
                type="search"
                :class="inputClass"
                :placeholder="$t('auth.search_help_articles')"
                autocomplete="off"
            />
            <button
                type="submit"
                class="shrink-0 rounded-xl px-4 py-2.5 text-sm font-medium text-white transition"
                :class="tone === 'dark' ? 'bg-blue-600 hover:bg-blue-500' : 'bg-slate-900 hover:bg-slate-800 dark:bg-slate-100 dark:bg-slate-900 dark:text-slate-900 dark:text-slate-100 dark:hover:bg-white'"
            >
                {{ $t('auth.search') }}
            </button>
        </form>
    </div>
</template>
