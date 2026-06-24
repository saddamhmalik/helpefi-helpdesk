<script setup>
import AppEmptyState from './ui/AppEmptyState.vue';

defineProps({
    empty: { type: Boolean, default: false },
    emptyTitle: { type: String, default: '' },
    emptyDescription: { type: String, default: '' },
    emptyIcon: { type: String, default: 'default' },
    colspan: { type: Number, default: 1 },
});
</script>

<template>
    <div class="overflow-hidden rounded-xl border agent-border agent-panel shadow-sm">
        <div v-if="$slots.mobile" class="divide-y agent-border-subtle md:hidden">
            <slot name="mobile" />
        </div>

        <div class="overflow-x-auto" :class="$slots.mobile ? 'hidden md:block' : ''">
            <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-700">
                <template v-if="$slots.head || $slots.body">
                    <thead v-if="$slots.head" class="agent-panel-muted">
                        <slot name="head" />
                    </thead>
                    <tbody v-if="$slots.body" class="divide-y divide-slate-100 dark:divide-slate-800">
                        <slot name="body" />
                    </tbody>
                </template>
                <slot v-else />
            </table>
        </div>

        <AppEmptyState
            v-if="empty"
            size="compact"
            :title="emptyTitle"
            :description="emptyDescription"
            :icon="emptyIcon"
        >
            <slot name="emptyAction" />
        </AppEmptyState>

        <div v-if="$slots.footer" class="border-t agent-border-subtle px-4 py-3">
            <slot name="footer" />
        </div>
    </div>
</template>
