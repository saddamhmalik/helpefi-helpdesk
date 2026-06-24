<script setup>
defineProps({
    href: { type: String, default: '' },
    selected: { type: Boolean, default: false },
    tag: { type: String, default: 'article' },
});
</script>

<template>
    <component
        :is="tag"
        class="block p-4 transition-ui"
        :class="selected ? 'bg-blue-50 ring-1 ring-inset ring-blue-500/20 dark:bg-blue-950/30 dark:ring-blue-400/20' : 'agent-hover-surface'"
    >
        <slot />
    </component>
</template>
