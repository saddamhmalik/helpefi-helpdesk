<script setup>
defineProps({
    title: { type: String, default: '' },
    description: { type: String, default: '' },
});
</script>

<template>
    <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm" v-bind="$attrs">
        <div v-if="title || $slots.header" class="border-b border-slate-100 px-5 py-4">
            <slot name="header">
                <h2 v-if="title" class="text-sm font-semibold text-slate-900">{{ title }}</h2>
                <p v-if="description" class="mt-0.5 text-sm text-slate-500">{{ description }}</p>
            </slot>
        </div>
        <div :class="title || $slots.header ? 'px-5 py-4' : ''">
            <slot />
        </div>
        <div v-if="$slots.footer" class="border-t border-slate-100 px-5 py-3">
            <slot name="footer" />
        </div>
    </div>
</template>
