<script setup>
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';

const props = defineProps({
    size: {
        type: String,
        default: 'md',
        validator: (value) => ['xs', 'sm', 'md', 'lg'].includes(value),
    },
    label: { type: String, default: '' },
    inline: { type: Boolean, default: false },
});

const { t } = useI18n();

const resolvedLabel = computed(() => props.label || t('components.loading_ellipsis'));

const sizeClass = computed(() => ({
    xs: 'h-3 w-3 border-[1.5px]',
    sm: 'h-4 w-4 border-2',
    md: 'h-5 w-5 border-2',
    lg: 'h-8 w-8 border-[3px]',
}[props.size] ?? 'h-5 w-5 border-2'));
</script>

<template>
    <span
        role="status"
        :class="inline ? 'inline-flex items-center' : 'flex items-center justify-center'"
        :aria-label="resolvedLabel"
    >
        <span
            class="animate-spin rounded-full border-blue-600 border-t-transparent dark:border-blue-400 dark:border-t-transparent"
            :class="sizeClass"
            aria-hidden="true"
        />
        <span class="sr-only">{{ resolvedLabel }}</span>
    </span>
</template>
