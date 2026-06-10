<script setup>
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { usePlanLimit } from '../composables/usePlanFeature.js';

const props = defineProps({
    limitKey: { type: String, required: true },
});

const { t } = useI18n();
const { limit, used, atLimit, nearLimit, billing } = usePlanLimit(props.limitKey);

const visible = computed(() => atLimit.value || nearLimit.value);

const message = computed(() => {
    if (props.limitKey === 'agents') {
        return atLimit.value
            ? t('settings_billing.agent_limit_reached', { used: used.value, limit: limit.value })
            : t('settings_billing.agent_limit_near', { used: used.value, limit: limit.value });
    }

    return atLimit.value
        ? t('settings_billing.ticket_limit_reached', { used: used.value, limit: limit.value })
        : t('settings_billing.ticket_limit_near', { used: used.value, limit: limit.value });
});

const toneClass = computed(() => (
    atLimit.value
        ? 'border-red-200 bg-red-50 text-red-900'
        : 'border-amber-200 bg-amber-50 text-amber-950'
));
</script>

<template>
    <div
        v-if="visible && limit !== null"
        class="mb-6 rounded-xl border px-4 py-3 text-sm"
        :class="toneClass"
    >
        <p>{{ message }}</p>
        <p v-if="billing?.plan?.name" class="mt-1 text-xs opacity-80">
            {{ t('settings_billing.current_plan_label', { plan: billing.plan.name }) }}
        </p>
        <Link
            href="/settings/billing?section=plans"
            class="mt-2 inline-flex font-medium underline underline-offset-2"
        >
            {{ t('settings_billing.upgrade_plan') }}
        </Link>
    </div>
</template>
