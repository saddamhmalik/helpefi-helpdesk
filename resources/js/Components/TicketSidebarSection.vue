<script setup>
defineProps({
    title: { type: String, required: true },
    open: { type: Boolean, default: false },
    badge: { type: [String, Number], default: '' },
    icon: { type: String, default: 'default' },
    tone: { type: String, default: 'slate' },
});

defineEmits(['toggle']);

const toneClasses = {
    slate: 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300',
    blue: 'bg-blue-100 text-blue-600 dark:bg-blue-950/50 dark:text-blue-300',
    emerald: 'bg-emerald-100 text-emerald-600 dark:bg-emerald-950/50 dark:text-emerald-300',
    amber: 'bg-amber-100 text-amber-700 dark:bg-amber-950/50 dark:text-amber-300',
    violet: 'bg-violet-100 text-violet-600 dark:bg-violet-950/50 dark:text-violet-300',
    rose: 'bg-rose-100 text-rose-600 dark:bg-rose-950/50 dark:text-rose-300',
};
</script>

<template>
    <section class="border-t border-slate-100 dark:border-slate-800/80">
        <button
            type="button"
            class="group flex w-full items-center gap-3 px-4 py-3 text-left transition hover:bg-slate-50/80 dark:hover:bg-slate-800/40"
            @click="$emit('toggle')"
        >
            <span
                class="flex h-8 w-8 shrink-0 items-center justify-center rounded-xl transition group-hover:scale-105"
                :class="toneClasses[tone] ?? toneClasses.slate"
            >
                <slot name="icon">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16" />
                    </svg>
                </slot>
            </span>
            <span class="min-w-0 flex-1">
                <span class="block text-sm font-semibold text-slate-900 dark:text-slate-100">{{ title }}</span>
            </span>
            <span
                v-if="badge !== '' && badge !== null && badge !== undefined"
                class="rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-semibold tabular-nums text-slate-500 dark:bg-slate-800 dark:text-slate-400"
            >
                {{ badge }}
            </span>
            <svg
                class="h-4 w-4 shrink-0 text-slate-400 transition-transform duration-300 ease-out dark:text-slate-500"
                :class="open ? 'rotate-180' : ''"
                fill="none"
                viewBox="0 0 24 24"
                stroke="currentColor"
                stroke-width="2"
            >
                <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
            </svg>
        </button>
        <slot v-if="open" />
    </section>
</template>
