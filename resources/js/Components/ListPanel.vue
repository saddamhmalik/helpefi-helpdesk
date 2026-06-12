<script setup>
defineProps({
    title: { type: String, default: '' },
    description: { type: String, default: '' },
});
</script>

<template>
    <div class="overflow-hidden rounded-xl border agent-border agent-panel shadow-sm" v-bind="$attrs">
        <div v-if="title || $slots.header" class="border-b agent-border-subtle px-5 py-4">
            <slot name="header">
                <h2 v-if="title" class="text-sm font-semibold agent-text">{{ title }}</h2>
                <p v-if="description" class="mt-0.5 text-sm agent-text-subtle">{{ description }}</p>
            </slot>
        </div>
        <div :class="title || $slots.header ? 'px-5 py-4' : ''">
            <slot />
        </div>
        <div v-if="$slots.footer" class="border-t agent-border-subtle px-5 py-3">
            <slot name="footer" />
        </div>
    </div>
</template>
