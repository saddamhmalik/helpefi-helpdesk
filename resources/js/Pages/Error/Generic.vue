<script setup>
import ErrorShell from '../../Components/ErrorShell.vue';

defineProps({
    status: {
        type: Number,
        required: true,
    },
});
</script>

<template>
    <ErrorShell :status="status" variant="generic" />
</template>
