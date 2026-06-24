<script setup>
import { computed } from 'vue';

const props = defineProps({
    variant: {
        type: String,
        default: 'text',
        validator: (value) => ['text', 'circular', 'rectangular'].includes(value),
    },
    lines: { type: Number, default: 1 },
    width: { type: String, default: '' },
    height: { type: String, default: '' },
    animate: { type: Boolean, default: true },
});

const pulseClass = computed(() => (props.animate ? 'animate-pulse' : ''));

const lineWidths = computed(() => {
    const widths = ['w-full', 'w-11/12', 'w-4/5', 'w-3/4', 'w-2/3'];

    return Array.from({ length: Math.max(1, props.lines) }, (_, index) => {
        if (props.width) {
            return props.width;
        }

        if (index === props.lines - 1 && props.lines > 1) {
            return widths[Math.min(index + 1, widths.length - 1)];
        }

        return widths[0];
    });
});
</script>

<template>
    <div aria-hidden="true">
        <template v-if="variant === 'text'">
            <div class="space-y-2">
                <div
                    v-for="(lineWidth, index) in lineWidths"
                    :key="index"
                    class="h-3 rounded-md bg-slate-200 dark:bg-slate-800"
                    :class="[pulseClass, lineWidth]"
                />
            </div>
        </template>

        <div
            v-else-if="variant === 'circular'"
            class="rounded-full bg-slate-200 dark:bg-slate-800"
            :class="[
                pulseClass,
                width || 'h-10 w-10',
                height,
            ]"
        />

        <div
            v-else
            class="rounded-xl bg-slate-200 dark:bg-slate-800"
            :class="[
                pulseClass,
                width || 'w-full',
                height || 'h-24',
            ]"
        />
    </div>
</template>
