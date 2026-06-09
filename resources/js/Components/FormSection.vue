<script setup>
defineProps({
    title: { type: String, default: '' },
    description: { type: String, default: '' },
});
</script>

<template>
    <section class="space-y-5 border-t border-slate-100 pt-8 first:border-t-0 first:pt-0">
        <div v-if="title || description">
            <h2 v-if="title" class="text-sm font-semibold tracking-tight text-slate-900">{{ title }}</h2>
            <p v-if="description" class="mt-1 text-sm text-slate-500">{{ description }}</p>
        </div>
        <div class="space-y-5">
            <slot />
        </div>
    </section>
</template>
