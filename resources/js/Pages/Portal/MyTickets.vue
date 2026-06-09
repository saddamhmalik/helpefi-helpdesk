<script setup>
import { Head, Link } from '@inertiajs/vue3';
import PortalLayout from '../../Layouts/PortalLayout.vue';

defineProps({
    tickets: Object,
});

const statusClass = (ticket) => {
    if (ticket.sla_timer?.first_response_breached || ticket.sla_timer?.resolution_breached) {
        return 'text-red-600';
    }
    return 'text-slate-500';
};
</script>

<template>
    <Head title="My tickets" />
    <PortalLayout>
        <div class="mb-6 flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold text-slate-900">My tickets</h1>
                <p class="mt-1 text-sm text-slate-600">All support requests linked to your account.</p>
            </div>
            <Link href="/portal/submit" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">New request</Link>
        </div>

        <div class="space-y-3">
            <Link
                v-for="ticket in tickets.data"
                :key="ticket.id"
                :href="`/portal/my-tickets/${ticket.id}`"
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
                    {{ new Date(ticket.created_at).toLocaleString() }}
                    <span v-if="ticket.sla_timer?.resolution_breached"> · Resolution overdue</span>
                </p>
            </Link>
            <p v-if="!tickets.data?.length" class="rounded-xl border border-slate-200 bg-white p-8 text-center text-sm text-slate-500">
                You have no tickets yet.
                <Link href="/portal/submit" class="text-blue-600 hover:text-blue-700">Submit a request</Link>
            </p>
        </div>
    </PortalLayout>
</template>
