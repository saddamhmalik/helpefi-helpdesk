<script setup>
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { useDateTime } from '../composables/useDateTime.js';

const page = usePage();
const { t } = useI18n();
const { formatDate } = useDateTime();

const billing = computed(() => page.props.billing);

const show = computed(() => billing.value?.show_cancellation_banner);

const dateOptions = { month: 'long', day: 'numeric', year: 'numeric' };

const headline = computed(() => (
    billing.value?.cancellation_pending
        ? t('components.subscription_scheduled_to_end')
        : t('components.export_grace_period')
));

const message = computed(() => {
    if (!billing.value) {
        return '';
    }

    const graceDays = billing.value.cancellation_grace_days ?? 3;
    const accessEnds = formatDate(billing.value.access_ends_at, dateOptions);
    const remaining = billing.value.grace_days_remaining ?? 0;

    if (billing.value.cancellation_pending) {
        const periodEnd = billing.value.renews_at
            ? formatDate(billing.value.renews_at, dateOptions)
            : '';

        return periodEnd
            ? t('components.cancellation_paid_access_message', { periodEnd, graceDays, accessEnds })
            : t('components.cancellation_ending_soon_message', { graceDays, accessEnds });
    }

    return t('components.grace_days_remaining_message', { remaining, accessEnds });
});
</script>

<template>
    <div
        v-if="show"
        class="mb-2 flex items-center gap-3 rounded-lg border border-amber-300 bg-amber-50 px-3 py-2 text-sm"
        role="status"
        :title="message"
    >
        <span class="shrink-0 font-semibold text-amber-950">{{ headline }}</span>
        <span class="hidden min-w-0 flex-1 truncate text-amber-900 sm:inline">{{ message }}</span>
        <Link
            v-if="page.props.auth?.user?.is_admin"
            href="/settings/billing"
            class="ml-auto shrink-0 rounded-md bg-amber-900 px-2.5 py-1 text-xs font-medium text-white hover:bg-amber-950"
        >
            {{ $t('components.manage_billing') }}
        </Link>
    </div>
</template>
