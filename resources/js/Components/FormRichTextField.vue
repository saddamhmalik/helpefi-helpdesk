<script setup>
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import AppRichTextEditor from './AppRichTextEditor.vue';
import FormField from './FormField.vue';

const model = defineModel({ type: String, default: '' });

const props = defineProps({
    label: { type: String, default: '' },
    hint: { type: String, default: '' },
    error: { type: String, default: '' },
    required: { type: Boolean, default: false },
    placeholder: { type: String, default: '' },
});

const { t } = useI18n();

const resolvedPlaceholder = computed(() => props.placeholder || t('components.write_here'));
</script>

<template>
    <FormField :label="label" :hint="hint" :error="error" :required="required">
        <AppRichTextEditor v-model="model" form :placeholder="resolvedPlaceholder" />
    </FormField>
</template>
