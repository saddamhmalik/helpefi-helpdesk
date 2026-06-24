<script setup>
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import AppEmptyState from './AppEmptyState.vue';
import AppErrorState from './AppErrorState.vue';
import AppSkeleton from './AppSkeleton.vue';
import AppSpinner from './AppSpinner.vue';

const props = defineProps({
    loading: { type: Boolean, default: false },
    error: { type: [String, null], default: null },
    empty: { type: Boolean, default: false },
    emptyTitle: { type: String, default: '' },
    emptyDescription: { type: String, default: '' },
    emptyIcon: {
        type: String,
        default: 'default',
    },
    loadingLabel: { type: String, default: '' },
    errorTitle: { type: String, default: '' },
    showRetry: { type: Boolean, default: true },
    skeleton: { type: Boolean, default: false },
    skeletonLines: { type: Number, default: 4 },
    minHeight: { type: String, default: '' },
    centered: { type: Boolean, default: true },
});

const emit = defineEmits(['retry']);

const { t } = useI18n();

const resolvedLoadingLabel = computed(() => props.loadingLabel || t('components.loading_ellipsis'));

const containerClass = computed(() => {
    const alignment = props.centered ? 'flex items-center justify-center' : '';
    const minHeight = props.minHeight || '';

    return `${alignment} ${minHeight}`.trim();
});
</script>

<template>
    <div :class="containerClass">
        <slot v-if="loading" name="loading">
            <div
                v-if="skeleton"
                class="w-full max-w-md space-y-3"
                aria-busy="true"
                :aria-label="resolvedLoadingLabel"
            >
                <slot name="skeleton">
                    <AppSkeleton variant="rectangular" height="h-8" width="w-2/3" />
                    <AppSkeleton :lines="skeletonLines" />
                </slot>
            </div>
            <div
                v-else
                class="flex flex-col items-center gap-3 px-4 py-10 text-center"
                aria-busy="true"
                :aria-label="resolvedLoadingLabel"
            >
                <AppSpinner size="lg" :label="resolvedLoadingLabel" />
                <p class="text-sm agent-text-subtle">{{ resolvedLoadingLabel }}</p>
            </div>
        </slot>

        <slot v-else-if="error" name="error" :error="error" :retry="() => emit('retry')">
            <AppErrorState
                :title="errorTitle"
                :message="error"
                :show-retry="showRetry"
                @retry="emit('retry')"
            />
        </slot>

        <slot v-else-if="empty" name="empty">
            <AppEmptyState
                :title="emptyTitle"
                :description="emptyDescription"
                :icon="emptyIcon"
            >
                <slot name="emptyAction" />
            </AppEmptyState>
        </slot>

        <slot v-else />
    </div>
</template>
