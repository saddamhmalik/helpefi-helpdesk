<script setup>
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { computed, onMounted, onUnmounted, ref } from 'vue';
import AgentLayout from '../../Layouts/AgentLayout.vue';
import AppAvatar from '../../Components/AppAvatar.vue';
import AppBadge from '../../Components/ui/AppBadge.vue';
import AppButton from '../../Components/ui/AppButton.vue';
import AppTabs from '../../Components/AppTabs.vue';
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
const userName = computed(() => page.props.auth?.user?.name?.split(' ')[0] ?? t('dashboard.greeting_fallback'));
const isAdmin = computed(() => page.props.auth?.user?.is_admin ?? false);
const deflectionTab = ref('kb');

const dashboardProps = [
    'stats',
    'csat',
    'ticketStatuses',
    'ticketPriorities',
    'topAgents',
    'volumeTrend',
    'deflection',
    'kbDeflection',
];

const reloadDashboard = () => {
    router.reload({
        only: dashboardProps,
        preserveScroll: true,
    });
};

let visibilityHandler = null;

onMounted(() => {
    visibilityHandler = () => {
        if (document.visibilityState === 'visible') {
            reloadDashboard();
        }
    };

    document.addEventListener('visibilitychange', visibilityHandler);
});

onUnmounted(() => {
    if (visibilityHandler) {
        document.removeEventListener('visibilitychange', visibilityHandler);
    }
});

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
        href: '/workspace',
        tone: 'blue',
        icon: 'ticket',
        primary: true,
    },
    {
        label: t('dashboard.sla_breaches'),
        value: props.stats.slaBreaches,
        href: '/reports?type=sla_breaches&run=1',
        tone: props.stats.slaBreaches ? 'red' : 'slate',
        icon: 'alert',
        alert: props.stats.slaBreaches > 0,
        primary: true,
    },
    {
        label: t('dashboard.created_this_week'),
        value: props.stats.createdThisWeek,
        href: '/reports?type=tickets&run=1',
        tone: 'violet',
        icon: 'plus',
        primary: true,
    },
    {
        label: t('dashboard.resolved_this_week'),
        value: props.stats.resolvedThisWeek,
        href: '/reports?type=tickets&run=1',
        tone: 'emerald',
        icon: 'check',
        primary: true,
    },
    {
        label: t('dashboard.contacts'),
        value: props.stats.contacts,
        href: '/contacts',
        tone: 'cyan',
        icon: 'users',
        primary: false,
    },
    {
        label: t('dashboard.articles'),
        value: props.stats.publishedArticles,
        href: '/knowledge',
        tone: 'amber',
        icon: 'book',
        primary: false,
    },
]);

const primaryStatCards = computed(() => statCards.value.filter((card) => card.primary));
const secondaryStatCards = computed(() => statCards.value.filter((card) => !card.primary));

const quickActions = computed(() => {
    const actions = [
        { href: '/workspace', label: t('dashboard.open_inbox'), variant: 'primary' },
        { href: '/tickets/create', label: t('dashboard.new_ticket'), variant: 'secondary' },
        { href: '/reports', label: t('dashboard.reports_link'), variant: 'secondary' },
    ];

    if (isAdmin.value) {
        actions.push({ href: '/growth', label: t('growth.page_title'), variant: 'secondary' });
    }

    return actions;
});

const deflectionTabs = computed(() => [
    { id: 'kb', label: t('dashboard.kb_deflection') },
    { id: 'ai', label: t('dashboard.ai_deflection') },
]);

