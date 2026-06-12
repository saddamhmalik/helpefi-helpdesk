<script setup>
import { nextTick, onMounted, onUnmounted, ref, watch } from 'vue';
import { csrfHeaders } from '../support/csrf.js';

const props = defineProps({
    ticketId: { type: Number, default: null },
});

const emit = defineEmits(['insert']);

const open = ref(false);
const query = ref('');
const loading = ref(false);
const results = ref([]);
const panelRef = ref(null);

let debounceTimer = null;
let abortController = null;

const fetchResults = async (term) => {
    if (abortController) {
        abortController.abort();
    }

    abortController = new AbortController();
    loading.value = true;

    try {
        const params = term.trim() ? `?q=${encodeURIComponent(term.trim())}` : '';
        const response = await fetch(`/canned-responses/search${params}`, {
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                ...csrfHeaders(),
            },
            signal: abortController.signal,
        });

        if (!response.ok) {
            results.value = [];
            return;
        }

        const data = await response.json();
        results.value = data.results ?? [];
    } catch (error) {
        if (error.name !== 'AbortError') {
            results.value = [];
        }
    } finally {
        loading.value = false;
    }
};

const toggle = async () => {
    open.value = !open.value;

    if (open.value) {
        await fetchResults('');
    }
};

const close = () => {
    open.value = false;
    query.value = '';
};

const applyMacro = async (macro) => {
    const response = await fetch(`/canned-responses/${macro.id}/apply`, {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            ...csrfHeaders(),
        },
        body: JSON.stringify({ ticket_id: props.ticketId }),
    });

    if (!response.ok) {
        return;
    }

    const data = await response.json();
    emit('insert', data.body ?? '');
    close();
};

const onInput = () => {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(() => fetchResults(query.value), 200);
};

const onDocumentClick = (event) => {
    if (!open.value || !panelRef.value) {
        return;
    }

    if (!panelRef.value.contains(event.target)) {
        close();
    }
};

watch(open, (value) => {
    if (value) {
        nextTick(() => document.addEventListener('click', onDocumentClick));
    } else {
        document.removeEventListener('click', onDocumentClick);
    }
});

onMounted(() => {
    if (open.value) {
        document.addEventListener('click', onDocumentClick);
    }
});

onUnmounted(() => {
    document.removeEventListener('click', onDocumentClick);
    clearTimeout(debounceTimer);

    if (abortController) {
        abortController.abort();
    }
});
</script>

<template>
    <div ref="panelRef" class="relative">
        <button
            type="button"
            class="inline-flex h-8 items-center gap-1 rounded-lg border border-slate-200 dark:border-slate-800 px-2.5 text-xs font-medium text-slate-600 dark:text-slate-400 transition hover:bg-slate-50 dark:hover:bg-slate-800"
            @click.stop="toggle"
        >
            <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M8 10h.01M12 10h.01M16 10h.01M9 16h6m2 5H7a2 2 0 01-2-2V7a2 2 0 012-2h5.586a1 1 0 01.707.293l4.414 4.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
            </svg>
            {{ $t('components.macros') }}
        </button>

        <div
            v-if="open"
            class="absolute bottom-full left-0 z-30 mb-2 w-80 overflow-hidden rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 shadow-xl"
            @click.stop
        >
            <div class="border-b border-slate-100 dark:border-slate-800 p-2">
                <input
                    v-model="query"
                    type="text"
                    class="w-full rounded-lg border border-slate-200 dark:border-slate-800 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20"
                    :placeholder="$t('components.search_macros_ellipsis')"
                    @input="onInput"
                />
            </div>
            <ul class="max-h-64 overflow-y-auto py-1">
                <li v-if="loading" class="px-3 py-2 text-xs text-slate-500 dark:text-slate-400">{{ $t('components.loading_ellipsis') }}</li>
                <li v-else-if="!results.length" class="px-3 py-2 text-xs text-slate-500 dark:text-slate-400">{{ $t('components.no_macros_found') }}</li>
                <li v-for="macro in results" :key="macro.id">
                    <button
                        type="button"
                        class="flex w-full flex-col items-start px-3 py-2 text-left hover:bg-slate-50 dark:hover:bg-slate-800"
                        @click="applyMacro(macro)"
                    >
                        <span class="text-sm font-medium text-slate-900 dark:text-slate-100">{{ macro.title }}</span>
                        <span v-if="macro.shortcut" class="text-[11px] text-slate-400 dark:text-slate-500">#{{ macro.shortcut }}</span>
                    </button>
                </li>
            </ul>
        </div>
    </div>
</template>
