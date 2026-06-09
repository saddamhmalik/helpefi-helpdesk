<script setup>
import { computed, onMounted, onUnmounted, ref } from 'vue';
import { Link } from '@inertiajs/vue3';

const props = defineProps({
    sla: {
        type: Object,
        default: null,
    },
});

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
        return `${label} overdue`;
    }

    return `${label} left`;
};

const liveRemaining = (milestone) => {
    if (!milestone?.due_at || milestone.completed_at) {
        return milestone?.remaining_seconds ?? null;
    }

    return Math.floor((new Date(milestone.due_at).getTime() - now.value) / 1000);
};

const milestoneLabel = (milestone) => {
    if (!milestone) {
        return '—';
    }

    if (milestone.status === 'met') {
        return 'Met on time';
    }

    if (milestone.status === 'breached' && milestone.completed_at) {
        return 'Met after breach';
    }

    if (milestone.status === 'breached') {
        const remaining = liveRemaining(milestone);
        return remaining !== null ? formatDuration(remaining) : 'Breached';
    }

    if (milestone.status === 'pending') {
        const remaining = liveRemaining(milestone);
        return remaining !== null ? formatDuration(remaining) : '—';
    }

    return '—';
};

const milestoneTone = (milestone) => {
    if (!milestone) {
        return 'text-slate-500';
    }

    if (milestone.status === 'met') {
        return 'text-emerald-700';
    }

    if (milestone.status === 'breached') {
        return 'text-red-600';
    }

    const remaining = liveRemaining(milestone);
    if (remaining !== null && remaining <= 3600) {
        return 'text-amber-600';
    }

    return 'text-slate-900';
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

const formatDueAt = (value) => value ? new Date(value).toLocaleString() : '—';

const hasRules = computed(() => (props.sla?.policy?.rules?.length ?? 0) > 0);
</script>

<template>
    <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
        <div class="flex items-start justify-between gap-3">
            <div>
                <h2 class="text-lg font-semibold text-slate-900">SLA</h2>
                <p v-if="sla?.policy?.name" class="mt-1 text-xs text-slate-500">
                    {{ sla.policy.name }}
                    <span v-if="sla.policy.business_hours"> · {{ sla.policy.business_hours.name }} ({{ sla.policy.business_hours.timezone }})</span>
                </p>
            </div>
            <Link v-if="sla?.policy" href="/settings/sla" class="text-xs text-blue-600 transition hover:text-blue-700">Manage</Link>
        </div>

        <div v-if="sla?.active" class="mt-4 space-y-4">
            <div class="rounded-lg border border-slate-100 bg-slate-50/70 p-3">
                <div class="flex items-center justify-between gap-3 text-sm">
                    <span class="font-medium text-slate-700">First response</span>
                    <span class="font-medium" :class="milestoneTone(sla.first_response)">
                        {{ milestoneLabel(sla.first_response) }}
                    </span>
                </div>
                <div class="mt-2 h-2 overflow-hidden rounded-full bg-slate-200">
                    <div
                        class="h-full rounded-full transition-all duration-500"
                        :class="progressTone(sla.first_response)"
                        :style="{ width: `${liveProgress(sla.first_response, ruleMinutes('first_response'))}%` }"
                    />
                </div>
                <p class="mt-2 text-xs text-slate-500">Due {{ formatDueAt(sla.first_response?.due_at) }}</p>
            </div>

            <div class="rounded-lg border border-slate-100 bg-slate-50/70 p-3">
                <div class="flex items-center justify-between gap-3 text-sm">
                    <span class="font-medium text-slate-700">Resolution</span>
                    <span class="font-medium" :class="milestoneTone(sla.resolution)">
                        {{ milestoneLabel(sla.resolution) }}
                    </span>
                </div>
                <div class="mt-2 h-2 overflow-hidden rounded-full bg-slate-200">
                    <div
                        class="h-full rounded-full transition-all duration-500"
                        :class="progressTone(sla.resolution)"
                        :style="{ width: `${liveProgress(sla.resolution, ruleMinutes('resolution'))}%` }"
                    />
                </div>
                <p class="mt-2 text-xs text-slate-500">Due {{ formatDueAt(sla.resolution?.due_at) }}</p>
            </div>
        </div>

        <p v-else class="mt-3 text-sm text-slate-500">No SLA timer on this ticket.</p>

        <div v-if="hasRules" class="mt-4 border-t border-slate-100 pt-4">
            <p class="text-xs font-medium uppercase tracking-wide text-slate-500">Violation rules</p>
            <ul class="mt-3 space-y-3">
                <li v-for="rule in sla.policy.rules" :key="rule.key" class="rounded-lg border border-slate-100 px-3 py-2.5">
                    <p class="text-sm font-medium text-slate-800">{{ rule.label }}</p>
                    <p class="mt-1 text-xs leading-relaxed text-slate-600">{{ rule.description }}</p>
                </li>
            </ul>
        </div>
    </div>
</template>
