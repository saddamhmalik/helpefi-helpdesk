<script setup>
defineProps({
    label: { type: String, required: true },
    value: { type: [String, Number], required: true },
    hint: { type: String, default: '' },
    tone: { type: String, default: 'slate' },
});

const tones = {
    slate: 'border-slate-200 bg-white text-slate-900',
    blue: 'border-blue-200 bg-blue-50 text-blue-900',
    emerald: 'border-emerald-200 bg-emerald-50 text-emerald-900',
    amber: 'border-amber-200 bg-amber-50 text-amber-900',
    red: 'border-red-200 bg-red-50 text-red-900',
};
</script>

<template>
    <div class="rounded-2xl border p-5 shadow-sm" :class="tones[tone] ?? tones.slate">
        <p class="text-xs font-semibold uppercase tracking-wider opacity-70">{{ label }}</p>
        <p class="mt-2 text-3xl font-semibold tabular-nums">{{ value }}</p>
        <p v-if="hint" class="mt-1 text-sm opacity-80">{{ hint }}</p>
    </div>
</template>
