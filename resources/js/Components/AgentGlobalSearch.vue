<script setup>
import { Link, router, usePage } from '@inertiajs/vue3';
import { computed, nextTick, onMounted, onUnmounted, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { useAgentNavigation } from '../composables/useAgentNavigation.js';
import { useSettingsNavFilter } from '../composables/useSettingsNavFilter.js';
import { csrfHeaders } from '../support/csrf.js';

const { t } = useI18n();
const page = usePage();
const { settingsNavGroups } = useAgentNavigation();
const settingsQuery = ref('');
const { filteredGroups: settingsGroups } = useSettingsNavFilter(settingsNavGroups, settingsQuery);

const query = ref('');
const open = ref(false);
const loading = ref(false);
const groups = ref([]);
const activeIndex = ref(-1);
const panelRef = ref(null);
const inputRef = ref(null);

const copilotLoading = ref(false);
const copilotAnswer = ref('');
const copilotArticles = ref([]);
const copilotError = ref('');
const copilotSource = ref('');

const aiEnabled = computed(() => {
    const billing = page.props.billing;
    const ai = page.props.ai;

    return (billing?.features?.includes('ai') ?? false) && (ai?.enabled ?? false);
});

let debounceTimer = null;
let abortController = null;

const settingsItems = computed(() => settingsGroups.value.flatMap((group) => group.items.map((item) => ({
    id: item.href,
    title: item.label,
    subtitle: item.description,
    meta: group.label,
    href: item.href,
    kind: 'settings',
}))));

const mergedGroups = computed(() => {
    const result = [];

    if (settingsItems.value.length) {
        result.push({
            type: 'settings',
            label: t('common.settings'),
            items: settingsItems.value.slice(0, 8),
        });
    }

    return [...result, ...groups.value];
});

const flatResults = computed(() => mergedGroups.value.flatMap((group) => group.items.map((item) => ({ ...item, group: group.label, kind: item.kind ?? group.type }))));

const hasResults = computed(() => flatResults.value.length > 0);
const hasQuery = computed(() => query.value.trim().length >= 2);
const showResults = computed(() => open.value && hasQuery.value);
const showCopilotPrompt = computed(() => open.value && hasQuery.value && aiEnabled.value && !copilotAnswer.value);

const resetResults = () => {
    groups.value = [];
    activeIndex.value = -1;
    copilotAnswer.value = '';
    copilotArticles.value = [];
    copilotError.value = '';
    copilotSource.value = '';
};

const fetchResults = async (value) => {
    if (abortController) {
        abortController.abort();
    }

    if (value.trim().length < 2) {
        loading.value = false;
        resetResults();
        return;
    }

    abortController = new AbortController();
    loading.value = true;
    copilotAnswer.value = '';
    copilotError.value = '';

    try {
        const response = await fetch(`/global-search?q=${encodeURIComponent(value.trim())}`, {
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                ...csrfHeaders(),
            },
            signal: abortController.signal,
        });

        if (!response.ok) {
            resetResults();
            return;
        }

        const payload = await response.json();
        groups.value = payload.groups ?? [];
        const count = flatResults.value.length;
        activeIndex.value = count ? 0 : -1;
    } catch (error) {
        if (error.name !== 'AbortError') {
            groups.value = [];
            activeIndex.value = -1;
        }
    } finally {
        loading.value = false;
    }
};

const askCopilot = async () => {
    const message = query.value.trim();

    if (!message || copilotLoading.value || !aiEnabled.value) {
        return;
    }

    copilotLoading.value = true;
    copilotError.value = '';
    copilotAnswer.value = '';
    copilotArticles.value = [];

    try {
        const response = await fetch('/ai/copilot/ask', {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                ...csrfHeaders(),
            },
            body: JSON.stringify({ message }),
        });

        const data = await response.json().catch(() => ({}));

        if (!response.ok) {
            copilotError.value = data.message || t('components.copilot_request_failed');
            return;
        }

        copilotAnswer.value = data.answer ?? '';
        copilotArticles.value = data.articles ?? [];
        copilotSource.value = data.source ?? '';
        activeIndex.value = -1;
    } catch {
        copilotError.value = t('components.copilot_request_failed');
    } finally {
        copilotLoading.value = false;
    }
};

