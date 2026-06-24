<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import AgentLayout from '../../Layouts/AgentLayout.vue';
import PageHeader from '../../Components/PageHeader.vue';
import { useI18n } from 'vue-i18n';
import { useDateTime } from '../../composables/useDateTime.js';

const props = defineProps({
    hub: Object,
    activeTab: { type: String, default: 'overview' },
});

const { t } = useI18n();
const { formatDate, formatDateTime } = useDateTime();

const tab = ref(props.activeTab ?? 'overview');

const tabs = computed(() => [
    { id: 'overview', label: t('growth.tabs.overview') },
    { id: 'health', label: t('growth.tabs.health') },
    { id: 'ai', label: t('growth.tabs.ai') },
    { id: 'deflection', label: t('growth.tabs.deflection') },
]);

const billing = computed(() => props.hub?.billing ?? {});
const engagement = computed(() => props.hub?.engagement ?? {});
const setupHealth = computed(() => props.hub?.setup_health ?? {});
const aiUsage = computed(() => props.hub?.ai_usage ?? {});
const aiDeflection = computed(() => props.hub?.ai_deflection ?? {});
const kbDeflection = computed(() => props.hub?.kb_deflection ?? {});
const filters = computed(() => props.hub?.deflection_filters ?? {});

const dateFrom = ref(filters.value.date_from ?? '');
const dateTo = ref(filters.value.date_to ?? '');

