<script setup>
import { Head, Link } from '@inertiajs/vue3';
import PortalLayout from '../../Layouts/PortalLayout.vue';
import { usePortalRoutes } from '../../composables/usePortalRoutes.js';
import { useI18n } from 'vue-i18n';
import { useDateTime } from '../../composables/useDateTime.js';

defineProps({
    tickets: Object,
});

const { formatDateTime } = useDateTime();

const { t } = useI18n();
const { portalPath } = usePortalRoutes();

const statusClass = (ticket) => {
    if (ticket.sla_timer?.first_response_breached || ticket.sla_timer?.resolution_breached) {
        return 'text-red-600';
    }
    return 'text-slate-500';
};
</script>

<template>
    <Head :title="$t('portal.my_tickets')" />
    <PortalLayout>
        <div class="mb-6 flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold text-slate-900">{{ $t('portal.my_tickets') }}</h1>
                <p class="mt-1 text-sm text-slate-600">{{ $t('portal.all_support_requests_linked_to_your_account') }}</p>
            </div>
            <Link :href="portalPath('/submit')" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">{{ $t('portal.new_request') }}</Link>
        </div>

        <div class="space-y-3">
            <Link
                v-for="ticket in tickets.data"
                :key="ticket.id"
                :href="portalPath(`/my-tickets/${ticket.id}`)"
                class="block rounded-xl border border-slate-200 bg-white p-5 shadow-sm hover:border-blue-300"
            >
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <p class="text-sm font-medium text-slate-500">{{ ticket.number }}</p>
                        <h2 class="font-semibold text-slate-900">{{ ticket.subject }}</h2>
                    </div>
                    <span class="rounded-full bg-slate-100 px-3 py-1 text-sm text-slate-700">{{ ticket.status?.name }}</span>
                </div>
                <p class="mt-2 text-xs" :class="statusClass(ticket)">
                    {{ formatDateTime(ticket.created_at) }}
                    <span v-if="ticket.sla_timer?.resolution_breached"> · Resolution overdue</span>
                </p>
            </Link>
            <p v-if="!tickets.data?.length" class="rounded-xl border border-slate-200 bg-white p-8 text-center text-sm text-slate-500">
                You have no tickets yet.
                <Link :href="portalPath('/submit')" class="text-blue-600 hover:text-blue-700">{{ $t('portal.submit_a_request') }}</Link>
            </p>
        </div>
    </PortalLayout>
</template>
