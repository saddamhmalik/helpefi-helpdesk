<script setup>
defineProps({
    title: { type: String, default: '' },
    description: { type: String, default: '' },
});
</script>

<template>
    <section class="space-y-5 border-t agent-border-subtle pt-8 first:border-t-0 first:pt-0">
        <div v-if="title || description">
            <h2 v-if="title" class="text-sm font-semibold tracking-tight agent-text">{{ title }}</h2>
            <p v-if="description" class="mt-1 text-sm agent-text-subtle">{{ description }}</p>
        </div>
        <div class="space-y-5">
            <slot />
        </div>
    </section>
</template>
