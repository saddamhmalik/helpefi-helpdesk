<script setup>
import { computed } from 'vue';
import { avatarColor, avatarLabel } from './ticketMessage.js';

const props = defineProps({
    name: { type: String, default: '' },
    email: { type: String, default: '' },
    size: { type: String, default: 'md' },
});

const label = computed(() => avatarLabel(props.name, props.email));
const color = computed(() => avatarColor(props.name || props.email || '?'));

const sizeClass = computed(() => ({
    sm: 'h-7 w-7 text-[10px]',
    md: 'h-9 w-9 text-sm',
    lg: 'h-11 w-11 text-base',
}[props.size] ?? 'h-9 w-9 text-sm'));
</script>

<template>
    <span
        class="inline-flex shrink-0 items-center justify-center rounded-full font-semibold text-white shadow-sm ring-2 ring-white"
        :class="sizeClass"
        :style="{ backgroundColor: color }"
        :title="name || email"
    >
        {{ label }}
    </span>
</template>
