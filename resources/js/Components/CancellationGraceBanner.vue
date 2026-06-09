<script setup>
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const page = usePage();
const billing = computed(() => page.props.billing);

const show = computed(() => billing.value?.show_cancellation_banner);

const formatDate = (value) => {
    if (!value) {
        return '';
    }

    return new Date(value).toLocaleDateString(undefined, {
        month: 'long',
        day: 'numeric',
        year: 'numeric',
    });
};

const headline = computed(() => (
    billing.value?.cancellation_pending
        ? 'Subscription scheduled to end'
        : 'Export grace period'
));

const message = computed(() => {
    if (!billing.value) {
        return '';
    }

    const graceDays = billing.value.cancellation_grace_days ?? 3;
    const accessEnds = formatDate(billing.value.access_ends_at);
    const remaining = billing.value.grace_days_remaining ?? 0;

    if (billing.value.cancellation_pending) {
        const periodEnd = formatDate(billing.value.renews_at);

        return periodEnd
            ? `Paid access until ${periodEnd}. ${graceDays}-day export window after (until ${accessEnds}).`
            : `Subscription ending soon. ${graceDays}-day export window after it ends (until ${accessEnds}).`;
    }

    return `${remaining} day${remaining === 1 ? '' : 's'} left to export data before workspace is blocked on ${accessEnds}.`;
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
            Manage billing
        </Link>
    </div>
</template>
