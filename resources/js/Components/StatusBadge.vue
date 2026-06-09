<script setup>
defineOptions({ inheritAttrs: false });

defineProps({
    label: { type: String, default: '—' },
    color: { type: String, default: '#64748b' },
});
</script>

<template>
    <span
        class="inline-flex rounded-full border px-2.5 py-1 text-xs font-medium"
        :style="{
            backgroundColor: `${color}18`,
            color: color,
            borderColor: `${color}33`,
        }"
    >
        {{ label }}
    </span>
</template>
