<script setup>
import { computed } from 'vue';

const props = defineProps({
    count: { type: Number, default: 0 },
    size: { type: String, default: 'sm' },
});

const label = computed(() => {
    if (props.count > 99) {
        return '99+';
    }

    return String(props.count);
});
</script>

<template>
    <span
        v-if="count > 0"
        class="inline-flex shrink-0 items-center justify-center rounded-full bg-emerald-500 font-bold text-white shadow-sm ring-2 ring-white"
        :class="size === 'lg' ? 'min-h-5 min-w-5 px-1.5 text-[11px]' : 'min-h-[18px] min-w-[18px] px-1 text-[10px]'"
    >
        {{ label }}
    </span>
</template>
