<script setup>
import ErrorShell from '../../Components/ErrorShell.vue';

defineProps({
    status: {
        type: Number,
        default: 500,
    },
});
</script>

<template>
    <ErrorShell :status="status" variant="server" />
</template>
