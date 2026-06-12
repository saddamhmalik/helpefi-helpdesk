<script setup>
defineProps({
    label: { type: String, default: '' },
    hint: { type: String, default: '' },
});
</script>

<template>
    <div>
        <label v-if="label" class="mb-1.5 block text-sm font-medium text-slate-700 dark:text-slate-300">{{ label }}</label>
        <slot />
        <p v-if="hint" class="mt-1 text-xs agent-text-subtle">{{ hint }}</p>
    </div>
</template>
