<script setup>
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { usePlanLimit } from '../composables/usePlanFeature.js';

const { t } = useI18n();

const agents = usePlanLimit('agents');
const tickets = usePlanLimit('tickets_monthly');

const activeLimit = computed(() => {
    if (agents.atLimit.value || agents.nearLimit.value) {
        return { key: 'agents', ...agents };
    }

    if (tickets.atLimit.value || tickets.nearLimit.value) {
        return { key: 'tickets_monthly', ...tickets };
    }

    return null;
});

const visible = computed(() => activeLimit.value !== null && activeLimit.value.limit.value !== null);

const message = computed(() => {
    if (!activeLimit.value) {
        return '';
    }

    const { key, used, limit, atLimit } = activeLimit.value;

    if (key === 'agents') {
        return atLimit.value
            ? t('settings_billing.agent_limit_reached', { used: used.value, limit: limit.value })
            : t('settings_billing.agent_limit_near', { used: used.value, limit: limit.value });
    }

    return atLimit.value
        ? t('settings_billing.ticket_limit_reached', { used: used.value, limit: limit.value })
        : t('settings_billing.ticket_limit_near', { used: used.value, limit: limit.value });
});

const toneClass = computed(() => {
    if (!activeLimit.value) {
        return '';
    }

    return activeLimit.value.atLimit.value
        ? 'border-red-200/70 bg-red-50/90 dark:border-red-900/40 dark:bg-red-950/30'
        : 'border-amber-200/70 bg-amber-50/90 dark:border-amber-900/40 dark:bg-amber-950/30';
});
</script>

<template>
    <div
        v-if="visible"
        class="flex items-center gap-2 rounded-md border px-2 py-1 text-xs"
        :class="toneClass"
        role="status"
    >
        <span class="min-w-0 flex-1 truncate">{{ message }}</span>
        <Link
            href="/settings/billing?section=plans"
            class="shrink-0 font-semibold underline underline-offset-2"
        >
            {{ t('settings_billing.upgrade_plan') }}
        </Link>
    </div>
</template>
