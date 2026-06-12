<script setup>
const props = defineProps({
    modelValue: { type: Array, default: () => [] },
    options: { type: Array, default: () => [] },
    label: { type: String, default: '' },
});

const emit = defineEmits(['update:modelValue']);

const toggle = (value) => {
    if (props.modelValue.includes(value)) {
        emit('update:modelValue', props.modelValue.filter((item) => item !== value));
        return;
    }

    emit('update:modelValue', [...props.modelValue, value]);
};
</script>

<template>
    <div>
        <p v-if="label" class="mb-2 text-sm font-medium text-slate-700 dark:text-slate-300">{{ label }}</p>
        <div class="flex flex-wrap gap-2">
            <button
                v-for="option in options"
                :key="option.value"
                type="button"
                class="rounded-full border px-3 py-1.5 text-sm font-medium transition-all duration-150"
                :class="modelValue.includes(option.value)
                    ? 'border-blue-600 bg-blue-600 text-white shadow-sm'
                    : 'border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-600 dark:text-slate-400 hover:border-slate-300 dark:hover:border-slate-600 hover:bg-slate-50 dark:hover:bg-slate-800'"
                @click="toggle(option.value)"
            >
                {{ option.label }}
            </button>
        </div>
    </div>
</template>
