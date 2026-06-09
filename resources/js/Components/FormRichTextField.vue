<script setup>
import AppRichTextEditor from './AppRichTextEditor.vue';
import FormField from './FormField.vue';

const model = defineModel({ type: String, default: '' });

defineProps({
    label: { type: String, default: '' },
    hint: { type: String, default: '' },
    error: { type: String, default: '' },
    required: { type: Boolean, default: false },
    placeholder: { type: String, default: 'Write here…' },
});
</script>

<template>
    <FormField :label="label" :hint="hint" :error="error" :required="required">
        <AppRichTextEditor v-model="model" form :placeholder="placeholder" />
    </FormField>
</template>
