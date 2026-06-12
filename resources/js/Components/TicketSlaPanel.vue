<script setup>
import { computed, onMounted, onUnmounted, ref } from 'vue';
import { Link } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import { useDateTime } from '../composables/useDateTime.js';

const { t } = useI18n();
const { formatDateTime } = useDateTime();

const props = defineProps({
    sla: {
        type: Object,
        default: null,
    },
    compact: {
        type: Boolean,
        default: false,
    },
});

const showRules = ref(false);

const now = ref(Date.now());
let timer = null;

onMounted(() => {
    timer = window.setInterval(() => {
        now.value = Date.now();
    }, 30000);
});

onUnmounted(() => {
    if (timer) {
        window.clearInterval(timer);
    }
});

const formatDuration = (totalSeconds) => {
    const abs = Math.abs(totalSeconds);
    const days = Math.floor(abs / 86400);
    const hours = Math.floor((abs % 86400) / 3600);
    const minutes = Math.floor((abs % 3600) / 60);

    const parts = [];
    if (days) parts.push(`${days}d`);
    if (hours) parts.push(`${hours}h`);
    if (minutes || !parts.length) parts.push(`${minutes}m`);

    const label = parts.join(' ');

    if (totalSeconds < 0) {
        return t('components.sla_overdue', { duration: label });
    }

    return t('components.sla_remaining', { duration: label });
};

const liveRemaining = (milestone) => {
    if (!milestone?.due_at || milestone.completed_at) {
        return milestone?.remaining_seconds ?? null;
    }

    return Math.floor((new Date(milestone.due_at).getTime() - now.value) / 1000);
};

const milestoneLabel = (milestone) => {
    if (!milestone) {
        return t('components.em_dash');
    }

    if (milestone.status === 'met') {
        return t('components.sla_met_on_time');
    }

    if (milestone.status === 'breached' && milestone.completed_at) {
        return t('components.sla_met_after_breach');
    }

    if (milestone.status === 'breached') {
        const remaining = liveRemaining(milestone);
        return remaining !== null ? formatDuration(remaining) : t('components.sla_breached_label');
    }

    if (milestone.status === 'pending') {
        const remaining = liveRemaining(milestone);
        return remaining !== null ? formatDuration(remaining) : t('components.em_dash');
    }

    return t('components.em_dash');
};

const milestoneTone = (milestone) => {
    if (!milestone) {
        return 'text-slate-500 dark:text-slate-400';
    }

    if (milestone.status === 'met') {
        return 'text-emerald-700 dark:text-emerald-300';
    }

    if (milestone.status === 'breached') {
        return 'text-red-600';
    }

    const remaining = liveRemaining(milestone);
    if (remaining !== null && remaining <= 3600) {
        return 'text-amber-600';
    }

    return 'text-slate-900 dark:text-slate-100';
};

const progressTone = (milestone) => {
    if (milestone?.status === 'breached') {
        return 'bg-red-500';
    }

    const remaining = liveRemaining(milestone);
    if (remaining !== null && remaining <= 3600) {
        return 'bg-amber-500';
    }

    return 'bg-blue-500';
};

const liveProgress = (milestone, ruleMinutes) => {
    if (!milestone?.due_at || milestone.completed_at) {
        return milestone?.progress_percent ?? 0;
    }

    const remaining = liveRemaining(milestone);
    const totalSeconds = Math.max(1, (ruleMinutes ?? 0) * 60);

    if (remaining === null) {
        return milestone.progress_percent ?? 0;
    }

    if (remaining <= 0) {
        return 100;
    }

    return Math.min(100, Math.round(((totalSeconds - remaining) / totalSeconds) * 100));
};

const ruleMinutes = (key) => props.sla?.policy?.rules?.find((rule) => rule.key === key)?.minutes ?? 0;

const formatDueAt = (value) => (value ? t('components.due_at', { date: formatDateTime(value) }) : t('components.em_dash'));

const hasRules = computed(() => (props.sla?.policy?.rules?.length ?? 0) > 0);
</script>

