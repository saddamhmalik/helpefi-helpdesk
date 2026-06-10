<script setup>
import ErrorShell from '../../Components/ErrorShell.vue';

defineProps({
    status: {
        type: Number,
        default: 404,
    },
});
</script>

<template>
    <ErrorShell :status="status" variant="not_found" />
</template>
