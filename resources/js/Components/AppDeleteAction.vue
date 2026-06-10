<script setup>
import AppIconAction from './AppIconAction.vue';

defineProps({
    label: { type: String, required: true },
    disabled: { type: Boolean, default: false },
});

defineEmits(['click']);
</script>

<template>
    <AppIconAction
        icon="delete"
        variant="danger"
        :label="label"
        :disabled="disabled"
        @click="$emit('click', $event)"
    />
</template>