const scheduleSearch = (value) => {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(() => fetchResults(value), 250);
};

watch(query, (value) => {
    settingsQuery.value = value;
    copilotAnswer.value = '';
    copilotError.value = '';

    if (value.trim().length < 2) {
        loading.value = false;
        resetResults();
        return;
    }

    loading.value = true;
    scheduleSearch(value);
});

watch(open, (isOpen) => {
    document.body.style.overflow = isOpen ? 'hidden' : '';
});

const openSearch = async () => {
    open.value = true;
    await nextTick();
    inputRef.value?.focus();
    inputRef.value?.select();
};

const closeSearch = () => {
    open.value = false;
    query.value = '';
    settingsQuery.value = '';
    resetResults();
};

const visitResult = (href) => {
    closeSearch();
    router.visit(href);
};

const onInputKeydown = (event) => {
    if (event.key === 'Escape') {
        event.preventDefault();
        closeSearch();
        return;
    }

    if (event.key === 'Enter' && event.metaKey && aiEnabled.value && hasQuery.value) {
        event.preventDefault();
        askCopilot();
        return;
    }

    if (!hasQuery.value || !flatResults.value.length) {
        if (event.key === 'Enter' && hasQuery.value && aiEnabled.value) {
            event.preventDefault();
            askCopilot();
        }
        return;
    }

    if (event.key === 'ArrowDown') {
        event.preventDefault();
        activeIndex.value = (activeIndex.value + 1) % flatResults.value.length;
    } else if (event.key === 'ArrowUp') {
        event.preventDefault();
        activeIndex.value = activeIndex.value <= 0
            ? flatResults.value.length - 1
            : activeIndex.value - 1;
    } else if (event.key === 'Enter' && activeIndex.value >= 0) {
        event.preventDefault();
        visitResult(flatResults.value[activeIndex.value].href);
    } else if (event.key === 'Enter' && aiEnabled.value) {
        event.preventDefault();
        askCopilot();
    }
};

const onBackdropClick = (event) => {
    if (!panelRef.value?.contains(event.target)) {
        closeSearch();
    }
};

const onGlobalShortcut = (event) => {
    if (!(event.metaKey || event.ctrlKey) || event.key.toLowerCase() !== 'k') {
        return;
    }

    event.preventDefault();

    if (open.value) {
        closeSearch();
    } else {
        openSearch();
    }
};

const resultIndex = (group, item) => {
    let index = 0;

    for (const currentGroup of mergedGroups.value) {
        for (const currentItem of currentGroup.items) {
            if (currentGroup === group && currentItem === item) {
                return index;
            }

            index += 1;
        }
    }

    return -1;
};

onMounted(() => {
    document.addEventListener('keydown', onGlobalShortcut);
});

onUnmounted(() => {
    document.removeEventListener('keydown', onGlobalShortcut);
    document.body.style.overflow = '';
    clearTimeout(debounceTimer);

    if (abortController) {
        abortController.abort();
    }
});

defineExpose({ openSearch });
</script>

