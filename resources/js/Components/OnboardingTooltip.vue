<script setup>
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { useOnboardingTip } from '../composables/useOnboardingTip.js';

const props = defineProps({
    tipKey: { type: String, required: true },
    title: { type: String, default: '' },
    message: { type: String, required: true },
});

const { t } = useI18n();
const { visible, dismiss } = useOnboardingTip(props.tipKey);

const titleText = computed(() => props.title || t('growth.onboarding_tip_title'));
</script>

<template>
    <div
        v-if="visible"
        class="flex items-start gap-3 rounded-xl border border-indigo-200/80 bg-indigo-50/90 px-4 py-3 text-sm dark:border-indigo-900/50 dark:bg-indigo-950/40"
        role="status"
    >
        <div class="mt-0.5 flex h-7 w-7 shrink-0 items-center justify-center rounded-lg bg-indigo-600 text-white">
            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
        </div>
        <div class="min-w-0 flex-1">
            <p class="font-semibold text-indigo-950 dark:text-indigo-100">{{ titleText }}</p>
            <p class="mt-0.5 text-indigo-900/90 dark:text-indigo-200/90">{{ message }}</p>
        </div>
        <button
            type="button"
            class="shrink-0 rounded-md px-2 py-1 text-xs font-medium text-indigo-700 hover:bg-indigo-100 dark:text-indigo-300 dark:hover:bg-indigo-900/50"
            @click="dismiss"
        >
            {{ t('growth.onboarding_dismiss') }}
        </button>
    </div>
</template>
