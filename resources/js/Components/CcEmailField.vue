<script setup>
import { computed, nextTick, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';

const props = defineProps({
    modelValue: { type: Array, default: () => [] },
    max: { type: Number, default: 10 },
    error: { type: String, default: '' },
    placeholder: { type: String, default: '' },
    compactWhenEmpty: { type: Boolean, default: true },
});

const emit = defineEmits(['update:modelValue']);

const { t } = useI18n();

const resolvedPlaceholder = computed(() => props.placeholder || t('components.add_email_press_enter'));

const input = ref('');
const inputRef = ref(null);
const adding = ref(false);

const showField = computed(() => !props.compactWhenEmpty || props.modelValue.length > 0 || adding.value);

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
    const next = props.modelValue.filter((_, i) => i !== index);
    emit('update:modelValue', next);

    if (next.length === 0) {
        adding.value = false;
    }
};

const startAdding = async () => {
    adding.value = true;
    await nextTick();
    inputRef.value?.focus();
};

const cancelAdding = () => {
    adding.value = false;
    input.value = '';
};

watch(
    () => props.modelValue.length,
    (length) => {
        if (length > 0) {
            adding.value = true;
        }
    },
);

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

    if (props.compactWhenEmpty && props.modelValue.length === 0 && !input.value.trim()) {
        adding.value = false;
    }
};
</script>

<template>
    <div>
        <button
            v-if="!showField"
            type="button"
            class="inline-flex w-full items-center justify-center gap-1.5 rounded-xl border border-dashed border-slate-300 bg-slate-50/80 px-3 py-2.5 text-xs font-semibold text-slate-600 transition hover:border-slate-400 hover:bg-slate-100 dark:border-slate-700 dark:bg-slate-900/60 dark:text-slate-300 dark:hover:border-slate-600 dark:hover:bg-slate-800/60"
            @click="startAdding"
        >
            <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
            </svg>
            {{ $t('components.add_cc') }}
        </button>

        <template v-else>
            <div class="mb-2 flex items-center justify-between gap-2">
                <p class="text-[10px] font-bold uppercase tracking-[0.14em] text-slate-400 dark:text-slate-500">{{ $t('components.cc') }}</p>
                <div class="flex items-center gap-2">
                    <span class="rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-semibold tabular-nums text-slate-500 dark:bg-slate-800 dark:text-slate-400">
                        {{ modelValue.length }}/{{ max }}
                    </span>
                    <button
                        v-if="compactWhenEmpty && modelValue.length === 0"
                        type="button"
                        class="text-[10px] font-medium text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-200"
                        @click="cancelAdding"
                    >
                        {{ $t('components.cancel') }}
                    </button>
                </div>
            </div>

            <div
                class="flex min-h-[42px] flex-wrap items-center gap-1.5 rounded-lg border border-slate-300 bg-white px-2 py-1.5 focus-within:border-blue-500 focus-within:ring-2 focus-within:ring-blue-500/20 dark:border-slate-700 dark:bg-slate-900"
            >
                <span
                    v-for="(email, index) in modelValue"
                    :key="`${email}-${index}`"
                    class="inline-flex items-center gap-1 rounded-md bg-slate-100 px-2 py-0.5 text-xs text-slate-700 dark:bg-slate-800 dark:text-slate-300"
                >
                    {{ email }}
                    <button type="button" class="text-slate-400 hover:text-slate-600 dark:text-slate-500 dark:hover:text-slate-300" @click="removeEmail(index)">×</button>
                </span>
                <input
                    ref="inputRef"
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
        </template>

        <p v-if="error" class="mt-1 text-xs text-red-600">{{ error }}</p>
    </div>
</template>
