<script setup>
defineProps({
    label: { type: String, default: '' },
    hint: { type: String, default: '' },
    error: { type: String, default: '' },
    required: { type: Boolean, default: false },
});
</script>

<template>
    <div>
        <label v-if="label" class="mb-1.5 block text-sm font-medium text-slate-700">
            {{ label }}
            <span v-if="required" class="text-red-500">*</span>
        </label>
        <slot />
        <p v-if="error" class="mt-1.5 text-sm text-red-600">{{ error }}</p>
        <p v-else-if="hint" class="mt-1.5 text-xs leading-relaxed text-slate-500">{{ hint }}</p>
    </div>
</template>
