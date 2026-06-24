<script setup>
import { Link } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { useAsyncState } from '../composables/useAsyncState.js';
import { useDateTime } from '../composables/useDateTime.js';
import { AppAlert, AppAsyncState, AppButton, AppErrorState, AppSpinner } from './ui';

const props = defineProps({
    ticketId: { type: Number, required: true },
});

const { t } = useI18n();
const { formatDate } = useDateTime();

const refreshing = ref(false);
const refreshError = ref(null);

const dateOptions = { month: 'short', day: 'numeric', year: 'numeric' };

const fetchContext = async () => {
    const response = await fetch(`/tickets/${props.ticketId}/customer-context`, {
        headers: { Accept: 'application/json' },
        credentials: 'same-origin',
    });

    if (!response.ok) {
        throw new Error(t('components.unable_to_load_customer_context'));
    }

    return response.json();
};

const { data: context, loading, error, execute } = useAsyncState(fetchContext, { immediate: true });

const healthClass = computed(() => {
    const level = context.value?.health?.level;

    if (level === 'healthy') {
        return 'bg-emerald-50 dark:bg-emerald-950/40 text-emerald-700 dark:text-emerald-300 ring-emerald-200';
    }

    if (level === 'at_risk') {
        return 'bg-amber-50 dark:bg-amber-950/40 text-amber-700 dark:text-amber-300 ring-amber-200';
    }

    if (level === 'critical') {
        return 'bg-red-50 dark:bg-red-950/40 text-red-700 dark:text-red-300 ring-red-200';
    }

    return 'bg-slate-50 dark:bg-slate-950 text-slate-600 dark:text-slate-400 ring-slate-200 dark:ring-slate-700';
});

const formatMoney = (value, currency) => {
    if (value === null || value === undefined || value === '') {
        return null;
    }

    const amount = Number(value);

    if (Number.isNaN(amount)) {
        return String(value);
    }

    try {
        return new Intl.NumberFormat(undefined, {
            style: 'currency',
            currency: currency || 'USD',
            maximumFractionDigits: 0,
        }).format(amount);
    } catch {
        return amount.toLocaleString();
    }
};

const refreshCrm = async () => {
    refreshing.value = true;
    refreshError.value = null;

    try {
        const response = await fetch(`/tickets/${props.ticketId}/customer-context/refresh`, {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
            },
            credentials: 'same-origin',
        });

        if (!response.ok) {
            throw new Error(t('components.unable_to_refresh_crm'));
        }

        context.value = await response.json();
    } catch (e) {
        refreshError.value = e.message || t('components.unable_to_refresh_crm');
    } finally {
        refreshing.value = false;
    }
};

watch(() => props.ticketId, () => {
    refreshError.value = null;
    execute();
});
</script>

