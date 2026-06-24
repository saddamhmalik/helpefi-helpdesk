<script setup>
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import AppButton from './AppButton.vue';

const props = defineProps({
    title: { type: String, default: '' },
    message: { type: String, default: '' },
    retryLabel: { type: String, default: '' },
    showRetry: { type: Boolean, default: true },
    size: {
        type: String,
        default: 'default',
        validator: (value) => ['compact', 'default'].includes(value),
    },
});

const emit = defineEmits(['retry']);

const { t } = useI18n();

const resolvedTitle = computed(() => props.title || t('components.error_state_title'));
const resolvedMessage = computed(() => props.message || t('components.error_state_message'));
const resolvedRetryLabel = computed(() => props.retryLabel || t('components.try_again'));

const paddingClass = computed(() => (props.size === 'compact' ? 'px-4 py-6' : 'px-4 py-10'));
</script>

<template>
    <div
        class="text-center"
        :class="paddingClass"
        role="alert"
    >
        <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-2xl bg-red-50 text-red-600 dark:bg-red-950/40 dark:text-red-400">
            <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v4m0 4h.01M5.07 19h13.86c1.54 0 2.5-1.67 1.73-3L13.73 5c-.77-1.33-2.69-1.33-3.46 0L3.34 16c-.77 1.33.19 3 1.73 3z" />
            </svg>
        </div>

        <p class="mt-4 text-sm font-medium text-slate-800 dark:text-slate-200">
            {{ resolvedTitle }}
        </p>
        <p class="mt-1 text-sm agent-text-subtle">
            {{ resolvedMessage }}
        </p>

        <div v-if="showRetry || $slots.action" class="mt-4 flex flex-col items-center gap-2 sm:flex-row sm:justify-center">
            <slot name="action">
                <AppButton
                    v-if="showRetry"
                    variant="secondary"
                    size="sm"
                    @click="emit('retry')"
                >
                    {{ resolvedRetryLabel }}
                </AppButton>
            </slot>
        </div>
    </div>
</template>