<template>
    <button
        type="button"
        class="flex w-full max-w-md items-center gap-2 rounded-full border agent-border agent-panel-muted px-4 py-2 text-left text-sm agent-text-subtle shadow-inner transition hover:border-slate-300 dark:hover:border-slate-600 dark:border-slate-700 hover:bg-white dark:bg-slate-900 dark:hover:bg-slate-800 dark:shadow-slate-900/30 xl:max-w-xl"
        @click="openSearch"
    >
        <svg class="h-4 w-4 shrink-0 text-slate-400 dark:text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
        </svg>
        <span class="truncate text-slate-500 dark:text-slate-400">{{ t('components.global_search_placeholder') }}</span>
        <kbd class="ml-auto hidden shrink-0 rounded-md border agent-border agent-panel px-1.5 py-0.5 text-[10px] font-medium text-slate-400 dark:text-slate-500 sm:inline">⌘K</kbd>
    </button>

    <Teleport to="body">
        <Transition
            enter-active-class="transition duration-200 ease-out"
            enter-from-class="opacity-0"
            enter-to-class="opacity-100"
            leave-active-class="transition duration-150 ease-in"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div
                v-if="open"
                class="fixed inset-0 z-[200] flex items-start justify-center px-4 pt-[12vh] sm:pt-[14vh]"
                @mousedown="onBackdropClick"
            >
                <div
                    class="absolute inset-0 bg-slate-900/25 backdrop-blur-[2px]"
                    aria-hidden="true"
                />

                <Transition
                    appear
                    enter-active-class="transition duration-200 ease-out"
                    enter-from-class="opacity-0 -translate-y-2 scale-[0.98]"
                    enter-to-class="opacity-100 translate-y-0 scale-100"
                    leave-active-class="transition duration-150 ease-in"
                    leave-from-class="opacity-100 translate-y-0 scale-100"
                    leave-to-class="opacity-0 -translate-y-2 scale-[0.98]"
                >
                    <div
                        v-if="open"
                        ref="panelRef"
                        class="relative w-full max-w-2xl"
                        role="dialog"
                        aria-modal="true"
                        :aria-label="t('components.search')"
                        @mousedown.stop
                    >
                        <div class="overflow-hidden rounded-[1.75rem] border agent-border agent-panel shadow-[0_24px_80px_rgba(15,23,42,0.22)] backdrop-blur-2xl dark:shadow-[0_24px_80px_rgba(0,0,0,0.45)]">
                            <div class="flex items-center gap-3 border-b agent-border px-5 py-4 sm:px-6 sm:py-5">
                                <svg class="h-5 w-5 shrink-0 text-slate-400 dark:text-slate-500 sm:h-6 sm:w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                                </svg>
                                <input
                                    ref="inputRef"
                                    v-model="query"
                                    type="search"
                                    :placeholder="t('components.global_search_placeholder')"
                                    class="min-w-0 flex-1 bg-transparent text-lg font-normal agent-text placeholder:text-slate-400 dark:placeholder:text-slate-500 focus:outline-none sm:text-[1.35rem]"
                                    @keydown="onInputKeydown"
                                >
                            </div>

                            <div v-if="showCopilotPrompt" class="border-b border-violet-100 bg-violet-50/60 px-4 py-2.5 dark:border-violet-900/50 dark:bg-violet-950/40">
                                <button
                                    type="button"
                                    class="flex w-full items-center gap-3 rounded-xl px-2 py-2 text-left transition hover:bg-violet-100/80 dark:hover:bg-violet-900/50"
                                    :disabled="copilotLoading"
                                    @click="askCopilot"
                                >
                                    <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-violet-600 text-white">
                                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.813 15.904L9 18.75l-.813-2.846a4.5 4.5 0 00-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 003.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 003.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 00-3.09 3.09z" />
                                        </svg>
                                    </span>
                                    <span class="min-w-0 flex-1">
                                        <span class="block text-sm font-medium text-violet-950 dark:text-violet-100">{{ t('components.ask_copilot') }}</span>
                                        <span class="block truncate text-xs text-violet-700 dark:text-violet-300">“{{ query.trim() }}”</span>
                                    </span>
                                    <span v-if="copilotLoading" class="text-xs text-violet-600 dark:text-violet-300">{{ t('components.thinking') }}</span>
                                    <kbd v-else class="hidden shrink-0 rounded border border-violet-200 dark:border-violet-900/60 bg-white dark:bg-slate-900 px-1.5 py-0.5 text-[10px] text-violet-500 sm:inline">↵</kbd>
                                </button>
                            </div>

                            <div v-if="copilotAnswer || copilotError" class="border-b agent-border agent-panel-muted px-5 py-4">
                                <div class="flex items-center gap-2 text-[11px] font-semibold uppercase tracking-wide text-violet-700 dark:text-violet-300">
                                    <span>{{ t('components.ai_copilot') }}</span>
                                    <span v-if="copilotSource" class="rounded-full bg-violet-100 px-2 py-0.5 text-[10px] normal-case text-violet-600 dark:bg-violet-900/50 dark:text-violet-300">{{ copilotSource }}</span>
                                </div>
                                <p v-if="copilotError" class="mt-2 text-sm text-red-600 dark:text-red-400">{{ copilotError }}</p>
                                <p v-else class="mt-2 whitespace-pre-wrap text-sm leading-relaxed text-slate-800 dark:text-slate-200">{{ copilotAnswer }}</p>
                                <div v-if="copilotArticles.length" class="mt-3 space-y-1">
                                    <p class="text-[11px] font-medium text-slate-500 dark:text-slate-400">{{ t('components.related_articles') }}</p>
                                    <a
                                        v-for="article in copilotArticles"
                                        :key="article.id"
                                        :href="article.url"
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        class="block text-xs font-medium text-violet-700 dark:text-violet-300 hover:underline"
                                    >
                                        {{ article.title }}
                                    </a>
                                </div>
                            </div>

                            <div v-if="showResults" class="max-h-[min(50vh,28rem)] overflow-y-auto">
                                <div v-if="loading" class="px-6 py-8 text-center text-sm agent-text-subtle">
                                    {{ t('components.searching_ellipsis') }}
                                </div>
                                <div v-else-if="!hasResults && !copilotAnswer" class="px-6 py-8 text-center text-sm agent-text-subtle">
                                    {{ t('components.no_results_for_query', { query: query.trim() }) }}
                                </div>
                                <div v-else class="py-2">
                                    <div v-for="group in mergedGroups" :key="group.type">
                                        <p class="px-5 py-2 text-[11px] font-semibold uppercase tracking-wide text-slate-400 dark:text-slate-500">
                                            {{ group.label }}
                                        </p>
                                        <button
                                            v-for="item in group.items"
                                            :key="`${group.type}-${item.id}`"
                                            type="button"
                                            class="flex w-full items-start gap-3 px-4 py-2.5 text-left transition"
                                            :class="resultIndex(group, item) === activeIndex ? 'bg-blue-500/10 dark:bg-blue-500/20' : 'agent-hover-surface'"
                                            @click="visitResult(item.href)"
                                        >
                                            <svg
                                                v-if="group.type === 'settings'"
                                                class="mt-0.5 h-4 w-4 shrink-0 text-slate-400 dark:text-slate-500"
                                                fill="none"
                                                stroke="currentColor"
                                                viewBox="0 0 24 24"
                                            >
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                            </svg>
                                            <div class="min-w-0 flex-1 px-1">
                                                <p class="truncate text-sm font-medium agent-text">{{ item.title }}</p>
                                                <p v-if="item.subtitle" class="truncate text-xs agent-text-subtle">{{ item.subtitle }}</p>
                                            </div>
                                            <span v-if="item.meta" class="shrink-0 px-1 text-xs text-slate-400 dark:text-slate-500">{{ item.meta }}</span>
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <div
                                v-else
                                class="px-6 py-5 text-sm text-slate-400 dark:text-slate-500"
                            >
                                {{ t('components.global_search_hint') }}
                            </div>

                            <div class="flex items-center justify-between border-t agent-border px-5 py-3 text-xs text-slate-400 dark:text-slate-500">
                                <span class="hidden sm:inline">{{ aiEnabled ? t('components.global_search_shortcuts') : t('components.navigate_open_esc_close') }}</span>
                                <span class="sm:hidden">{{ t('components.open_esc_close') }}</span>
                                <Link
                                    v-if="hasQuery"
                                    :href="`/tickets?search=${encodeURIComponent(query.trim())}`"
                                    class="font-medium text-blue-600 hover:text-blue-700 dark:text-blue-300 dark:text-blue-400 dark:hover:text-blue-300"
                                    @click="closeSearch"
                                >
                                    {{ t('components.view_all_tickets') }}
                                </Link>
                            </div>
                        </div>
                    </div>
                </Transition>
            </div>
        </Transition>
    </Teleport>
</template>

<style scoped>
input[type='search']::-webkit-search-cancel-button {
    display: none;
}
</style>
