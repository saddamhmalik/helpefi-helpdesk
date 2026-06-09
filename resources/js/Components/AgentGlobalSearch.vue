<script setup>
import { Link, router } from '@inertiajs/vue3';
import { computed, nextTick, onMounted, onUnmounted, ref, watch } from 'vue';

const query = ref('');
const open = ref(false);
const loading = ref(false);
const groups = ref([]);
const activeIndex = ref(-1);
const panelRef = ref(null);
const inputRef = ref(null);

let debounceTimer = null;
let abortController = null;

const flatResults = computed(() => groups.value.flatMap((group) => group.items.map((item) => ({ ...item, group: group.label }))));

const hasResults = computed(() => flatResults.value.length > 0);
const hasQuery = computed(() => query.value.trim().length >= 2);
const showResults = computed(() => open.value && hasQuery.value);

const csrf = () => document.querySelector('meta[name="csrf-token"]')?.content;

const resetResults = () => {
    groups.value = [];
    activeIndex.value = -1;
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

    try {
        const response = await fetch(`/global-search?q=${encodeURIComponent(value.trim())}`, {
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': csrf(),
            },
            signal: abortController.signal,
        });

        if (!response.ok) {
            resetResults();
            return;
        }

        const payload = await response.json();
        groups.value = payload.groups ?? [];
        const count = groups.value.reduce((sum, group) => sum + group.items.length, 0);
        activeIndex.value = count ? 0 : -1;
    } catch (error) {
        if (error.name !== 'AbortError') {
            resetResults();
        }
    } finally {
        loading.value = false;
    }
};

const scheduleSearch = (value) => {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(() => fetchResults(value), 250);
};

watch(query, (value) => {
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

    if (!hasQuery.value || !flatResults.value.length) {
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

    for (const currentGroup of groups.value) {
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
        class="flex w-full max-w-md items-center gap-2 rounded-full border border-slate-200/80 bg-slate-100/70 px-4 py-2 text-left text-sm text-slate-500 shadow-inner shadow-white/50 transition hover:border-slate-300 hover:bg-white xl:max-w-xl"
        @click="openSearch"
    >
        <svg class="h-4 w-4 shrink-0 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
        </svg>
        <span class="truncate">Search tickets, customers, organizations…</span>
        <kbd class="ml-auto hidden shrink-0 rounded-md border border-slate-200/80 bg-white/80 px-1.5 py-0.5 text-[10px] font-medium text-slate-400 sm:inline">⌘K</kbd>
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
                        aria-label="Search"
                        @mousedown.stop
                    >
                        <div class="overflow-hidden rounded-[1.75rem] border border-white/60 bg-white/85 shadow-[0_24px_80px_rgba(15,23,42,0.22)] backdrop-blur-2xl">
                            <div class="flex items-center gap-3 border-b border-slate-200/70 px-5 py-4 sm:px-6 sm:py-5">
                                <svg class="h-5 w-5 shrink-0 text-slate-400 sm:h-6 sm:w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                                </svg>
                                <input
                                    ref="inputRef"
                                    v-model="query"
                                    type="search"
                                    placeholder="Search tickets, customers, organizations…"
                                    class="min-w-0 flex-1 bg-transparent text-lg font-normal text-slate-900 placeholder:text-slate-400 focus:outline-none sm:text-[1.35rem]"
                                    @keydown="onInputKeydown"
                                >
                            </div>

                            <div v-if="showResults" class="max-h-[min(50vh,28rem)] overflow-y-auto">
                                <div v-if="loading" class="px-6 py-8 text-center text-sm text-slate-500">
                                    Searching…
                                </div>
                                <div v-else-if="!hasResults" class="px-6 py-8 text-center text-sm text-slate-500">
                                    No results for “{{ query.trim() }}”
                                </div>
                                <div v-else class="py-2">
                                    <div v-for="group in groups" :key="group.type">
                                        <p class="px-5 py-2 text-[11px] font-semibold uppercase tracking-wide text-slate-400">
                                            {{ group.label }}
                                        </p>
                                        <button
                                            v-for="item in group.items"
                                            :key="`${group.type}-${item.id}`"
                                            type="button"
                                            class="flex w-full items-start gap-3 px-4 py-2.5 text-left transition"
                                            :class="resultIndex(group, item) === activeIndex ? 'bg-blue-500/10' : 'hover:bg-slate-100/80'"
                                            @click="visitResult(item.href)"
                                        >
                                            <div class="min-w-0 flex-1 px-1">
                                                <p class="truncate text-sm font-medium text-slate-900">{{ item.title }}</p>
                                                <p v-if="item.subtitle" class="truncate text-xs text-slate-500">{{ item.subtitle }}</p>
                                            </div>
                                            <span v-if="item.meta" class="shrink-0 px-1 text-xs text-slate-400">{{ item.meta }}</span>
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <div
                                v-else
                                class="px-6 py-5 text-sm text-slate-400"
                            >
                                Type at least 2 characters to search across tickets, customers, and organizations.
                            </div>

                            <div class="flex items-center justify-between border-t border-slate-200/70 px-5 py-3 text-xs text-slate-400">
                                <span class="hidden sm:inline">↑↓ navigate · ↵ open · esc close</span>
                                <span class="sm:hidden">↵ open · esc close</span>
                                <Link
                                    v-if="hasQuery"
                                    :href="`/tickets?search=${encodeURIComponent(query.trim())}`"
                                    class="font-medium text-blue-600 hover:text-blue-700"
                                    @click="closeSearch"
                                >
                                    View all tickets
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
