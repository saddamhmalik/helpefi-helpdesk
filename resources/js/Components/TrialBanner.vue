<script setup>
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const page = usePage();
const billing = computed(() => page.props.billing);

const show = computed(() => billing.value?.on_trial && billing.value?.trial_days_remaining !== null);
</script>

<template>
    <div v-if="show" class="mb-4 rounded-xl border border-blue-200 bg-blue-50 px-4 py-3">
        <p class="text-sm font-semibold text-blue-900">Free trial active</p>
        <p class="text-sm text-blue-800">
            {{ billing.trial_days_remaining }} day{{ billing.trial_days_remaining === 1 ? '' : 's' }} left with full platform access.
            We'll ask you to choose a plan when your trial ends.
        </p>
    </div>
</template>
