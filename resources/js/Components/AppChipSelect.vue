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
        <p v-if="label" class="mb-2 text-sm font-medium text-slate-700">{{ label }}</p>
        <div class="flex flex-wrap gap-2">
            <button
                v-for="option in options"
                :key="option.value"
                type="button"
                class="rounded-full border px-3 py-1.5 text-sm font-medium transition-all duration-150"
                :class="modelValue.includes(option.value)
                    ? 'border-blue-600 bg-blue-600 text-white shadow-sm'
                    : 'border-slate-200 bg-white text-slate-600 hover:border-slate-300 hover:bg-slate-50'"
                @click="toggle(option.value)"
            >
                {{ option.label }}
            </button>
        </div>
    </div>
</template>
