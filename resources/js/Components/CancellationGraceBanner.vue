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
            ? `Your paid access continues until ${periodEnd}. After that you will have ${graceDays} days to export your data (until ${accessEnds}).`
            : `Your subscription is ending soon. You will have ${graceDays} days to export your data after it ends (until ${accessEnds}).`;
    }

    return `Your subscription has ended. You have ${remaining} day${remaining === 1 ? '' : 's'} left to export your data before this workspace is blocked on ${accessEnds}.`;
});
</script>

<template>
    <div
        v-if="show"
        class="mb-4 rounded-xl border border-amber-300 bg-amber-50 px-4 py-4 shadow-sm"
        role="status"
    >
        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <p class="text-sm font-semibold text-amber-950">{{ headline }}</p>
                <p class="mt-1 text-sm leading-relaxed text-amber-900">{{ message }}</p>
            </div>
            <Link
                v-if="page.props.auth?.user?.is_admin"
                href="/settings/billing"
                class="inline-flex shrink-0 items-center justify-center rounded-lg bg-amber-900 px-3 py-2 text-sm font-medium text-white hover:bg-amber-950"
            >
                Manage billing
            </Link>
        </div>
    </div>
</template>
