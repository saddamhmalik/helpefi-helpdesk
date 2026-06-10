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

const message = computed(() => t('components.trial_days_remaining_message', {
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
        class="mb-2 flex items-center gap-2 rounded-lg border border-blue-200 bg-blue-50 px-3 py-2"
        role="status"
    >
        <span class="shrink-0 text-sm font-semibold text-blue-900">{{ $t('components.free_trial_active') }}</span>
        <span class="min-w-0 flex-1 truncate text-sm text-blue-800">{{ message }}</span>
        <button
            type="button"
            class="shrink-0 rounded-md p-1.5 text-blue-700 transition hover:bg-blue-100 hover:text-blue-900"
            :title="$t('components.dismiss')"
            :aria-label="$t('components.dismiss')"
            @click="dismiss"
        >
            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
            </svg>
        </button>
    </div>
</template>
