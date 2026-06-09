<script setup>
defineProps({
    modelValue: { type: Boolean, default: false },
    label: { type: String, default: '' },
    description: { type: String, default: '' },
    disabled: { type: Boolean, default: false },
});

defineEmits(['update:modelValue']);
</script>

<template>
    <label class="flex items-center justify-between gap-4" :class="disabled ? 'opacity-50' : 'cursor-pointer'">
        <span>
            <span v-if="label" class="block text-sm font-medium text-slate-700">{{ label }}</span>
            <span v-if="description" class="mt-0.5 block text-xs text-slate-500">{{ description }}</span>
        </span>
        <button
            type="button"
            role="switch"
            class="relative inline-flex h-6 w-11 shrink-0 rounded-full transition-colors duration-200 focus:outline-none focus-visible:ring-2 focus-visible:ring-blue-500 focus-visible:ring-offset-2"
            :class="modelValue ? 'bg-blue-600' : 'bg-slate-300'"
            :aria-checked="modelValue"
            :disabled="disabled"
            @click="$emit('update:modelValue', !modelValue)"
        >
            <span
                class="pointer-events-none inline-block h-5 w-5 translate-y-0.5 rounded-full bg-white shadow-sm ring-0 transition-transform duration-200"
                :class="modelValue ? 'translate-x-5' : 'translate-x-0.5'"
            />
        </button>
    </label>
</template>