const toneClasses = {
    blue: { bg: 'bg-blue-50 dark:bg-blue-950/40 dark:bg-blue-500/10', icon: 'bg-blue-500/10 text-blue-600 dark:text-blue-400', ring: 'ring-blue-100 dark:ring-blue-900/40' },
    violet: { bg: 'bg-violet-50 dark:bg-violet-950/40 dark:bg-violet-500/10', icon: 'bg-violet-500/10 text-violet-600 dark:text-violet-400', ring: 'ring-violet-100 dark:ring-violet-900/40' },
    emerald: { bg: 'bg-emerald-50 dark:bg-emerald-950/40 dark:bg-emerald-500/10', icon: 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400', ring: 'ring-emerald-100 dark:ring-emerald-900/40' },
    red: { bg: 'bg-red-50 dark:bg-red-950/40 dark:bg-red-500/10', icon: 'bg-red-500/10 text-red-600 dark:text-red-400', ring: 'ring-red-100 dark:ring-red-900/40' },
    slate: { bg: 'bg-slate-50 dark:bg-slate-950 dark:bg-slate-500/10', icon: 'bg-slate-500/10 text-slate-600 dark:text-slate-400', ring: 'ring-slate-100 dark:ring-slate-800' },
    cyan: { bg: 'bg-cyan-50 dark:bg-cyan-500/10', icon: 'bg-cyan-500/10 text-cyan-600 dark:text-cyan-400', ring: 'ring-cyan-100 dark:ring-cyan-900/40' },
    amber: { bg: 'bg-amber-50 dark:bg-amber-950/40 dark:bg-amber-500/10', icon: 'bg-amber-500/10 text-amber-600 dark:text-amber-400', ring: 'ring-amber-100 dark:ring-amber-900/40' },
};

const priorityTone = (slug) => ({
    low: { bar: 'bg-slate-400', variant: 'default' },
    normal: { bar: 'bg-blue-500', variant: 'info' },
    high: { bar: 'bg-orange-500', variant: 'warning' },
    urgent: { bar: 'bg-red-500', variant: 'error' },
}[slug] ?? { bar: 'bg-slate-400', variant: 'default' });

const statusTone = (status) => {
    if (status.color) {
        return { bar: '', badge: 'text-slate-700 dark:text-slate-300', dot: status.color };
    }

    const slug = (status.slug ?? '').toLowerCase();

    return {
        open: { bar: 'bg-emerald-500', badge: 'bg-emerald-50 dark:bg-emerald-950/40 text-emerald-700 dark:bg-emerald-950/50 dark:text-emerald-300', dot: '#10b981' },
        pending: { bar: 'bg-amber-500', badge: 'bg-amber-50 dark:bg-amber-950/40 text-amber-700 dark:bg-amber-950/50 dark:text-amber-300', dot: '#f59e0b' },
        resolved: { bar: 'bg-blue-500', badge: 'bg-blue-50 dark:bg-blue-950/40 text-blue-700 dark:bg-blue-950/50 dark:text-blue-300', dot: '#3b82f6' },
        closed: { bar: 'bg-slate-400', badge: 'bg-slate-100 dark:bg-slate-900 text-slate-600 dark:bg-slate-800 dark:text-slate-400', dot: '#94a3b8' },
    }[slug] ?? { bar: 'bg-slate-400', badge: 'bg-slate-100 dark:bg-slate-900 text-slate-700 dark:bg-slate-800 dark:text-slate-300', dot: '#94a3b8' };
};

const csatStars = computed(() => {
    const rating = Number(props.csat?.average_rating);
    if (!rating) return 0;

    return Math.round(rating);
});

const hasCsatData = computed(() => Boolean(props.csat?.average_rating) || (props.csat?.total_responses ?? 0) > 0);
const hasAgentWorkload = computed(() => (props.topAgents?.length ?? 0) > 0);
const openTicketTotal = computed(() => props.stats?.openTickets ?? 0);

const greeting = computed(() => {
    const hour = new Date().getHours();
    if (hour < 12) return t('dashboard.good_morning');
    if (hour < 17) return t('dashboard.good_afternoon');

    return t('dashboard.good_evening');
});
</script>

<template>
    <Head :title="$t('dashboard.dashboard')" />
    <AgentLayout>
        <div class="space-y-5 pb-8">
            <div class="relative overflow-hidden rounded-xl border agent-border agent-panel px-4 py-4 sm:px-5">
                <div class="pointer-events-none absolute inset-0 bg-gradient-to-r from-blue-600/[0.06] via-violet-500/[0.03] to-transparent dark:from-blue-500/[0.1]" />

                <div class="relative flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div class="min-w-0">
                        <h1 class="text-xl font-bold tracking-tight agent-text sm:text-2xl">{{ greeting }}, {{ userName }}</h1>
                        <p class="mt-0.5 text-sm agent-text-muted">{{ $t('dashboard.overview_description') }}</p>
                    </div>
                    <div class="flex shrink-0 flex-wrap items-center gap-2">
                        <AppButton href="/workspace" size="sm">
                            {{ $t('dashboard.open_inbox') }}
                        </AppButton>
                        <AppButton href="/tickets/create" variant="secondary" size="sm">
                            {{ $t('dashboard.new_ticket') }}
                        </AppButton>
                    </div>
                </div>
            </div>

            <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                <Link
                    v-for="card in primaryStatCards"
                    :key="card.label"
                    :href="card.href"
                    class="group relative overflow-hidden rounded-xl border agent-border agent-panel p-4 shadow-sm ring-1 backdrop-blur transition hover:-translate-y-0.5 hover:shadow-md dark:border-slate-700/60"
                    :class="toneClasses[card.tone].ring"
                >
                    <div class="absolute -right-4 -top-4 h-20 w-20 rounded-full opacity-60 blur-2xl transition group-hover:opacity-80" :class="toneClasses[card.tone].bg" />
                    <div class="relative flex items-start justify-between gap-3">
                        <div>
                            <p class="text-xs font-medium uppercase tracking-wide agent-text-subtle">{{ card.label }}</p>
                            <p class="mt-1.5 text-2xl font-bold tabular-nums tracking-tight sm:mt-2 sm:text-3xl"
                                :class="card.alert ? 'text-red-600' : card.tone === 'emerald' ? 'text-emerald-600' : 'agent-text'"
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

            <div class="flex flex-wrap gap-3">
                <Link
                    v-for="card in secondaryStatCards"
                    :key="card.label"
                    :href="card.href"
                    class="inline-flex items-center gap-2 rounded-full border agent-border agent-panel px-4 py-2 text-sm transition agent-hover-surface"
                >
                    <span class="agent-text-muted">{{ card.label }}</span>
                    <span class="font-semibold tabular-nums agent-text">{{ card.value }}</span>
                </Link>
            </div>

            <div class="grid gap-5 lg:grid-cols-2">
                <div class="agent-card">
                    <div class="flex items-center justify-between gap-4">
                        <div>
                            <h2 class="text-base font-semibold agent-text">{{ $t('dashboard.tickets_by_status') }}</h2>
                            <p class="mt-0.5 text-sm agent-text-subtle">{{ $t('dashboard.all_non-merged_tickets') }}</p>
                        </div>
                        <Link href="/tickets" class="text-xs font-semibold text-blue-600 hover:text-blue-700 dark:text-blue-300 dark:hover:text-blue-300">{{ $t('dashboard.view_all') }}</Link>
                    </div>

                    <div class="mt-4 space-y-2.5">
                        <div
                            v-for="status in ticketStatuses"
                            :key="status.id"
                            class="group agent-card-item agent-hover-surface"
                        >
                            <div class="flex items-center justify-between gap-3">
                                <div class="flex items-center gap-2.5">
                                    <span
                                        class="h-2.5 w-2.5 shrink-0 rounded-full ring-2 ring-white dark:ring-slate-900"
                                        :style="{ backgroundColor: statusTone(status).dot ?? status.color }"
                                    />
                                    <span class="text-sm font-medium text-slate-700 dark:text-slate-300">{{ status.name }}</span>
                                </div>
                                <span class="text-lg font-bold tabular-nums agent-text sm:text-xl">{{ status.tickets_count }}</span>
                            </div>
                            <div class="mt-2.5 h-1.5 overflow-hidden rounded-full bg-slate-200/60 dark:bg-slate-700/60">
                                <div
                                    class="h-full rounded-full transition-all duration-500"
                                    :class="statusTone(status).bar"
                                    :style="status.color && !statusTone(status).bar ? { backgroundColor: status.color, width: `${(status.tickets_count / maxStatusCount) * 100}%` } : { width: `${(status.tickets_count / maxStatusCount) * 100}%` }"
                                />
                            </div>
                        </div>
                    </div>
                </div>

                <div class="agent-card">
                    <div class="flex items-center justify-between gap-4">
                        <div>
                            <h2 class="text-base font-semibold agent-text">{{ $t('dashboard.open_by_priority') }}</h2>
                            <p class="mt-0.5 text-sm agent-text-subtle">{{ $t('dashboard.active_ticket_queue_breakdown') }}</p>
                        </div>
                        <Link href="/workspace" class="text-xs font-semibold text-blue-600 hover:text-blue-700 dark:text-blue-300 dark:hover:text-blue-300">{{ $t('dashboard.open_queue') }}</Link>
                    </div>

                    <div class="mt-4 grid gap-2.5 sm:grid-cols-2">
                        <div
                            v-for="priority in ticketPriorities"
                            :key="priority.id"
                            class="relative overflow-hidden rounded-xl border agent-border agent-panel p-3.5 transition hover:shadow-md dark:hover:shadow-slate-900/40"
                        >
                            <div
                                class="absolute inset-x-0 top-0 h-1"
                                :class="priorityTone(priority.slug).bar"
                            />
                            <div class="flex items-start justify-between gap-2 pt-0.5">
                                <AppBadge :variant="priorityTone(priority.slug).variant">{{ priority.name }}</AppBadge>
                                <span class="text-xl font-bold tabular-nums agent-text">{{ priority.tickets_count }}</span>
                            </div>
                            <div class="mt-2.5 h-1.5 overflow-hidden rounded-full bg-slate-100 dark:bg-slate-800">
                                <div
                                    class="h-full rounded-full transition-all duration-500"
                                    :class="priorityTone(priority.slug).bar"
                                    :style="{ width: `${maxPriorityCount ? (priority.tickets_count / maxPriorityCount) * 100 : 0}%` }"
                                />
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="grid gap-5 lg:grid-cols-12">
                <div class="agent-card lg:col-span-8">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <h2 class="text-base font-semibold agent-text">{{ $t('dashboard.ticket_volume') }}</h2>
                            <p class="mt-0.5 text-sm agent-text-subtle">{{ $t('dashboard.last_7_days') }}</p>
                        </div>
                        <div class="rounded-xl bg-blue-50 dark:bg-blue-950/40 px-3 py-1.5 text-right dark:bg-blue-950/50">
                            <p class="text-lg font-bold tabular-nums text-blue-700 dark:text-blue-300">{{ totalVolume }}</p>
                            <p class="text-[10px] font-medium uppercase tracking-wide text-blue-600/80 dark:text-blue-400/80">{{ $t('dashboard.total') }}</p>
                        </div>
                    </div>

                    <div class="relative mt-5 h-32">
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
                            <span v-for="day in volumeTrend" :key="day.date" class="text-[10px] font-medium text-slate-400 dark:text-slate-500">{{ day.label }}</span>
                        </div>
                    </div>

                    <div class="mt-4 flex flex-wrap gap-2">
                        <span
                            v-for="day in volumeTrend"
                            :key="`${day.date}-pill`"
                            class="inline-flex items-center gap-1.5 rounded-lg agent-panel-muted px-2.5 py-1 text-xs agent-text-muted"
                        >
                            <span class="font-semibold agent-text">{{ day.count }}</span>
                            {{ day.label }}
                        </span>
                    </div>
                </div>

                <div class="agent-card lg:col-span-4">
                    <h2 class="text-base font-semibold agent-text">{{ $t('dashboard.quick_actions') }}</h2>
                    <p class="mt-0.5 text-sm agent-text-subtle">{{ $t('dashboard.jump_back_into_work') }}</p>
                    <div class="mt-5 space-y-2">
                        <AppButton
                            v-for="action in quickActions"
                            :key="action.href"
                            :href="action.href"
                            :variant="action.variant"
                            block
                        >
                            {{ action.label }}
                        </AppButton>
                    </div>
                </div>

                <div class="agent-card lg:col-span-6">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <h2 class="text-base font-semibold agent-text">{{ $t('dashboard.csat_score') }}</h2>
                            <p class="mt-0.5 text-sm agent-text-subtle">{{ $t('dashboard.last_30_days') }}</p>
                        </div>
                        <p class="text-xs tabular-nums agent-text-subtle">{{ $t('dashboard.responses', { count: csat?.total_responses ?? 0 }) }}</p>
                    </div>

                    <template v-if="hasCsatData">
                        <div class="mt-4 flex items-end gap-3">
                            <p class="text-3xl font-bold tracking-tight agent-text">{{ csat?.average_rating ?? '—' }}</p>
                            <div v-if="csat?.average_rating" class="mb-1 flex gap-0.5">
                                <span
                                    v-for="star in 5"
                                    :key="star"
                                    class="text-base"
                                    :class="star <= csatStars ? 'text-amber-400' : 'text-slate-200 dark:text-slate-700'"
                                >★</span>
                            </div>
                        </div>

                        <div v-if="csat?.breakdown && Object.keys(csat.breakdown).length" class="mt-4 space-y-2">
                            <div v-for="rating in [5, 4, 3, 2, 1]" :key="rating" class="flex items-center gap-2.5">
                                <span class="w-3 text-xs font-medium agent-text-subtle">{{ rating }}</span>
                                <div class="h-1.5 flex-1 overflow-hidden rounded-full bg-slate-100 dark:bg-slate-800">
                                    <div
                                        class="h-full rounded-full bg-gradient-to-r from-amber-400 to-amber-500 transition-all duration-500"
                                        :style="{ width: `${csat.total_responses ? ((csat.breakdown[rating] ?? 0) / csat.total_responses) * 100 : 0}%` }"
                                    />
                                </div>
                                <span class="w-5 text-right text-xs tabular-nums text-slate-400">{{ csat.breakdown[rating] ?? 0 }}</span>
                            </div>
                        </div>
                    </template>

                    <div v-else class="mt-4 flex items-center gap-3 rounded-lg agent-panel-muted px-3 py-3">
                        <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-amber-500/10 text-amber-500">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z" />
                            </svg>
                        </span>
                        <p class="text-sm agent-text-subtle">{{ $t('dashboard.no_csat_responses_yet') }}</p>
                    </div>
                </div>

                <div class="agent-card lg:col-span-6">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <h2 class="text-base font-semibold agent-text">{{ $t('dashboard.agent_workload') }}</h2>
                            <p class="mt-0.5 text-sm agent-text-subtle">{{ $t('dashboard.open_tickets_by_assignee') }}</p>
                        </div>
                        <Link v-if="openTicketTotal > 0" href="/workspace" class="text-xs font-semibold text-blue-600 hover:text-blue-700 dark:text-blue-300">{{ $t('dashboard.open_queue') }}</Link>
                    </div>

                    <ul v-if="hasAgentWorkload" class="mt-4 space-y-3">
                        <li v-for="agent in topAgents" :key="agent.agent_id" class="flex items-center gap-3">
                            <AppAvatar :name="agent.agent_name" size="sm" />
                            <div class="min-w-0 flex-1">
                                <div class="flex items-center justify-between gap-2">
                                    <span class="truncate text-sm font-medium text-slate-800 dark:text-slate-200">{{ agent.agent_name }}</span>
                                    <span class="shrink-0 rounded-full agent-panel-muted px-2 py-0.5 text-xs font-semibold tabular-nums text-slate-700 dark:text-slate-300">{{ agent.open_count }}</span>
                                </div>
                                <div class="mt-1.5 h-1.5 overflow-hidden rounded-full bg-slate-100 dark:bg-slate-800">
                                    <div
                                        class="h-full rounded-full bg-gradient-to-r from-blue-500 to-violet-500 transition-all duration-500"
                                        :style="{ width: `${(agent.open_count / maxAgentCount) * 100}%` }"
                                    />
                                </div>
                            </div>
                        </li>
                    </ul>

                    <div v-else class="mt-4 flex items-center gap-3 rounded-lg agent-panel-muted px-3 py-3">
                        <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-blue-500/10 text-blue-600 dark:text-blue-400">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z" />
                            </svg>
                        </span>
                        <div class="min-w-0">
                            <p class="text-sm agent-text-subtle">{{ $t('dashboard.no_assigned_open_tickets') }}</p>
                            <Link v-if="openTicketTotal > 0" href="/workspace" class="mt-0.5 inline-block text-xs font-semibold text-blue-600 hover:text-blue-700 dark:text-blue-300">
                                {{ $t('dashboard.open_inbox') }}
                            </Link>
                        </div>
                    </div>
                </div>
            </div>

            <div class="agent-card">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h2 class="text-base font-semibold agent-text">{{ $t('dashboard.deflection_overview') }}</h2>
                        <p class="mt-0.5 text-sm agent-text-subtle">{{ $t('dashboard.last_30_days') }}</p>
                    </div>
                    <Link
                        v-if="isAdmin"
                        href="/growth?tab=deflection"
                        class="text-xs font-medium text-blue-600 hover:underline dark:text-blue-400"
                    >
                        {{ $t('growth.page_title') }}
                    </Link>
                </div>

                <AppTabs v-model="deflectionTab" class="mt-4" variant="pills" :items="deflectionTabs" />

                <div v-if="deflectionTab === 'kb'" class="mt-5 grid gap-5 sm:grid-cols-3">
                    <div>
                        <p class="text-2xl font-bold tabular-nums agent-text sm:text-3xl">{{ kbDeflection?.suggestions_shown ?? 0 }}</p>
                        <p class="mt-1 text-sm agent-text-subtle">{{ $t('dashboard.suggestion_views') }}</p>
                    </div>
                    <div>
                        <p class="text-2xl font-bold tabular-nums text-emerald-600 dark:text-emerald-400 sm:text-3xl">
                            {{ kbDeflection?.deflection_rate ?? '—' }}<span v-if="kbDeflection?.deflection_rate != null" class="text-lg">%</span>
                        </p>
                        <p class="mt-1 text-sm agent-text-subtle">{{ $t('dashboard.deflection_rate') }}</p>
                    </div>
                    <div class="text-sm agent-text-muted">
                        <p>{{ $t('dashboard.kb_resolved_without_ticket', { count: kbDeflection?.deflected ?? 0 }) }}</p>
                        <p class="mt-1">{{ $t('dashboard.kb_still_submitted', { count: kbDeflection?.tickets_created ?? 0 }) }}</p>
                    </div>
                </div>

                <div v-else class="mt-5 grid gap-5 sm:grid-cols-3">
                    <div>
                        <p class="text-2xl font-bold tabular-nums agent-text sm:text-3xl">{{ deflection?.queries ?? 0 }}</p>
                        <p class="mt-1 text-sm agent-text-subtle">{{ $t('dashboard.bot_queries') }}</p>
                    </div>
                    <div>
                        <p class="text-2xl font-bold tabular-nums text-emerald-600 dark:text-emerald-400 sm:text-3xl">
                            {{ deflection?.deflection_rate ?? '—' }}<span v-if="deflection?.deflection_rate != null" class="text-lg">%</span>
                        </p>
                        <p class="mt-1 text-sm agent-text-subtle">{{ $t('dashboard.marked_helpful') }}</p>
                    </div>
                    <div class="text-sm agent-text-muted">
                        <p>{{ $t('dashboard.escalated_to_tickets', { count: deflection?.tickets_created ?? 0 }) }}</p>
                        <p v-if="deflection?.by_channel?.portal != null" class="mt-1">
                            {{ $t('dashboard.portal_widget_split', { portal: deflection.by_channel.portal ?? 0, widget: deflection.by_channel.widget ?? 0 }) }}
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </AgentLayout>
</template>
