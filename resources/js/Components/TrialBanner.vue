<script setup>
import { usePage } from '@inertiajs/vue3';
import { computed, onMounted, ref } from 'vue';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();

const page = usePage();
const billing = computed(() => page.props.billing);
const dismissed = ref(false);

const storageKey = computed(() => {
    const tenantId = page.props.tenantId ?? 'tenant';
    const userId = page.props.auth?.user?.id ?? 'guest';

    return `trial-banner-dismissed:${tenantId}:${userId}`;
});

onMounted(() => {
    dismissed.value = localStorage.getItem(storageKey.value) === '1';
});

const eligible = computed(() => billing.value?.on_trial && billing.value?.trial_days_remaining !== null);
const show = computed(() => eligible.value && !dismissed.value);

const message = computed(() => t('components.trial_days_remaining_short', {
    days: billing.value?.trial_days_remaining,
}));

const dismiss = () => {
    dismissed.value = true;
    localStorage.setItem(storageKey.value, '1');
};
</script>

<template>
    <div
        v-if="show"
        class="flex items-center gap-2 rounded-md border border-blue-200/70 bg-blue-50/80 px-2 py-1 text-xs dark:border-blue-900/40 dark:bg-blue-950/30"
        role="status"
        :title="t('components.trial_days_remaining_message', { days: billing?.trial_days_remaining })"
    >
        <span class="shrink-0 rounded bg-blue-600 px-1.5 py-px text-[10px] font-bold uppercase leading-none tracking-wide text-white">
            {{ $t('components.trial_badge') }}
        </span>
        <span class="min-w-0 flex-1 truncate text-blue-900 dark:text-blue-100">{{ message }}</span>
        <button
            type="button"
            class="shrink-0 rounded p-0.5 text-blue-700/80 transition hover:bg-blue-100 hover:text-blue-900 dark:text-blue-300 dark:hover:bg-blue-900/40 dark:hover:text-blue-100"
            :title="$t('components.dismiss')"
            :aria-label="$t('components.dismiss')"
            @click="dismiss"
        >
            <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
            </svg>
        </button>
    </div>
</template>
