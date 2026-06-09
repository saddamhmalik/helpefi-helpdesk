<script setup>
import { Link } from '@inertiajs/vue3';
import SettingsLayout from '../../Layouts/SettingsLayout.vue';

defineProps({
    agent: Object,
    summary: Object,
    events: Object,
    pointMap: Object,
});
</script>

<template>
    <SettingsLayout :title="agent.name" :description="agent.email" :head-title="`Performance · ${agent.name}`">
        <Link :href="`/settings/members/${agent.id}`" class="mb-4 inline-block text-sm text-blue-600 transition hover:text-blue-700">← Member profile</Link>

        <div class="mb-6 grid gap-4 sm:grid-cols-4">
            <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                <p class="text-sm text-slate-500">Performance score</p>
                <p class="mt-1 text-3xl font-semibold" :class="summary.score >= 80 ? 'text-emerald-600' : summary.score >= 60 ? 'text-amber-600' : 'text-red-600'">
                    {{ Number(summary.score).toFixed(1) }}
                </p>
            </div>
            <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                <p class="text-sm text-slate-500">30-day points</p>
                <p class="mt-1 text-2xl font-semibold text-slate-900">{{ summary.total_points }}</p>
            </div>
            <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                <p class="text-sm text-slate-500">Positive events</p>
                <p class="mt-1 text-2xl font-semibold text-emerald-700">{{ summary.positive_events }}</p>
            </div>
            <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                <p class="text-sm text-slate-500">SLA violations</p>
                <p class="mt-1 text-2xl font-semibold text-red-600">{{ summary.violations }}</p>
            </div>
        </div>

        <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
            <table class="min-w-full divide-y divide-slate-200">
                <thead class="bg-slate-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase text-slate-500">Event</th>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase text-slate-500">Ticket</th>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase text-slate-500">Points</th>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase text-slate-500">When</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200">
                    <tr v-for="event in events.data" :key="event.id">
                        <td class="px-4 py-3 text-sm text-slate-700">{{ event.event_type.replaceAll('_', ' ') }}</td>
                        <td class="px-4 py-3 text-sm">
                            <Link v-if="event.ticket" :href="`/tickets/${event.ticket.id}`" class="text-blue-600 hover:text-blue-700">{{ event.ticket.number }}</Link>
                            <span v-else class="text-slate-400">—</span>
                        </td>
                        <td class="px-4 py-3 text-sm font-medium" :class="event.points >= 0 ? 'text-emerald-700' : 'text-red-600'">{{ event.points >= 0 ? '+' : '' }}{{ event.points }}</td>
                        <td class="px-4 py-3 text-sm text-slate-500">{{ new Date(event.created_at).toLocaleString() }}</td>
                    </tr>
                    <tr v-if="!events.data?.length">
                        <td colspan="4" class="px-4 py-8 text-center text-sm text-slate-500">No performance events yet.</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </SettingsLayout>
</template>
