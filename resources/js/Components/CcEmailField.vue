<script setup>
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { formInputClass } from '../composables/useFormControls.js';

const props = defineProps({
    modelValue: { type: Array, default: () => [] },
    max: { type: Number, default: 10 },
    error: { type: String, default: '' },
    placeholder: { type: String, default: '' },
});

const emit = defineEmits(['update:modelValue']);

const { t } = useI18n();

const resolvedPlaceholder = computed(() => props.placeholder || t('components.add_email_press_enter'));

const input = ref('');

const emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

const normalize = (value) => value.trim().toLowerCase();

const addEmail = (raw) => {
    const email = normalize(raw.replace(/,$/, ''));

    if (!emailPattern.test(email)) {
        return;
    }

    const current = props.modelValue.map(normalize);

    if (current.includes(email) || current.length >= props.max) {
        input.value = '';
        return;
    }

    emit('update:modelValue', [...props.modelValue, email]);
    input.value = '';
};

const removeEmail = (index) => {
    emit('update:modelValue', props.modelValue.filter((_, i) => i !== index));
};

const onKeydown = (event) => {
    if (event.key === 'Enter' || event.key === ',') {
        event.preventDefault();
        addEmail(input.value);
    }

    if (event.key === 'Backspace' && input.value === '' && props.modelValue.length) {
        removeEmail(props.modelValue.length - 1);
    }
};

const onPaste = (event) => {
    const text = event.clipboardData?.getData('text') ?? '';

    if (!text.includes(',') && !text.includes(';')) {
        return;
    }

    event.preventDefault();
    text.split(/[,;\s]+/).forEach(addEmail);
};

const onBlur = () => {
    if (input.value.trim()) {
        addEmail(input.value);
    }
};
</script>

<template>
    <div>
        <div
            class="flex min-h-[42px] flex-wrap items-center gap-1.5 rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 px-2 py-1.5 focus-within:border-blue-500 focus-within:ring-2 focus-within:ring-blue-500/20"
        >
            <span
                v-for="(email, index) in modelValue"
                :key="`${email}-${index}`"
                class="inline-flex items-center gap-1 rounded-md bg-slate-100 dark:bg-slate-900 px-2 py-0.5 text-xs text-slate-700 dark:text-slate-300"
            >
                {{ email }}
                <button type="button" class="text-slate-400 dark:text-slate-500 hover:text-slate-600 dark:text-slate-400" @click="removeEmail(index)">×</button>
            </span>
            <input
                v-model="input"
                type="text"
                class="min-w-[8rem] flex-1 border-0 bg-transparent px-1 py-1 text-sm outline-none focus:ring-0"
                :placeholder="modelValue.length ? '' : resolvedPlaceholder"
                :disabled="modelValue.length >= max"
                @keydown="onKeydown"
                @paste="onPaste"
                @blur="onBlur"
            />
        </div>
        <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ $t('components.cc_recipients_count', { count: modelValue.length, max }) }}</p>
        <p v-if="error" class="mt-1 text-xs text-red-600">{{ error }}</p>
    </div>
</template>
