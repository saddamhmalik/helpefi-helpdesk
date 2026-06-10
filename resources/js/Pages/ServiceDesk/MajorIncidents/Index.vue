<script setup>
import { Head, Link } from '@inertiajs/vue3';
import AgentLayout from '../../../Layouts/AgentLayout.vue';
import ServiceDeskNav from '../../../Components/ServiceDeskNav.vue';
import PageHeader from '../../../Components/PageHeader.vue';
import DataTable from '../../../Components/DataTable.vue';
import { useI18n } from 'vue-i18n';
import { useDateTime } from '../../../composables/useDateTime.js';

const props = defineProps({
    entries: Array,
    active_count: Number,
    pending_review_count: Number,
});

const { formatDateTime } = useDateTime();

const { t } = useI18n();

const statusClass = (status) => {
    if (status === 'active') return 'bg-red-100 text-red-800';
    if (status === 'resolved') return 'bg-amber-100 text-amber-800';

    return 'bg-slate-100 text-slate-700';
};

const formatDate = (value) => {
    if (!value) return '—';

    return formatDateTime(value, {
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
};
</script>

<template>
    <Head :title="$t('service_desk.major_incidents')" />
    <AgentLayout>
        <PageHeader :title="$t('service_desk.major_incidents')" :description="$t('service_desk.active_war_rooms_and_post-incident_reviews_awaiting_completion')" />

        <ServiceDeskNav />

        <div class="mb-6 grid gap-4 sm:grid-cols-2">
            <div class="rounded-xl border border-red-200 bg-red-50/60 p-4 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-wide text-red-700">{{ $t('service_desk.active_war_rooms') }}</p>
                <p class="mt-2 text-3xl font-semibold text-red-900">{{ active_count }}</p>
            </div>
            <div class="rounded-xl border border-amber-200 bg-amber-50/60 p-4 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-wide text-amber-700">{{ $t('service_desk.pending_review') }}</p>
                <p class="mt-2 text-3xl font-semibold text-amber-900">{{ pending_review_count }}</p>
            </div>
        </div>

        <DataTable>
            <template #head>
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">{{ $t('service_desk.incident') }}</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">{{ $t('service_desk.status') }}</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">{{ $t('service_desk.declared') }}</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">{{ $t('service_desk.assignee') }}</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">{{ $t('service_desk.actions') }}</th>
                </tr>
            </template>
            <template #body>
                <tr v-if="!entries?.length">
                    <td colspan="5" class="px-4 py-10 text-center text-sm text-slate-500">No active major incidents or pending reviews.</td>
                </tr>
                <tr v-for="entry in entries" :key="entry.id" class="border-t border-slate-100">
                    <td class="px-4 py-3">
                        <p class="font-medium text-slate-900">{{ entry.subject }}</p>
                        <p class="text-xs text-slate-500">{{ entry.number }}</p>
                    </td>
                    <td class="px-4 py-3">
                        <span class="rounded-full px-2 py-0.5 text-xs font-medium capitalize" :class="statusClass(entry.status)">
                            {{ entry.status }}
                        </span>
                        <p v-if="entry.ticket_status" class="mt-1 text-xs text-slate-500">{{ entry.ticket_status }}</p>
                    </td>
                    <td class="px-4 py-3 text-sm text-slate-700">
                        <p>{{ formatDate(entry.declared_at) }}</p>
                        <p v-if="entry.declared_by" class="text-xs text-slate-500">by {{ entry.declared_by }}</p>
                    </td>
                    <td class="px-4 py-3 text-sm text-slate-700">{{ entry.assignee || '—' }}</td>
                    <td class="px-4 py-3">
                        <Link
                            :href="entry.war_room_url"
                            class="rounded-lg bg-red-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-red-700"
                        >
                            {{ entry.status === 'active' ? 'War room' : 'Review' }}
                        </Link>
                    </td>
                </tr>
            </template>
        </DataTable>
    </AgentLayout>
</template>
