<script setup>
defineProps({
    label: { type: String, required: true },
    value: { type: [String, Number], required: true },
    hint: { type: String, default: '' },
    tone: { type: String, default: 'slate' },
});

const tones = {
    slate: 'border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100',
    blue: 'border-blue-200 dark:border-blue-900/60 bg-blue-50 dark:bg-blue-950/40 text-blue-900',
    emerald: 'border-emerald-200 dark:border-emerald-900/60 bg-emerald-50 dark:bg-emerald-950/40 text-emerald-900',
    amber: 'border-amber-200 dark:border-amber-900/60 bg-amber-50 dark:bg-amber-950/40 text-amber-900',
    red: 'border-red-200 dark:border-red-900/60 bg-red-50 dark:bg-red-950/40 text-red-900',
};
</script>

<template>
    <div class="rounded-2xl border p-5 shadow-sm" :class="tones[tone] ?? tones.slate">
        <p class="text-xs font-semibold uppercase tracking-wider opacity-70">{{ label }}</p>
        <p class="mt-2 text-3xl font-semibold tabular-nums">{{ value }}</p>
        <p v-if="hint" class="mt-1 text-sm opacity-80">{{ hint }}</p>
    </div>
</template>
