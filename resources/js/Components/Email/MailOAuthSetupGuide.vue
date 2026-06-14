<script setup>
const stepsByProvider = {
    google: ['connect', 'inbox', 'test'],
    microsoft: ['connect', 'test'],
    zoho: ['connect', 'test'],
};

defineProps({
    provider: {
        type: String,
        required: true,
    },
});
</script>

<template>
    <div class="rounded-lg border border-sky-200 bg-sky-50 p-4 dark:border-sky-900/60 dark:bg-sky-950/40">
        <p class="text-sm font-medium text-sky-950 dark:text-sky-100">{{ $t(`settings_email.${provider}_oauth_tenant_title`) }}</p>
        <ol class="mt-3 list-decimal space-y-2 pl-5 text-xs leading-relaxed text-sky-900 dark:text-sky-200">
            <li v-for="step in stepsByProvider[provider]" :key="step">
                {{ $t(`settings_email.${provider}_oauth_tenant_step_${step}`) }}
            </li>
        </ol>
    </div>
</template>
