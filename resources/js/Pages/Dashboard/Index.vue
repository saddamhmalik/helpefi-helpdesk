<script setup>
import { Head, Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import AgentLayout from '../../Layouts/AgentLayout.vue';
import AppAvatar from '../../Components/AppAvatar.vue';
import { useI18n } from 'vue-i18n';

const props = defineProps({
    stats: Object,
    csat: Object,
    deflection: Object,
    kbDeflection: Object,
    ticketStatuses: Array,
    ticketPriorities: Array,
    topAgents: Array,
    volumeTrend: Array,
});

const { t } = useI18n();

const page = usePage();
const userName = computed(() => page.props.auth?.user?.name?.split(' ')[0] ?? 'there');

const maxTrend = computed(() => Math.max(...(props.volumeTrend?.map((d) => d.count) ?? [1]), 1));
const totalVolume = computed(() => props.volumeTrend?.reduce((sum, day) => sum + day.count, 0) ?? 0);

const maxAgentCount = computed(() => Math.max(...(props.topAgents?.map((a) => a.open_count) ?? [1]), 1));
const maxStatusCount = computed(() => Math.max(...(props.ticketStatuses?.map((s) => s.tickets_count) ?? [1]), 1));
const maxPriorityCount = computed(() => Math.max(...(props.ticketPriorities?.map((p) => p.tickets_count) ?? [1]), 1));

const chartPoints = computed(() => {
    const data = props.volumeTrend ?? [];
    if (!data.length) return '';

    const width = 100;
    const height = 48;
    const step = width / Math.max(data.length - 1, 1);

    return data.map((day, index) => {
        const x = index * step;
        const y = height - (day.count / maxTrend.value) * height;

        return `${x},${y}`;
    }).join(' ');
});

const areaPath = computed(() => {
    const data = props.volumeTrend ?? [];
    if (!data.length) return '';

    const width = 100;
    const height = 48;
    const step = width / Math.max(data.length - 1, 1);

    let path = `M 0,${height}`;
    data.forEach((day, index) => {
        const x = index * step;
        const y = height - (day.count / maxTrend.value) * height;
        path += ` L ${x},${y}`;
    });
    path += ` L ${width},${height} Z`;

    return path;
});

const statCards = computed(() => [
    {
        label: t('dashboard.open_tickets'),
        value: props.stats.openTickets,
        href: '/tickets',
        tone: 'blue',
        icon: 'ticket',
    },
    {
        label: t('dashboard.created_this_week'),
        value: props.stats.createdThisWeek,
        href: '/reports?type=tickets&run=1',
        tone: 'violet',
        icon: 'plus',
    },
    {
        label: t('dashboard.resolved_this_week'),
        value: props.stats.resolvedThisWeek,
        href: '/reports?type=tickets&run=1',
        tone: 'emerald',
        icon: 'check',
    },
    {
        label: t('dashboard.sla_breaches'),
        value: props.stats.slaBreaches,
        href: '/reports?type=sla_breaches&run=1',
        tone: props.stats.slaBreaches ? 'red' : 'slate',
        icon: 'alert',
        alert: props.stats.slaBreaches > 0,
    },
    {
        label: t('dashboard.contacts'),
        value: props.stats.contacts,
        href: '/contacts',
        tone: 'cyan',
        icon: 'users',
    },
    {
        label: t('dashboard.articles'),
        value: props.stats.publishedArticles,
        href: '/knowledge',
        tone: 'amber',
        icon: 'book',
    },
]);

const toneClasses = {
    blue: { bg: 'bg-blue-50', icon: 'bg-blue-500/10 text-blue-600', ring: 'ring-blue-100' },
    violet: { bg: 'bg-violet-50', icon: 'bg-violet-500/10 text-violet-600', ring: 'ring-violet-100' },
    emerald: { bg: 'bg-emerald-50', icon: 'bg-emerald-500/10 text-emerald-600', ring: 'ring-emerald-100' },
    red: { bg: 'bg-red-50', icon: 'bg-red-500/10 text-red-600', ring: 'ring-red-100' },
    slate: { bg: 'bg-slate-50', icon: 'bg-slate-500/10 text-slate-600', ring: 'ring-slate-100' },
    cyan: { bg: 'bg-cyan-50', icon: 'bg-cyan-500/10 text-cyan-600', ring: 'ring-cyan-100' },
    amber: { bg: 'bg-amber-50', icon: 'bg-amber-500/10 text-amber-600', ring: 'ring-amber-100' },
};

const priorityTone = (slug) => ({
    low: { bar: 'bg-slate-400', badge: 'bg-slate-100 text-slate-700' },
    normal: { bar: 'bg-blue-500', badge: 'bg-blue-50 text-blue-700' },
    high: { bar: 'bg-orange-500', badge: 'bg-orange-50 text-orange-700' },
    urgent: { bar: 'bg-red-500', badge: 'bg-red-50 text-red-700' },
}[slug] ?? { bar: 'bg-slate-400', badge: 'bg-slate-100 text-slate-700' });

const statusTone = (status) => {
    if (status.color) {
        return { bar: '', badge: 'text-slate-700', dot: status.color };
    }

    const slug = (status.slug ?? '').toLowerCase();

    return {
        open: { bar: 'bg-emerald-500', badge: 'bg-emerald-50 text-emerald-700', dot: '#10b981' },
        pending: { bar: 'bg-amber-500', badge: 'bg-amber-50 text-amber-700', dot: '#f59e0b' },
        resolved: { bar: 'bg-blue-500', badge: 'bg-blue-50 text-blue-700', dot: '#3b82f6' },
        closed: { bar: 'bg-slate-400', badge: 'bg-slate-100 text-slate-600', dot: '#94a3b8' },
    }[slug] ?? { bar: 'bg-slate-400', badge: 'bg-slate-100 text-slate-700', dot: '#94a3b8' };
};

const csatStars = computed(() => {
    const rating = Number(props.csat?.average_rating);
    if (!rating) return 0;

    return Math.round(rating);
});

const greeting = computed(() => {
    const hour = new Date().getHours();
    if (hour < 12) return 'Good morning';
    if (hour < 17) return 'Good afternoon';

    return 'Good evening';
});
</script>

<template>
    <Head :title="$t('dashboard.dashboard')" />
    <AgentLayout>
        <div class="space-y-6 pb-8">
            <div class="relative overflow-hidden rounded-2xl border border-slate-200/60 bg-white/60 px-5 py-6 sm:px-6 sm:py-8">
                <div class="pointer-events-none absolute inset-0 bg-gradient-to-br from-blue-600/[0.07] via-violet-500/[0.04] to-transparent" />

                <div class="relative flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <h1 class="text-3xl font-bold tracking-tight text-slate-900">{{ greeting }}, {{ userName }}</h1>
                        <p class="mt-1.5 max-w-xl text-sm text-slate-500">{{ $t('dashboard.real-time_overview_of_tickets_team_workload_and_customer_satisfaction') }}</p>
                    </div>
                    <div class="flex flex-wrap items-center gap-2">
                        <Link
                            href="/workspace"
                            class="inline-flex items-center gap-2 rounded-xl border border-slate-200/80 bg-white/80 px-4 py-2.5 text-sm font-semibold text-slate-700 shadow-sm backdrop-blur transition hover:bg-white hover:shadow-md"
                        >
                            <svg class="h-4 w-4 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h7" />
                            </svg>
                            {{ $t('dashboard.workspace_link') }}
                        </Link>
                        <Link
                            href="/reports"
                            class="inline-flex items-center gap-2 rounded-xl border border-slate-200/80 bg-white/80 px-4 py-2.5 text-sm font-semibold text-slate-700 shadow-sm backdrop-blur transition hover:bg-white hover:shadow-md"
                        >
                            <svg class="h-4 w-4 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                            </svg>
                            {{ $t('dashboard.reports_link') }}
                        </Link>
                    </div>
                </div>
            </div>

            <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-6">
                <Link
                    v-for="card in statCards"
                    :key="card.label"
                    :href="card.href"
                    class="group relative overflow-hidden rounded-2xl border border-white/60 bg-white p-5 shadow-sm ring-1 backdrop-blur transition hover:-translate-y-0.5 hover:shadow-lg"
                    :class="toneClasses[card.tone].ring"
                >
                    <div class="absolute -right-4 -top-4 h-20 w-20 rounded-full opacity-60 blur-2xl transition group-hover:opacity-80" :class="toneClasses[card.tone].bg" />
                    <div class="relative flex items-start justify-between gap-3">
                        <div>
                            <p class="text-xs font-medium uppercase tracking-wide text-slate-500">{{ card.label }}</p>
                            <p
                                class="mt-2 text-3xl font-bold tabular-nums tracking-tight"
                                :class="card.alert ? 'text-red-600' : card.tone === 'emerald' ? 'text-emerald-600' : 'text-slate-900'"
                            >
                                {{ card.value }}
                            </p>
                        </div>
                        <span class="flex h-10 w-10 items-center justify-center rounded-xl" :class="toneClasses[card.tone].icon">
                            <svg v-if="card.icon === 'ticket'" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 110-4V7a2 2 0 00-2-2H5z" />
                            </svg>
                            <svg v-else-if="card.icon === 'plus'" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                            </svg>
                            <svg v-else-if="card.icon === 'check'" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            <svg v-else-if="card.icon === 'alert'" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                            </svg>
                            <svg v-else-if="card.icon === 'users'" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z" />
                            </svg>
                            <svg v-else class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />
                            </svg>
                        </span>
                    </div>
                </Link>
            </div>

            <div class="grid gap-6 lg:grid-cols-12">
                <div class="rounded-2xl border border-white/60 bg-white/90 p-6 shadow-sm ring-1 ring-slate-100 backdrop-blur lg:col-span-5">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <h2 class="text-base font-semibold text-slate-900">{{ $t('dashboard.ticket_volume') }}</h2>
                            <p class="mt-0.5 text-sm text-slate-500">{{ $t('dashboard.last_7_days') }}</p>
                        </div>
                        <div class="rounded-xl bg-blue-50 px-3 py-1.5 text-right">
                            <p class="text-lg font-bold tabular-nums text-blue-700">{{ totalVolume }}</p>
                            <p class="text-[10px] font-medium uppercase tracking-wide text-blue-600/80">{{ $t('dashboard.total') }}</p>
                        </div>
                    </div>

                    <div class="relative mt-6 h-40">
                        <svg viewBox="0 0 100 48" preserveAspectRatio="none" class="h-full w-full overflow-visible">
                            <defs>
                                <linearGradient id="volumeGradient" x1="0" y1="0" x2="0" y2="1">
                                    <stop offset="0%" stop-color="#3b82f6" stop-opacity="0.35" />
                                    <stop offset="100%" stop-color="#3b82f6" stop-opacity="0.02" />
                                </linearGradient>
                            </defs>
                            <path v-if="areaPath" :d="areaPath" fill="url(#volumeGradient)" />
                            <polyline
                                v-if="chartPoints"
                                :points="chartPoints"
                                fill="none"
                                stroke="#3b82f6"
                                stroke-width="1.5"
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                vector-effect="non-scaling-stroke"
                            />
                        </svg>
                        <div class="absolute inset-x-0 bottom-0 flex justify-between px-0.5">
                            <span v-for="day in volumeTrend" :key="day.date" class="text-[10px] font-medium text-slate-400">{{ day.label }}</span>
                        </div>
                    </div>

                    <div class="mt-4 flex flex-wrap gap-2">
                        <span
                            v-for="day in volumeTrend"
                            :key="`${day.date}-pill`"
                            class="inline-flex items-center gap-1.5 rounded-lg bg-slate-50 px-2.5 py-1 text-xs text-slate-600"
                        >
                            <span class="font-semibold text-slate-900">{{ day.count }}</span>
                            {{ day.label }}
                        </span>
                    </div>
                </div>

                <div class="rounded-2xl border border-white/60 bg-white/90 p-6 shadow-sm ring-1 ring-slate-100 backdrop-blur lg:col-span-3">
                    <h2 class="text-base font-semibold text-slate-900">{{ $t('dashboard.csat_score') }}</h2>
                    <p class="mt-0.5 text-sm text-slate-500">{{ $t('dashboard.last_30_days') }}</p>

                    <div class="mt-6 flex items-end gap-4">
                        <div>
                            <p class="text-4xl font-bold tracking-tight text-slate-900">
                                {{ csat?.average_rating ?? '—' }}
                            </p>
                            <p v-if="csat?.average_rating" class="mt-1 text-sm text-slate-500">{{ $t('dashboard.out_of_5') }}</p>
                        </div>
                        <div v-if="csat?.average_rating" class="mb-1 flex gap-0.5">
                            <span
                                v-for="star in 5"
                                :key="star"
                                class="text-xl"
                                :class="star <= csatStars ? 'text-amber-400' : 'text-slate-200'"
                            >★</span>
                        </div>
                    </div>

                    <p class="mt-3 text-sm text-slate-500">{{ csat?.total_responses ?? 0 }} responses</p>

                    <div v-if="csat?.breakdown && Object.keys(csat.breakdown).length" class="mt-5 space-y-2.5">
                        <div v-for="rating in [5, 4, 3, 2, 1]" :key="rating" class="flex items-center gap-3">
                            <span class="w-3 text-xs font-medium text-slate-500">{{ rating }}</span>
                            <div class="h-2 flex-1 overflow-hidden rounded-full bg-slate-100">
                                <div
                                    class="h-full rounded-full bg-gradient-to-r from-amber-400 to-amber-500 transition-all duration-500"
                                    :style="{ width: `${csat.total_responses ? ((csat.breakdown[rating] ?? 0) / csat.total_responses) * 100 : 0}%` }"
                                />
                            </div>
                            <span class="w-5 text-right text-xs tabular-nums text-slate-400">{{ csat.breakdown[rating] ?? 0 }}</span>
                        </div>
                    </div>
                    <div v-else class="mt-6 rounded-xl border border-dashed border-slate-200 px-4 py-8 text-center text-sm text-slate-400">
                        {{ $t('dashboard.no_csat_responses_yet') }}
                    </div>
                </div>

                <div class="rounded-2xl border border-white/60 bg-white/90 p-6 shadow-sm ring-1 ring-slate-100 backdrop-blur lg:col-span-4">
                    <h2 class="text-base font-semibold text-slate-900">{{ $t('dashboard.agent_workload') }}</h2>
                    <p class="mt-0.5 text-sm text-slate-500">{{ $t('dashboard.open_tickets_by_assignee') }}</p>

                    <ul class="mt-5 space-y-4">
                        <li v-for="agent in topAgents" :key="agent.agent_id" class="flex items-center gap-3">
                            <AppAvatar :name="agent.agent_name" size="sm" />
                            <div class="min-w-0 flex-1">
                                <div class="flex items-center justify-between gap-2">
                                    <span class="truncate text-sm font-medium text-slate-800">{{ agent.agent_name }}</span>
                                    <span class="shrink-0 rounded-full bg-slate-100 px-2 py-0.5 text-xs font-semibold tabular-nums text-slate-700">{{ agent.open_count }}</span>
                                </div>
                                <div class="mt-1.5 h-1.5 overflow-hidden rounded-full bg-slate-100">
                                    <div
                                        class="h-full rounded-full bg-gradient-to-r from-blue-500 to-violet-500 transition-all duration-500"
                                        :style="{ width: `${(agent.open_count / maxAgentCount) * 100}%` }"
                                    />
                                </div>
                            </div>
                        </li>
                        <li v-if="!topAgents?.length" class="rounded-xl border border-dashed border-slate-200 px-4 py-8 text-center text-sm text-slate-400">
                            {{ $t('dashboard.no_assigned_open_tickets') }}
                        </li>
                    </ul>
                </div>
            </div>

            <div class="grid gap-6 lg:grid-cols-2">
                <div class="rounded-2xl border border-white/60 bg-white/90 p-6 shadow-sm ring-1 ring-slate-100 backdrop-blur">
                    <div class="flex items-center justify-between gap-4">
                        <div>
                            <h2 class="text-base font-semibold text-slate-900">{{ $t('dashboard.tickets_by_status') }}</h2>
                            <p class="mt-0.5 text-sm text-slate-500">{{ $t('dashboard.all_non-merged_tickets') }}</p>
                        </div>
                        <Link href="/tickets" class="text-xs font-semibold text-blue-600 hover:text-blue-700">{{ $t('dashboard.view_all') }}</Link>
                    </div>

                    <div class="mt-5 space-y-3">
                        <div
                            v-for="status in ticketStatuses"
                            :key="status.id"
                            class="group rounded-xl border border-slate-100 bg-slate-50/50 p-4 transition hover:border-slate-200 hover:bg-white"
                        >
                            <div class="flex items-center justify-between gap-3">
                                <div class="flex items-center gap-2.5">
                                    <span
                                        class="h-2.5 w-2.5 shrink-0 rounded-full ring-2 ring-white"
                                        :style="{ backgroundColor: statusTone(status).dot ?? status.color }"
                                    />
                                    <span class="text-sm font-medium text-slate-700">{{ status.name }}</span>
                                </div>
                                <span class="text-xl font-bold tabular-nums text-slate-900">{{ status.tickets_count }}</span>
                            </div>
                            <div class="mt-3 h-1.5 overflow-hidden rounded-full bg-slate-200/60">
                                <div
                                    class="h-full rounded-full transition-all duration-500"
                                    :class="statusTone(status).bar"
                                    :style="status.color && !statusTone(status).bar ? { backgroundColor: status.color, width: `${(status.tickets_count / maxStatusCount) * 100}%` } : { width: `${(status.tickets_count / maxStatusCount) * 100}%` }"
                                />
                            </div>
                        </div>
                    </div>
                </div>

                <div class="rounded-2xl border border-white/60 bg-white/90 p-6 shadow-sm ring-1 ring-slate-100 backdrop-blur">
                    <div class="flex items-center justify-between gap-4">
                        <div>
                            <h2 class="text-base font-semibold text-slate-900">{{ $t('dashboard.open_by_priority') }}</h2>
                            <p class="mt-0.5 text-sm text-slate-500">{{ $t('dashboard.active_ticket_queue_breakdown') }}</p>
                        </div>
                        <Link href="/workspace" class="text-xs font-semibold text-blue-600 hover:text-blue-700">Open queue →</Link>
                    </div>

                    <div class="mt-5 grid gap-3 sm:grid-cols-2">
                        <div
                            v-for="priority in ticketPriorities"
                            :key="priority.id"
                            class="relative overflow-hidden rounded-xl border border-slate-100 p-4 transition hover:shadow-md"
                        >
                            <div
                                class="absolute inset-x-0 top-0 h-1"
                                :class="priorityTone(priority.slug).bar"
                            />
                            <div class="flex items-start justify-between gap-2 pt-1">
                                <span class="rounded-lg px-2 py-0.5 text-xs font-semibold uppercase tracking-wide" :class="priorityTone(priority.slug).badge">
                                    {{ priority.name }}
                                </span>
                                <span class="text-2xl font-bold tabular-nums text-slate-900">{{ priority.tickets_count }}</span>
                            </div>
                            <div class="mt-3 h-1.5 overflow-hidden rounded-full bg-slate-100">
                                <div
                                    class="h-full rounded-full transition-all duration-500"
                                    :class="priorityTone(priority.slug).bar"
                                    :style="{ width: `${maxPriorityCount ? (priority.tickets_count / maxPriorityCount) * 100 : 0}%` }"
                                />
                            </div>
                        </div>
                    </div>
                </div>

                <div class="rounded-2xl border border-white/60 bg-white/90 p-6 shadow-sm ring-1 ring-slate-100 backdrop-blur lg:col-span-3">
                    <h2 class="text-base font-semibold text-slate-900">{{ $t('dashboard.kb_deflection') }}</h2>
                    <p class="mt-0.5 text-sm text-slate-500">{{ $t('dashboard.last_30_days_portal_submit') }}</p>

                    <div class="mt-6 flex items-end gap-6">
                        <div>
                            <p class="text-4xl font-bold tracking-tight text-slate-900">{{ kbDeflection?.suggestions_shown ?? 0 }}</p>
                            <p class="mt-1 text-sm text-slate-500">{{ $t('dashboard.suggestion_views') }}</p>
                        </div>
                        <div>
                            <p class="text-3xl font-bold tracking-tight text-emerald-600">{{ kbDeflection?.deflection_rate ?? '—' }}<span v-if="kbDeflection?.deflection_rate != null" class="text-lg">%</span></p>
                            <p class="mt-1 text-sm text-slate-500">{{ $t('dashboard.deflection_rate') }}</p>
                        </div>
                    </div>

                    <p class="mt-4 text-sm text-slate-500">
                        {{ kbDeflection?.deflected ?? 0 }} resolved without ticket
                        · {{ kbDeflection?.tickets_created ?? 0 }} still submitted
                    </p>
                </div>

                <div class="rounded-2xl border border-white/60 bg-white/90 p-6 shadow-sm ring-1 ring-slate-100 backdrop-blur lg:col-span-3">
                    <h2 class="text-base font-semibold text-slate-900">{{ $t('dashboard.ai_deflection') }}</h2>
                    <p class="mt-0.5 text-sm text-slate-500">{{ $t('dashboard.last_30_days') }}</p>

                    <div class="mt-6 flex items-end gap-6">
                        <div>
                            <p class="text-4xl font-bold tracking-tight text-slate-900">{{ deflection?.queries ?? 0 }}</p>
                            <p class="mt-1 text-sm text-slate-500">{{ $t('dashboard.bot_queries') }}</p>
                        </div>
                        <div>
                            <p class="text-3xl font-bold tracking-tight text-emerald-600">{{ deflection?.deflection_rate ?? '—' }}<span v-if="deflection?.deflection_rate != null" class="text-lg">%</span></p>
                            <p class="mt-1 text-sm text-slate-500">{{ $t('dashboard.marked_helpful') }}</p>
                        </div>
                    </div>

                    <p class="mt-4 text-sm text-slate-500">
                        {{ deflection?.tickets_created ?? 0 }} escalated to tickets
                        <span v-if="deflection?.by_channel?.portal != null"> · {{ deflection.by_channel.portal ?? 0 }} portal / {{ deflection.by_channel.widget ?? 0 }} widget</span>
                    </p>
                </div>
            </div>
        </div>
    </AgentLayout>
</template>
