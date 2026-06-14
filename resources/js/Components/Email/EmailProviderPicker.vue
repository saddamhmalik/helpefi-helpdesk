<script setup>
import EmailProviderIcon from './EmailProviderIcon.vue';

defineProps({
    providers: { type: Array, required: true },
    selectedKey: { type: String, default: null },
});

defineEmits(['select']);
</script>

<template>
    <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
        <button
            v-for="provider in providers"
            :key="provider.key"
            type="button"
            class="rounded-xl border p-4 text-left transition"
            :class="selectedKey === provider.key
                ? 'border-blue-500 bg-blue-50/80 ring-2 ring-blue-500/30 dark:border-blue-500 dark:bg-blue-950/40'
                : 'agent-border agent-panel agent-hover-surface'"
            @click="$emit('select', provider.key)"
        >
            <div class="flex items-start gap-3">
                <EmailProviderIcon :provider="provider.key" />
                <span class="min-w-0">
                    <span class="block text-sm font-semibold agent-text">{{ provider.label }}</span>
                    <span class="mt-1 block text-xs leading-relaxed agent-text-muted">{{ provider.description }}</span>
                </span>
            </div>
        </button>
    </div>
</template>
