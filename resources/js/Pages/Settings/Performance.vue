<script setup>
import { Link } from '@inertiajs/vue3';
import SettingsPage from '../../Components/SettingsPage.vue';
import { useI18n } from 'vue-i18n';
import { useDateTime } from '../../composables/useDateTime.js';

defineProps({
    agent: Object,
    summary: Object,
    events: Object,
    pointMap: Object,
});

const { formatDateTime } = useDateTime();

const { t } = useI18n();
</script>

<template>
    <SettingsPage :title="agent.name" :description="agent.email" :head-title="`Performance · ${agent.name}`">
        <Link :href="`/settings/members/${agent.id}`" class="mb-4 inline-block text-sm text-blue-600 transition hover:text-blue-700 dark:hover:text-blue-300 dark:text-blue-300">← Member profile</Link>

        <div class="mb-6 grid gap-4 sm:grid-cols-4">
            <div class="rounded-xl border agent-border agent-panel p-4 shadow-sm">
                <p class="text-sm agent-text-subtle">{{ $t('settings_performance.performance_score') }}</p>
                <p class="mt-1 text-3xl font-semibold" :class="summary.score >= 80 ? 'text-emerald-600' : summary.score >= 60 ? 'text-amber-600' : 'text-red-600'">
                    {{ Number(summary.score).toFixed(1) }}
                </p>
            </div>
            <div class="rounded-xl border agent-border agent-panel p-4 shadow-sm">
                <p class="text-sm agent-text-subtle">{{ $t('settings_performance.30-day_points') }}</p>
                <p class="mt-1 text-2xl font-semibold agent-text">{{ summary.total_points }}</p>
            </div>
            <div class="rounded-xl border agent-border agent-panel p-4 shadow-sm">
                <p class="text-sm agent-text-subtle">{{ $t('settings_performance.positive_events') }}</p>
                <p class="mt-1 text-2xl font-semibold text-emerald-700 dark:text-emerald-300">{{ summary.positive_events }}</p>
            </div>
            <div class="rounded-xl border agent-border agent-panel p-4 shadow-sm">
                <p class="text-sm agent-text-subtle">{{ $t('settings_performance.sla_violations') }}</p>
                <p class="mt-1 text-2xl font-semibold text-red-600">{{ summary.violations }}</p>
            </div>
        </div>

        <div class="overflow-hidden rounded-xl border agent-border agent-panel shadow-sm">
            <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-800 dark:divide-slate-700">
                <thead class="agent-panel-muted">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase agent-text-subtle">{{ $t('settings_performance.event') }}</th>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase agent-text-subtle">{{ $t('settings_performance.ticket') }}</th>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase agent-text-subtle">{{ $t('settings_performance.points') }}</th>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase agent-text-subtle">{{ $t('settings_performance.when') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200 dark:divide-slate-800 dark:divide-slate-700">
                    <tr v-for="event in events.data" :key="event.id">
                        <td class="px-4 py-3 text-sm text-slate-700 dark:text-slate-300">{{ event.event_type.replaceAll('_', ' ') }}</td>
                        <td class="px-4 py-3 text-sm">
                            <Link v-if="event.ticket" :href="`/tickets/${event.ticket.id}`" class="text-blue-600 hover:text-blue-700 dark:hover:text-blue-300 dark:text-blue-300">{{ event.ticket.number }}</Link>
                            <span v-else class="text-slate-400 dark:text-slate-500">—</span>
                        </td>
                        <td class="px-4 py-3 text-sm font-medium" :class="event.points >= 0 ? 'text-emerald-700 dark:text-emerald-300' : 'text-red-600'">{{ event.points >= 0 ? '+' : '' }}{{ event.points }}</td>
                        <td class="px-4 py-3 text-sm agent-text-subtle">{{ formatDateTime(event.created_at) }}</td>
                    </tr>
                    <tr v-if="!events.data?.length">
                        <td colspan="4" class="px-4 py-8 text-center text-sm agent-text-subtle">No performance events yet.</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </SettingsPage>
</template>
