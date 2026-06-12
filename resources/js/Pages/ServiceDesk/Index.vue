<script setup>
import { Head, Link } from '@inertiajs/vue3';
import AgentLayout from '../../Layouts/AgentLayout.vue';
import ServiceDeskNav from '../../Components/ServiceDeskNav.vue';
import PageHeader from '../../Components/PageHeader.vue';
import StatusBadge from '../../Components/StatusBadge.vue';
import { useI18n } from 'vue-i18n';

const props = defineProps({
    summaries: Array,
    totals: Object,
    recent: Object,
    approvalStats: Object,
    majorIncidentStats: Object,
});

const { t } = useI18n();

const queueHref = (type) => `/service-desk/queues/${type}`;

const cardTone = (type) => {
    if (type === 'incident') {
        return 'border-red-200 bg-red-50/60 dark:border-red-900/60 dark:bg-red-950/40';
    }

    if (type === 'change') {
        return 'border-violet-200 bg-violet-50/60 dark:border-violet-900/60 dark:bg-violet-950/40';
    }

    if (type === 'problem') {
        return 'border-amber-200 bg-amber-50/60 dark:border-amber-900/60 dark:bg-amber-950/40';
    }

    return 'border-blue-200 bg-blue-50/60 dark:border-blue-900/60 dark:bg-blue-950/40';
};
</script>

<template>
    <Head :title="$t('service_desk.service_desk')" />
    <AgentLayout>
        <PageHeader :title="$t('service_desk.service_desk')" :description="$t('service_desk.itsm_queues_for_incidents_requests_changes_and_problems')" />

        <ServiceDeskNav />

        <div class="mb-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <div class="rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 p-4 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-400 dark:text-slate-500">{{ $t('service_desk.open_items') }}</p>
                <p class="mt-2 text-3xl font-semibold text-slate-900 dark:text-slate-100">{{ totals.open }}</p>
            </div>
            <div class="rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 p-4 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-400 dark:text-slate-500">{{ $t('service_desk.unassigned') }}</p>
                <p class="mt-2 text-3xl font-semibold text-slate-900 dark:text-slate-100">{{ totals.unassigned }}</p>
            </div>
            <div class="rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 p-4 shadow-sm sm:col-span-2">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-400 dark:text-slate-500">{{ $t('service_desk.pending_approvals') }}</p>
                <p class="mt-2 text-3xl font-semibold text-slate-900 dark:text-slate-100">{{ approvalStats.pending_mine }}</p>
                <Link href="/service-desk/approvals?mine=1" class="mt-2 inline-block text-sm font-medium text-blue-600 hover:text-blue-700 dark:hover:text-blue-300 dark:text-blue-300">
                    Review my approvals
                </Link>
            </div>
            <div class="rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 p-4 shadow-sm sm:col-span-2">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-400 dark:text-slate-500">{{ $t('service_desk.major_incidents') }}</p>
                <p class="mt-2 text-3xl font-semibold text-red-700 dark:text-red-300">{{ majorIncidentStats.active }}</p>
                <Link href="/service-desk/major-incidents" class="mt-2 inline-block text-sm font-medium text-blue-600 hover:text-blue-700 dark:hover:text-blue-300 dark:text-blue-300">
                    Open war rooms
                    <span v-if="majorIncidentStats?.pending_review"> · {{ majorIncidentStats.pending_review }} pending review</span>
                </Link>
            </div>
            <div class="rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 p-4 shadow-sm sm:col-span-2">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-400 dark:text-slate-500">{{ $t('service_desk.quick_links') }}</p>
                <div class="mt-3 flex flex-wrap gap-2">
                    <Link href="/settings/service-catalog" class="rounded-lg border border-slate-200 dark:border-slate-800 px-3 py-1.5 text-sm text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800">
                        {{ $t('settings.service_catalog') }}
                    </Link>
                    <Link href="/assets" class="rounded-lg border border-slate-200 dark:border-slate-800 px-3 py-1.5 text-sm text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800">
                        {{ $t('nav.assets') }}
                    </Link>
                    <Link href="/reports" class="rounded-lg border border-slate-200 dark:border-slate-800 px-3 py-1.5 text-sm text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800">
                        {{ $t('nav.reports') }}
                    </Link>
                </div>
            </div>
        </div>

        <div class="grid gap-4 xl:grid-cols-2">
            <Link
                v-for="summary in summaries"
                :key="summary.type"
                :href="queueHref(summary.type)"
                class="rounded-xl border p-5 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md"
                :class="cardTone(summary.type)"
            >
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <h2 class="text-lg font-semibold text-slate-900 dark:text-slate-100">{{ summary.label }}</h2>
                        <p class="mt-1 text-sm text-slate-600 dark:text-slate-400">{{ summary.description }}</p>
                    </div>
                    <span class="rounded-full bg-white/80 px-2.5 py-1 text-xs font-semibold text-slate-700 ring-1 ring-black/5">
                        {{ summary.open }} open
                    </span>
                </div>
                <dl class="mt-4 grid grid-cols-3 gap-3 text-sm">
                    <div>
                        <dt class="text-slate-500 dark:text-slate-400">{{ $t('service_desk.total') }}</dt>
                        <dd class="font-semibold text-slate-900 dark:text-slate-100">{{ summary.total }}</dd>
                    </div>
                    <div>
                        <dt class="text-slate-500 dark:text-slate-400">{{ $t('service_desk.open') }}</dt>
                        <dd class="font-semibold text-slate-900 dark:text-slate-100">{{ summary.open }}</dd>
                    </div>
                    <div>
                        <dt class="text-slate-500 dark:text-slate-400">{{ $t('service_desk.unassigned') }}</dt>
                        <dd class="font-semibold text-slate-900 dark:text-slate-100">{{ summary.unassigned }}</dd>
                    </div>
                </dl>
            </Link>
        </div>

        <div class="mt-8 grid gap-6 xl:grid-cols-2">
            <section
                v-for="summary in summaries"
                :key="`recent-${summary.type}`"
                class="rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 p-5 shadow-sm"
            >
                <div class="mb-4 flex items-center justify-between gap-3">
                    <h3 class="text-base font-semibold text-slate-900 dark:text-slate-100">Recent {{ summary.label.toLowerCase() }}</h3>
                    <Link :href="queueHref(summary.type)" class="text-sm font-medium text-blue-600 hover:text-blue-700 dark:hover:text-blue-300 dark:text-blue-300">
                        View queue
                    </Link>
                </div>

                <div v-if="(recent[summary.type] || []).length === 0" class="rounded-lg border border-dashed border-slate-200 dark:border-slate-800 px-4 py-8 text-center text-sm text-slate-500 dark:text-slate-400">
                    No {{ summary.label.toLowerCase() }} yet.
                </div>

                <ul v-else class="divide-y divide-slate-100 dark:divide-slate-800">
                    <li v-for="ticket in recent[summary.type]" :key="ticket.id">
                        <Link :href="`/tickets/${ticket.id}`" class="flex items-center justify-between gap-3 py-3 hover:bg-slate-50 dark:hover:bg-slate-800">
                            <div class="min-w-0">
                                <p class="truncate text-sm font-medium text-slate-900 dark:text-slate-100">{{ ticket.subject }}</p>
                                <p class="truncate text-xs text-slate-500 dark:text-slate-400">
                                    {{ ticket.number }} · {{ ticket.contact?.name || 'Unknown' }}
                                </p>
                            </div>
                            <StatusBadge :label="ticket.status?.name" :color="ticket.status?.color" />
                        </Link>
                    </li>
                </ul>
            </section>
        </div>
    </AgentLayout>
</template>