<template>
    <section class="px-4 py-3">
        <div class="flex items-center justify-between gap-2">
            <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-400 dark:text-slate-500">{{ $t('components.customer_context') }}</p>
            <AppButton
                v-if="context?.crm"
                variant="ghost"
                size="xs"
                class="!px-0 !py-0 text-[10px] font-medium text-blue-600 hover:bg-transparent hover:text-blue-700 dark:text-blue-300 dark:hover:bg-transparent dark:hover:text-blue-200"
                :loading="refreshing"
                @click="refreshCrm"
            >
                {{ refreshing ? $t('components.refreshing') : $t('components.refresh_crm') }}
            </AppButton>
        </div>

        <AppAsyncState
            class="mt-2"
            :loading="loading"
            :error="error"
            :centered="false"
            :loading-label="$t('components.loading_customer_context_ellipsis')"
            @retry="execute"
        >
            <template #loading>
                <div class="flex items-center gap-2 py-1">
                    <AppSpinner size="xs" inline :label="$t('components.loading_customer_context_ellipsis')" />
                    <p class="text-xs agent-text-subtle">{{ $t('components.loading_customer_context_ellipsis') }}</p>
                </div>
            </template>

            <template #error="{ retry }">
                <AppErrorState
                    size="compact"
                    :message="error"
                    @retry="retry"
                />
            </template>

            <AppAlert v-if="refreshError" tone="error" dismissible class="mb-3" @dismiss="refreshError = null">
                {{ refreshError }}
            </AppAlert>

            <div v-if="context" class="space-y-3">
                <div class="flex items-center justify-between gap-2">
                    <div class="min-w-0">
                        <p v-if="context.organization?.name" class="truncate text-sm font-medium text-slate-900 dark:text-slate-100">
                            {{ context.organization.name }}
                        </p>
                        <p v-else class="truncate text-sm font-medium text-slate-900 dark:text-slate-100">
                            {{ context.contact.name || context.contact.email }}
                        </p>
                        <p v-if="context.organization?.customer_tier" class="mt-0.5 text-[11px] uppercase tracking-wide text-slate-500 dark:text-slate-400">
                            {{ $t('components.customer_tier', { tier: context.organization.customer_tier }) }}
                        </p>
                    </div>
                    <span
                        class="shrink-0 rounded-full px-2 py-0.5 text-[11px] font-semibold ring-1"
                        :class="healthClass"
                    >
                        {{ context.health.score }} · {{ context.health.label }}
                    </span>
                </div>

                <dl class="grid grid-cols-2 gap-2 text-xs">
                    <div class="rounded-lg bg-slate-50 dark:bg-slate-950 px-2.5 py-2">
                        <dt class="text-slate-500 dark:text-slate-400">{{ $t('components.open_tickets') }}</dt>
                        <dd class="mt-0.5 font-semibold text-slate-900 dark:text-slate-100">{{ context.metrics.open_tickets }}</dd>
                    </div>
                    <div class="rounded-lg bg-slate-50 dark:bg-slate-950 px-2.5 py-2">
                        <dt class="text-slate-500 dark:text-slate-400">{{ $t('components.csat_90d') }}</dt>
                        <dd class="mt-0.5 font-semibold text-slate-900 dark:text-slate-100">
                            <span v-if="context.metrics.csat_average_90d !== null">
                                {{ context.metrics.csat_average_90d }}/5
                            </span>
                            <span v-else>{{ $t('components.em_dash') }}</span>
                        </dd>
                    </div>
                    <div class="rounded-lg bg-slate-50 dark:bg-slate-950 px-2.5 py-2">
                        <dt class="text-slate-500 dark:text-slate-400">{{ $t('components.sla_breaches') }}</dt>
                        <dd class="mt-0.5 font-semibold text-slate-900 dark:text-slate-100">{{ context.metrics.sla_breaches_90d }}</dd>
                    </div>
                    <div class="rounded-lg bg-slate-50 dark:bg-slate-950 px-2.5 py-2">
                        <dt class="text-slate-500 dark:text-slate-400">{{ $t('components.last_contact') }}</dt>
                        <dd class="mt-0.5 font-semibold text-slate-900 dark:text-slate-100">{{ formatDate(context.metrics.last_contact_at, dateOptions) }}</dd>
                    </div>
                </dl>

                <div v-if="context.crm" class="rounded-lg border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 p-2.5">
                    <div class="flex items-start justify-between gap-2">
                        <div class="min-w-0">
                            <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-400 dark:text-slate-500">
                                {{ context.crm.provider_label }}
                            </p>
                            <p v-if="context.crm.name" class="mt-1 truncate text-sm font-medium text-slate-900 dark:text-slate-100">
                                {{ context.crm.name }}
                            </p>
                            <p v-if="context.crm.company" class="truncate text-xs text-slate-500 dark:text-slate-400">{{ context.crm.company }}</p>
                        </div>
                        <a
                            v-if="context.crm.url"
                            :href="context.crm.url"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="shrink-0 text-[11px] font-medium text-blue-600 hover:text-blue-700 dark:hover:text-blue-300 dark:text-blue-300"
                        >
                            {{ $t('components.open') }}
                        </a>
                    </div>
                    <dl class="mt-2 space-y-1 text-xs">
                        <div v-if="context.crm.lifecycle_stage" class="flex justify-between gap-2">
                            <dt class="text-slate-500 dark:text-slate-400">{{ $t('components.lifecycle') }}</dt>
                            <dd class="font-medium capitalize text-slate-800 dark:text-slate-200">{{ context.crm.lifecycle_stage }}</dd>
                        </div>
                        <div v-if="context.crm.owner" class="flex justify-between gap-2">
                            <dt class="text-slate-500 dark:text-slate-400">{{ $t('components.owner') }}</dt>
                            <dd class="truncate font-medium text-slate-800 dark:text-slate-200">{{ context.crm.owner }}</dd>
                        </div>
                        <div v-if="context.crm.deal_value !== null && context.crm.deal_value !== undefined" class="flex justify-between gap-2">
                            <dt class="text-slate-500 dark:text-slate-400">{{ $t('components.deal_value') }}</dt>
                            <dd class="font-medium text-slate-800 dark:text-slate-200">{{ formatMoney(context.crm.deal_value) }}</dd>
                        </div>
                    </dl>
                </div>

                <div v-if="context.commerce?.recent_orders?.length" class="rounded-lg border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 p-2.5">
                    <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-400 dark:text-slate-500">
                        {{ $t('components.commerce_orders', { provider: context.commerce.provider_label }) }}
                    </p>
                    <ul class="mt-2 space-y-1.5">
                        <li
                            v-for="order in context.commerce.recent_orders"
                            :key="order.id"
                            class="flex items-center justify-between gap-2 text-xs"
                        >
                            <div class="min-w-0">
                                <a
                                    v-if="order.url"
                                    :href="order.url"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    class="font-medium text-blue-600 hover:text-blue-700 dark:hover:text-blue-300 dark:text-blue-300"
                                >
                                    {{ order.name }}
                                </a>
                                <span v-else class="font-medium text-slate-800 dark:text-slate-200">{{ order.name }}</span>
                                <p class="text-slate-500 dark:text-slate-400">{{ formatDate(order.created_at, dateOptions) }}</p>
                            </div>
                            <span class="shrink-0 font-medium text-slate-800 dark:text-slate-200">
                                {{ formatMoney(order.total, order.currency) }}
                            </span>
                        </li>
                    </ul>
                </div>

                <Link
                    v-if="context.contact?.id"
                    :href="`/contacts/${context.contact.id}`"
                    class="inline-flex text-xs font-medium text-blue-600 hover:text-blue-700 dark:hover:text-blue-300 dark:text-blue-300"
                >
                    {{ $t('components.view_full_contact_profile') }}
                </Link>
            </div>
        </AppAsyncState>
    </section>
</template>
