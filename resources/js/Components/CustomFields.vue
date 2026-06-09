<script setup>
import FormField from './FormField.vue';
import FormSection from './FormSection.vue';
import AppRichTextEditor from './AppRichTextEditor.vue';
import { computed } from 'vue';
import { formInputClass, formSelectClass } from '../composables/useFormControls.js';

const props = defineProps({
    definitions: { type: Array, default: () => [] },
    modelValue: { type: Object, default: () => ({}) },
    errors: { type: Object, default: () => ({}) },
});

const emit = defineEmits(['update:modelValue']);

const fields = computed(() => props.definitions ?? []);

const valueFor = (name) => props.modelValue?.[name] ?? '';

const updateField = (name, value) => {
    emit('update:modelValue', {
        ...props.modelValue,
        [name]: value,
    });
};
</script>

<template>
    <FormSection v-if="fields.length" title="Additional information" description="Custom fields configured for this record.">
        <FormField
            v-for="field in fields"
            :key="field.name"
            :label="field.label"
            :required="field.required"
            :error="errors[`custom_fields.${field.name}`]"
        >
            <AppRichTextEditor
                v-if="field.type === 'textarea'"
                :model-value="valueFor(field.name)"
                form
                :placeholder="field.label"
                @update:model-value="updateField(field.name, $event)"
            />

            <select
                v-else-if="field.type === 'select'"
                :value="valueFor(field.name)"
                :class="formSelectClass"
                :required="field.required"
                @change="updateField(field.name, $event.target.value)"
            >
                <option value="">Select…</option>
                <option v-for="option in field.options ?? []" :key="option" :value="option">{{ option }}</option>
            </select>

            <input
                v-else
                :value="valueFor(field.name)"
                :type="field.type === 'number' ? 'number' : field.type === 'email' ? 'email' : 'text'"
                :class="formInputClass"
                :required="field.required"
                @input="updateField(field.name, $event.target.value)"
            />
        </FormField>
        <p v-if="errors.custom_fields" class="text-sm text-red-600">{{ errors.custom_fields }}</p>
    </FormSection>
</template>