<template>
    <div :class="compact ? '' : 'rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 p-6 shadow-sm'">
        <div class="flex items-start justify-between gap-3" :class="compact ? 'px-1' : ''">
            <div>
                <h2 :class="compact ? 'text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400' : 'text-lg font-semibold text-slate-900 dark:text-slate-100'">
                    {{ $t('components.sla') }}
                </h2>
                <p v-if="!compact && sla?.policy?.name" class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                    {{ sla.policy.name }}
                    <span v-if="sla.policy.business_hours"> · {{ sla.policy.business_hours.name }} ({{ sla.policy.business_hours.timezone }})</span>
                </p>
                <p v-else-if="compact && sla?.policy?.name" class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">{{ sla.policy.name }}</p>
            </div>
            <Link v-if="sla?.policy" href="/settings/sla" class="text-xs text-blue-600 transition hover:text-blue-700 dark:hover:text-blue-300 dark:text-blue-300">{{ $t('components.manage') }}</Link>
        </div>

        <div v-if="sla?.active" :class="compact ? 'mt-3 space-y-2.5' : 'mt-4 space-y-4'">
            <div :class="compact ? 'rounded-lg border border-slate-200 dark:border-slate-800 px-3 py-2.5' : 'rounded-lg border border-slate-100 bg-slate-50 dark:bg-slate-950/70 p-3'">
                <div class="flex items-center justify-between gap-3 text-sm">
                    <span :class="compact ? 'text-xs font-medium text-slate-600 dark:text-slate-400' : 'font-medium text-slate-700 dark:text-slate-300'">{{ $t('components.first_response') }}</span>
                    <span :class="['font-medium', compact ? 'text-xs' : 'text-sm', milestoneTone(sla.first_response)]">
                        {{ milestoneLabel(sla.first_response) }}
                    </span>
                </div>
                <div :class="compact ? 'mt-1.5 h-1.5' : 'mt-2 h-2'" class="overflow-hidden rounded-full bg-slate-200">
                    <div
                        class="h-full rounded-full transition-all duration-500"
                        :class="progressTone(sla.first_response)"
                        :style="{ width: `${liveProgress(sla.first_response, ruleMinutes('first_response'))}%` }"
                    />
                </div>
                <p v-if="!compact" class="mt-2 text-xs text-slate-500 dark:text-slate-400">{{ formatDueAt(sla.first_response?.due_at) }}</p>
            </div>

            <div :class="compact ? 'rounded-lg border border-slate-200 dark:border-slate-800 px-3 py-2.5' : 'rounded-lg border border-slate-100 bg-slate-50 dark:bg-slate-950/70 p-3'">
                <div class="flex items-center justify-between gap-3 text-sm">
                    <span :class="compact ? 'text-xs font-medium text-slate-600 dark:text-slate-400' : 'font-medium text-slate-700 dark:text-slate-300'">{{ $t('components.resolution') }}</span>
                    <span :class="['font-medium', compact ? 'text-xs' : 'text-sm', milestoneTone(sla.resolution)]">
                        {{ milestoneLabel(sla.resolution) }}
                    </span>
                </div>
                <div :class="compact ? 'mt-1.5 h-1.5' : 'mt-2 h-2'" class="overflow-hidden rounded-full bg-slate-200">
                    <div
                        class="h-full rounded-full transition-all duration-500"
                        :class="progressTone(sla.resolution)"
                        :style="{ width: `${liveProgress(sla.resolution, ruleMinutes('resolution'))}%` }"
                    />
                </div>
                <p v-if="!compact" class="mt-2 text-xs text-slate-500 dark:text-slate-400">{{ formatDueAt(sla.resolution?.due_at) }}</p>
            </div>
        </div>

        <p v-else :class="compact ? 'mt-2 text-xs text-slate-500 dark:text-slate-400' : 'mt-3 text-sm text-slate-500 dark:text-slate-400'">{{ $t('components.no_sla_timer') }}</p>

        <div v-if="hasRules && !compact" class="mt-4 border-t border-slate-100 dark:border-slate-800 pt-4">
            <p class="text-xs font-medium uppercase tracking-wide text-slate-500 dark:text-slate-400">{{ $t('components.violation_rules') }}</p>
            <ul class="mt-3 space-y-3">
                <li v-for="rule in sla.policy.rules" :key="rule.key" class="rounded-lg border border-slate-100 dark:border-slate-800 px-3 py-2.5">
                    <p class="text-sm font-medium text-slate-800 dark:text-slate-200">{{ rule.label }}</p>
                    <p class="mt-1 text-xs leading-relaxed text-slate-600 dark:text-slate-400">{{ rule.description }}</p>
                </li>
            </ul>
        </div>

        <div v-else-if="hasRules && compact" class="mt-3 border-t border-slate-100 dark:border-slate-800 pt-3">
            <button
                type="button"
                class="flex w-full items-center justify-between text-xs font-medium text-slate-500 dark:text-slate-400 hover:text-slate-700 dark:hover:text-slate-300 dark:text-slate-300"
                @click="showRules = !showRules"
            >
                <span>{{ $t('components.violation_rules') }}</span>
                <svg class="h-4 w-4 transition" :class="showRules ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                </svg>
            </button>
            <ul v-if="showRules" class="mt-2 space-y-2">
                <li v-for="rule in sla.policy.rules" :key="rule.key" class="text-xs leading-relaxed text-slate-600 dark:text-slate-400">
                    <span class="font-medium text-slate-700 dark:text-slate-300">{{ rule.label }}:</span> {{ rule.description }}
                </li>
            </ul>
        </div>
    </div>
</template>
