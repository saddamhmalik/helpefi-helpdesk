<script setup>
import { computed } from 'vue';

const props = defineProps({
    variant: {
        type: String,
        default: 'default',
        validator: (value) => ['default', 'primary', 'success', 'warning', 'error', 'info'].includes(value),
    },
    size: {
        type: String,
        default: 'md',
        validator: (value) => ['sm', 'md'].includes(value),
    },
    dot: { type: Boolean, default: false },
});

const classes = computed(() => {
    const sizeClass = props.size === 'sm'
        ? 'gap-1 px-1.5 py-0.5 text-[10px]'
        : 'gap-1.5 px-2.5 py-1 text-xs';

    const variants = {
        default: 'bg-slate-100 text-slate-700 ring-slate-600/10 dark:bg-slate-800 dark:text-slate-300',
        primary: 'bg-blue-100 text-blue-800 ring-blue-600/10 dark:bg-blue-950/50 dark:text-blue-300',
        success: 'bg-emerald-100 text-emerald-800 ring-emerald-600/10 dark:bg-emerald-950/50 dark:text-emerald-300',
        warning: 'bg-amber-100 text-amber-800 ring-amber-600/10 dark:bg-amber-950/50 dark:text-amber-300',
        error: 'bg-red-100 text-red-800 ring-red-600/10 dark:bg-red-950/50 dark:text-red-300',
        info: 'bg-sky-100 text-sky-800 ring-sky-600/10 dark:bg-sky-950/50 dark:text-sky-300',
    };

    return `inline-flex items-center font-medium ring-1 ring-inset rounded-full ${sizeClass} ${variants[props.variant] ?? variants.default}`;
});

const dotClass = computed(() => ({
    default: 'bg-slate-400',
    primary: 'bg-blue-500',
    success: 'bg-emerald-500',
    warning: 'bg-amber-500',
    error: 'bg-red-500',
    info: 'bg-sky-500',
}[props.variant] ?? 'bg-slate-400'));
</script>

<template>
    <span :class="classes">
        <span v-if="dot" class="h-1.5 w-1.5 shrink-0 rounded-full" :class="dotClass" aria-hidden="true" />
        <slot />
    </span>
</template>
