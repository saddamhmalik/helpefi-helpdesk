<script setup>
defineProps({
    title: { type: String, default: '' },
    description: { type: String, default: '' },
});
</script>

<template>
    <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
        <div>
            <h1 v-if="title" class="text-2xl font-semibold text-slate-900">{{ title }}</h1>
            <p
                v-if="description || $slots.description"
                class="text-sm text-slate-600"
                :class="title ? 'mt-1' : ''"
            >
                <slot name="description">{{ description }}</slot>
            </p>
        </div>
        <div v-if="$slots.actions" class="flex flex-wrap items-center gap-2">
            <slot name="actions" />
        </div>
    </div>
</template>
