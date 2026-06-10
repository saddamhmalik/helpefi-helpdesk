<script setup>
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';

defineOptions({ inheritAttrs: false });

const props = defineProps({
    label: { type: String, default: '' },
    color: { type: String, default: '#64748b' },
});

const { t } = useI18n();

const resolvedLabel = computed(() => props.label || t('components.em_dash'));
</script>

<template>
    <span
        class="inline-flex rounded-full border px-2.5 py-1 text-xs font-medium"
        :style="{
            backgroundColor: `${color}18`,
            color: color,
            borderColor: `${color}33`,
        }"
    >
        {{ resolvedLabel }}
    </span>
</template>