const switchTab = (id) => {
    tab.value = id;
    router.get('/growth', { tab: id, date_from: dateFrom.value, date_to: dateTo.value }, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
};

const applyDateFilter = () => {
    router.get('/growth', {
        tab: tab.value,
        date_from: dateFrom.value || undefined,
        date_to: dateTo.value || undefined,
    }, { preserveScroll: true });
};

const statusTone = (status) => ({
    healthy: 'bg-emerald-50 text-emerald-800 ring-emerald-200 dark:bg-emerald-950/40 dark:text-emerald-300 dark:ring-emerald-900/50',
    warning: 'bg-amber-50 text-amber-900 ring-amber-200 dark:bg-amber-950/40 dark:text-amber-200 dark:ring-amber-900/50',
    error: 'bg-red-50 text-red-800 ring-red-200 dark:bg-red-950/40 dark:text-red-300 dark:ring-red-900/50',
}[status] ?? 'bg-slate-50 text-slate-700 ring-slate-200 dark:bg-slate-800 dark:text-slate-300 dark:ring-slate-700');

const formatPercent = (value) => (value === null || value === undefined ? '—' : `${value}%`);

const maxTrend = computed(() => Math.max(...(aiUsage.value.trend?.map((d) => d.count) ?? [1]), 1));

const setupProgress = computed(() => {
    const progress = setupHealth.value.setup?.progress;

    if (!progress?.total) {
        return 0;
    }

    return Math.round((progress.completed / progress.total) * 100);
});

const trialMilestones = computed(() => [
    {
        key: 'channel',
        label: t('growth.milestones.channel'),
        done: (setupHealth.value.checks ?? []).some((c) => c.key === 'email_inbound' && c.status === 'healthy'),
    },
    {
        key: 'first_ticket',
        label: t('growth.milestones.first_ticket'),
        done: Boolean(engagement.value.first_ticket_at),
    },
    {
        key: 'first_reply',
        label: t('growth.milestones.first_reply'),
        done: Boolean(engagement.value.first_reply_at),
    },
    {
        key: 'team',
        label: t('growth.milestones.team'),
        done: (engagement.value.team_members ?? 0) > 1,
    },
    {
        key: 'kb',
        label: t('growth.milestones.kb'),
        done: (engagement.value.published_articles ?? 0) > 0,
    },
]);
</script>

<template>
    <AgentLayout>
        <Head :title="t('growth.page_title')" />

        <div class="flex min-h-0 flex-1 flex-col overflow-y-auto">
            <div class="mx-auto w-full max-w-6xl px-4 py-6 sm:px-6">
                <PageHeader
                    :title="t('growth.page_title')"
                    :description="t('growth.page_description')"
                />

                <div class="mt-6 flex flex-wrap gap-2 border-b agent-border pb-3">
                    <button
                        v-for="item in tabs"
                        :key="item.id"
                        type="button"
                        class="rounded-lg px-3 py-1.5 text-sm font-medium transition-ui"
                        :class="tab === item.id
                            ? 'bg-slate-900 text-white dark:bg-slate-100 dark:text-slate-900'
                            : 'agent-text-muted hover:bg-slate-100 dark:hover:bg-slate-800'"
                        @click="switchTab(item.id)"
                    >
                        {{ item.label }}
                    </button>
                </div>

                <div v-if="tab === 'overview'" class="mt-6 space-y-6">
                    <div
                        v-if="billing.on_trial"
                        class="rounded-2xl border border-blue-200/80 bg-gradient-to-br from-blue-50 to-indigo-50 p-6 dark:border-blue-900/40 dark:from-blue-950/30 dark:to-indigo-950/20"
                    >
                        <div class="flex flex-wrap items-start justify-between gap-4">
                            <div>
                                <p class="text-xs font-semibold uppercase tracking-wide text-blue-700 dark:text-blue-300">
                                    {{ t('growth.trial_status') }}
                                </p>
                                <h2 class="mt-1 text-2xl font-bold text-slate-900 dark:text-slate-100">
                                    {{ t('growth.trial_days_left', { days: billing.trial_days_remaining }) }}
                                </h2>
                                <p class="mt-2 text-sm text-slate-600 dark:text-slate-400">
                                    {{ t('growth.trial_plan', { plan: billing.plan?.name }) }}
                                    <span v-if="billing.trial_ends_at"> · {{ t('growth.trial_ends', { date: formatDate(billing.trial_ends_at) }) }}</span>
                                </p>
                            </div>
                            <Link
                                href="/settings/billing?section=plans"
                                class="inline-flex items-center rounded-xl bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700"
                            >
                                {{ t('growth.upgrade_cta') }}
                            </Link>
                        </div>

                        <div class="mt-6 grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
                            <div
                                v-for="milestone in trialMilestones"
                                :key="milestone.key"
                                class="flex items-center gap-2 rounded-xl bg-white/70 px-3 py-2 text-sm dark:bg-slate-900/50"
                            >
                                <span
                                    class="flex h-5 w-5 shrink-0 items-center justify-center rounded-full text-xs font-bold"
                                    :class="milestone.done ? 'bg-emerald-600 text-white' : 'bg-slate-200 text-slate-500 dark:bg-slate-700 dark:text-slate-400'"
                                >
                                    {{ milestone.done ? '✓' : '·' }}
                                </span>
                                <span :class="milestone.done ? 'text-slate-900 dark:text-slate-100' : 'text-slate-500 dark:text-slate-400'">
                                    {{ milestone.label }}
                                </span>
                            </div>
                        </div>
                    </div>

                    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                        <div class="rounded-2xl border agent-border agent-panel p-4">
                            <p class="text-xs font-medium uppercase tracking-wide agent-text-muted">{{ t('growth.stats.setup') }}</p>
                            <p class="mt-2 text-3xl font-bold">{{ setupProgress }}%</p>
                            <p class="mt-1 text-sm agent-text-muted">{{ t('growth.stats.setup_hint') }}</p>
                        </div>
                        <div class="rounded-2xl border agent-border agent-panel p-4">
                            <p class="text-xs font-medium uppercase tracking-wide agent-text-muted">{{ t('growth.stats.tickets_30d') }}</p>
                            <p class="mt-2 text-3xl font-bold">{{ engagement.tickets_30d ?? 0 }}</p>
                        </div>
                        <div class="rounded-2xl border agent-border agent-panel p-4">
                            <p class="text-xs font-medium uppercase tracking-wide agent-text-muted">{{ t('growth.stats.replies_30d') }}</p>
                            <p class="mt-2 text-3xl font-bold">{{ engagement.agent_replies_30d ?? 0 }}</p>
                        </div>
                        <div class="rounded-2xl border agent-border agent-panel p-4">
                            <p class="text-xs font-medium uppercase tracking-wide agent-text-muted">{{ t('growth.stats.team') }}</p>
                            <p class="mt-2 text-3xl font-bold">{{ engagement.team_members ?? 0 }}</p>
                        </div>
                    </div>

                    <div class="rounded-2xl border agent-border agent-panel p-5">
                        <div class="flex items-center justify-between gap-3">
                            <h3 class="text-sm font-semibold">{{ t('growth.usage_limits') }}</h3>
                            <Link href="/settings/billing" class="text-sm font-medium text-blue-600 hover:underline dark:text-blue-400">
                                {{ t('growth.view_billing') }}
                            </Link>
                        </div>
                        <div class="mt-4 grid gap-4 sm:grid-cols-2">
                            <div v-for="limitKey in ['agents', 'tickets_monthly']" :key="limitKey">
                                <div class="flex justify-between text-sm">
                                    <span>{{ limitKey === 'agents' ? t('growth.limit_agents') : t('growth.limit_tickets') }}</span>
                                    <span class="font-medium">
                                        {{ billing.usage?.[limitKey] ?? 0 }}
                                        /
                                        {{ billing.limits?.[limitKey] === 'unlimited' ? '∞' : billing.limits?.[limitKey] }}
                                    </span>
                                </div>
                                <div class="mt-2 h-2 overflow-hidden rounded-full bg-slate-100 dark:bg-slate-800">
                                    <div
                                        class="h-full rounded-full bg-blue-600 transition-all"
                                        :style="{ width: billing.limits?.[limitKey] && billing.limits[limitKey] !== 'unlimited'
                                            ? `${Math.min(100, ((billing.usage?.[limitKey] ?? 0) / billing.limits[limitKey]) * 100)}%`
                                            : '0%' }"
                                    />
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div v-else-if="tab === 'health'" class="mt-6 space-y-4">
                    <div class="flex items-center gap-3">
                        <span
                            class="inline-flex rounded-full px-3 py-1 text-xs font-semibold ring-1 ring-inset"
                            :class="statusTone(setupHealth.overall)"
                        >
                            {{ t(`growth.health_status.${setupHealth.overall ?? 'healthy'}`) }}
                        </span>
                        <span class="text-sm agent-text-muted">
                            {{ t('growth.health_summary', { issues: setupHealth.issue_count ?? 0, warnings: setupHealth.warning_count ?? 0 }) }}
                        </span>
                    </div>

                    <div class="grid gap-3">
                        <div
                            v-for="check in setupHealth.checks ?? []"
                            :key="check.key"
                            class="flex flex-wrap items-center justify-between gap-3 rounded-2xl border agent-border agent-panel p-4"
                        >
                            <div class="min-w-0">
                                <div class="flex items-center gap-2">
                                    <span
                                        class="inline-flex rounded-full px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide ring-1 ring-inset"
                                        :class="statusTone(check.status)"
                                    >
                                        {{ check.status }}
                                    </span>
                                    <p class="font-medium">{{ check.label }}</p>
                                </div>
                                <p class="mt-1 text-sm agent-text-muted">{{ check.message }}</p>
                            </div>
                            <Link :href="check.url" class="text-sm font-medium text-blue-600 hover:underline dark:text-blue-400">
                                {{ t('growth.fix_issue') }}
                            </Link>
                        </div>
                    </div>

                    <div class="rounded-2xl border agent-border agent-panel p-5">
                        <h3 class="text-sm font-semibold">{{ t('growth.setup_steps') }}</h3>
                        <div class="mt-4 space-y-2">
                            <div
                                v-for="step in setupHealth.setup?.steps ?? []"
                                :key="step.key"
                                class="flex items-center justify-between gap-3 rounded-xl px-3 py-2"
                                :class="step.complete ? 'bg-emerald-50/80 dark:bg-emerald-950/20' : 'bg-slate-50 dark:bg-slate-800/50'"
                            >
                                <div class="flex items-center gap-2">
                                    <span class="text-sm">{{ step.complete ? '✓' : '○' }}</span>
                                    <span class="text-sm font-medium">{{ step.title }}</span>
                                    <span v-if="!step.required" class="text-xs agent-text-muted">({{ t('growth.optional') }})</span>
                                </div>
                                <Link :href="step.url" class="text-xs font-medium text-blue-600 hover:underline dark:text-blue-400">
                                    {{ step.complete ? t('growth.review') : t('growth.complete') }}
                                </Link>
                            </div>
                        </div>
                        <Link
                            v-if="!setupHealth.setup?.completed"
                            href="/setup"
                            class="mt-4 inline-flex text-sm font-semibold text-blue-600 hover:underline dark:text-blue-400"
                        >
                            {{ t('growth.open_setup_wizard') }}
                        </Link>
                    </div>
                </div>

                <div v-else-if="tab === 'ai'" class="mt-6 space-y-6">
                    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                        <div class="rounded-2xl border agent-border agent-panel p-4">
                            <p class="text-xs font-medium uppercase tracking-wide agent-text-muted">{{ t('growth.ai.user_messages') }}</p>
                            <p class="mt-2 text-3xl font-bold">{{ aiUsage.user_messages ?? 0 }}</p>
                        </div>
                        <div class="rounded-2xl border agent-border agent-panel p-4">
                            <p class="text-xs font-medium uppercase tracking-wide agent-text-muted">{{ t('growth.ai.assistant_messages') }}</p>
                            <p class="mt-2 text-3xl font-bold">{{ aiUsage.assistant_messages ?? 0 }}</p>
                        </div>
                        <div class="rounded-2xl border agent-border agent-panel p-4">
                            <p class="text-xs font-medium uppercase tracking-wide agent-text-muted">{{ t('growth.ai.agents') }}</p>
                            <p class="mt-2 text-3xl font-bold">{{ aiUsage.unique_agents ?? 0 }}</p>
                        </div>
                        <div class="rounded-2xl border agent-border agent-panel p-4">
                            <p class="text-xs font-medium uppercase tracking-wide agent-text-muted">{{ t('growth.ai.tickets') }}</p>
                            <p class="mt-2 text-3xl font-bold">{{ aiUsage.unique_tickets ?? 0 }}</p>
                        </div>
                    </div>

                    <div class="rounded-2xl border agent-border agent-panel p-5">
                        <h3 class="text-sm font-semibold">{{ t('growth.ai.trend') }}</h3>
                        <div class="mt-4 flex h-24 items-end gap-1">
                            <div
                                v-for="day in aiUsage.trend ?? []"
                                :key="day.date"
                                class="flex-1 rounded-t bg-indigo-500/80 dark:bg-indigo-400/80"
                                :style="{ height: `${Math.max(4, (day.count / maxTrend) * 100)}%` }"
                                :title="`${day.date}: ${day.count}`"
                            />
                        </div>
                    </div>

                    <div v-if="(aiUsage.top_agents ?? []).length" class="rounded-2xl border agent-border agent-panel p-5">
                        <h3 class="text-sm font-semibold">{{ t('growth.ai.top_agents') }}</h3>
                        <ul class="mt-3 space-y-2">
                            <li
                                v-for="agent in aiUsage.top_agents"
                                :key="agent.user_id"
                                class="flex items-center justify-between text-sm"
                            >
                                <span>{{ agent.name }}</span>
                                <span class="font-medium">{{ agent.messages }}</span>
                            </li>
                        </ul>
                    </div>
                </div>

                <div v-else-if="tab === 'deflection'" class="mt-6 space-y-6">
                    <div class="flex flex-wrap items-end gap-3 rounded-2xl border agent-border agent-panel p-4">
                        <label class="text-sm">
                            <span class="mb-1 block agent-text-muted">{{ t('growth.date_from') }}</span>
                            <input v-model="dateFrom" type="date" class="rounded-lg border agent-border bg-transparent px-3 py-1.5 text-sm" />
                        </label>
                        <label class="text-sm">
                            <span class="mb-1 block agent-text-muted">{{ t('growth.date_to') }}</span>
                            <input v-model="dateTo" type="date" class="rounded-lg border agent-border bg-transparent px-3 py-1.5 text-sm" />
                        </label>
                        <button
                            type="button"
                            class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-medium text-white dark:bg-slate-100 dark:text-slate-900"
                            @click="applyDateFilter"
                        >
                            {{ t('growth.apply_filter') }}
                        </button>
                    </div>

                    <div class="grid gap-4 lg:grid-cols-2">
                        <div class="rounded-2xl border agent-border agent-panel p-5">
                            <h3 class="text-sm font-semibold">{{ t('growth.deflection.ai_title') }}</h3>
                            <dl class="mt-4 grid grid-cols-2 gap-3 text-sm">
                                <div><dt class="agent-text-muted">{{ t('growth.deflection.queries') }}</dt><dd class="text-xl font-bold">{{ aiDeflection.queries ?? 0 }}</dd></div>
                                <div><dt class="agent-text-muted">{{ t('growth.deflection.helpful') }}</dt><dd class="text-xl font-bold">{{ aiDeflection.helpful ?? 0 }}</dd></div>
                                <div><dt class="agent-text-muted">{{ t('growth.deflection.tickets') }}</dt><dd class="text-xl font-bold">{{ aiDeflection.tickets_created ?? 0 }}</dd></div>
                                <div><dt class="agent-text-muted">{{ t('growth.deflection.rate') }}</dt><dd class="text-xl font-bold">{{ formatPercent(aiDeflection.deflection_rate) }}</dd></div>
                            </dl>
                        </div>
                        <div class="rounded-2xl border agent-border agent-panel p-5">
                            <h3 class="text-sm font-semibold">{{ t('growth.deflection.kb_title') }}</h3>
                            <dl class="mt-4 grid grid-cols-2 gap-3 text-sm">
                                <div><dt class="agent-text-muted">{{ t('growth.deflection.suggestions') }}</dt><dd class="text-xl font-bold">{{ kbDeflection.suggestions_shown ?? 0 }}</dd></div>
                                <div><dt class="agent-text-muted">{{ t('growth.deflection.deflected') }}</dt><dd class="text-xl font-bold">{{ kbDeflection.deflected ?? 0 }}</dd></div>
                                <div><dt class="agent-text-muted">{{ t('growth.deflection.continued') }}</dt><dd class="text-xl font-bold">{{ kbDeflection.continued ?? 0 }}</dd></div>
                                <div><dt class="agent-text-muted">{{ t('growth.deflection.rate') }}</dt><dd class="text-xl font-bold">{{ formatPercent(kbDeflection.deflection_rate) }}</dd></div>
                            </dl>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AgentLayout>
</template>
