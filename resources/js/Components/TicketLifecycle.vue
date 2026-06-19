<script setup>
import { computed } from 'vue';
import { useDateTime } from '../composables/useDateTime.js';

const props = defineProps({
    lifecycle: { type: Array, default: () => [] },
    compact: { type: Boolean, default: false },
});

const { formatDateTime } = useDateTime();

const entries = computed(() => [...props.lifecycle].reverse());
</script>

<template>
    <div v-if="compact">
        <ol v-if="entries.length" class="relative space-y-0">
            <li
                v-for="(entry, index) in entries"
                :key="entry.id"
                class="relative flex gap-3 pb-4 last:pb-0"
            >
                <div class="flex flex-col items-center">
                    <span class="relative z-10 mt-1 flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-blue-600 text-white shadow-sm shadow-blue-600/30 ring-4 ring-blue-50 dark:ring-blue-950/40">
                        <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3" />
                        </svg>
                    </span>
                    <span
                        v-if="index < entries.length - 1"
                        class="mt-1 w-px flex-1 bg-gradient-to-b from-blue-200 to-slate-200 dark:from-blue-900/60 dark:to-slate-700"
                    />
                </div>
                <div class="min-w-0 flex-1 rounded-xl border border-slate-100 bg-slate-50/80 px-3 py-2.5 dark:border-slate-800 dark:bg-slate-900/60">
                    <p class="text-xs font-medium leading-snug text-slate-900 dark:text-slate-100">{{ entry.description }}</p>
                    <p class="mt-1 flex flex-wrap items-center gap-x-1.5 text-[11px] text-slate-500 dark:text-slate-400">
                        <span class="font-medium text-slate-600 dark:text-slate-300">{{ entry.actor }}</span>
                        <span v-if="entry.created_at" class="text-slate-300 dark:text-slate-600">·</span>
                        <span v-if="entry.created_at">{{ formatDateTime(entry.created_at) }}</span>
                    </p>
                </div>
            </li>
        </ol>
        <p v-else class="rounded-xl border border-dashed border-slate-200 px-4 py-6 text-center text-xs text-slate-500 dark:border-slate-700 dark:text-slate-400">{{ $t('components.no_activity_yet') }}</p>
    </div>

    <div v-else class="rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 p-6 shadow-sm">
        <h2 class="text-lg font-semibold text-slate-900 dark:text-slate-100">{{ $t('components.lifecycle_title') }}</h2>
        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ $t('components.ticket_activity_excluding_replies') }}</p>

        <ol v-if="entries.length" class="mt-4 space-y-0">
            <li
                v-for="(entry, index) in entries"
                :key="entry.id"
                class="relative flex gap-3 pb-6 last:pb-0"
            >
                <div class="flex flex-col items-center">
                    <span class="mt-1.5 h-2.5 w-2.5 shrink-0 rounded-full bg-blue-500 ring-4 ring-blue-50" />
                    <span v-if="index < entries.length - 1" class="mt-1 w-px flex-1 bg-slate-200" />
                </div>
                <div class="min-w-0 flex-1 pt-0.5">
                    <p class="text-sm font-medium text-slate-900 dark:text-slate-100">{{ entry.description }}</p>
                    <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">
                        {{ entry.actor }}
                        <span v-if="entry.created_at"> · {{ formatDateTime(entry.created_at) }}</span>
                    </p>
                </div>
            </li>
        </ol>

        <p v-else class="mt-4 text-sm text-slate-500 dark:text-slate-400">{{ $t('components.no_lifecycle_events_yet') }}</p>
    </div>
</template>
