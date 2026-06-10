<script setup>
import AppIconAction from './AppIconAction.vue';

defineProps({
    label: { type: String, required: true },
    href: { type: String, default: null },
    disabled: { type: Boolean, default: false },
});

defineEmits(['click']);
</script>

<template>
    <AppIconAction
        icon="edit"
        variant="primary"
        :label="label"
        :href="href"
        :disabled="disabled"
        @click="$emit('click', $event)"
    />
</template>
