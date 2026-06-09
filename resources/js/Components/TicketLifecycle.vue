<script setup>
import { computed } from 'vue';

const props = defineProps({
    lifecycle: { type: Array, default: () => [] },
    compact: { type: Boolean, default: false },
});

const entries = computed(() => [...props.lifecycle].reverse());
</script>

<template>
    <div v-if="compact">
        <ol v-if="entries.length" class="space-y-3">
            <li v-for="entry in entries" :key="entry.id" class="relative pl-4">
                <span class="absolute left-0 top-1.5 h-2 w-2 rounded-full bg-blue-500 ring-2 ring-blue-50" />
                <p class="text-xs font-medium text-slate-900">{{ entry.description }}</p>
                <p class="mt-0.5 text-[11px] text-slate-500">
                    {{ entry.actor }}
                    <span v-if="entry.created_at"> · {{ new Date(entry.created_at).toLocaleString() }}</span>
                </p>
            </li>
        </ol>
        <p v-else class="text-xs text-slate-500">No activity yet.</p>
    </div>

    <div v-else class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
        <h2 class="text-lg font-semibold text-slate-900">Lifecycle</h2>
        <p class="mt-1 text-sm text-slate-500">Ticket activity excluding replies.</p>

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
                    <p class="text-sm font-medium text-slate-900">{{ entry.description }}</p>
                    <p class="mt-0.5 text-xs text-slate-500">
                        {{ entry.actor }}
                        <span v-if="entry.created_at"> · {{ new Date(entry.created_at).toLocaleString() }}</span>
                    </p>
                </div>
            </li>
        </ol>

        <p v-else class="mt-4 text-sm text-slate-500">No lifecycle events yet.</p>
    </div>
</template>
