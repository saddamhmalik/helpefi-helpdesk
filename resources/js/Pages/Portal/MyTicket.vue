<script setup>
import { Head, Link } from '@inertiajs/vue3';
import PortalLayout from '../../Layouts/PortalLayout.vue';
import CsatRatingForm from '../../Components/CsatRatingForm.vue';
import TicketMessageContent from '../../Components/TicketMessageContent.vue';

defineProps({
    ticket: Object,
    csat: Object,
});
</script>

<template>
    <Head :title="ticket.number" />
    <PortalLayout>
        <div class="mb-6">
            <Link href="/portal/my-tickets" class="text-sm text-blue-600 hover:text-blue-700">← My tickets</Link>
        </div>

        <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <p class="text-sm font-medium text-slate-500">{{ ticket.number }}</p>
                    <h1 class="text-2xl font-bold text-slate-900">{{ ticket.subject }}</h1>
                </div>
                <span class="rounded-full bg-slate-100 px-3 py-1 text-sm text-slate-700">{{ ticket.status?.name }}</span>
            </div>
            <div v-if="ticket.description" class="mt-4">
                <TicketMessageContent :body="ticket.description" />
            </div>

            <div v-if="ticket.sla_timer" class="mt-4 grid gap-3 rounded-lg bg-slate-50 p-4 text-sm sm:grid-cols-2">
                <div>
                    <p class="font-medium text-slate-700">First response due</p>
                    <p :class="ticket.sla_timer.first_response_breached ? 'text-red-600' : 'text-slate-600'">
                        {{ ticket.sla_timer.first_response_due_at ? new Date(ticket.sla_timer.first_response_due_at).toLocaleString() : '—' }}
                    </p>
                </div>
                <div>
                    <p class="font-medium text-slate-700">Resolution due</p>
                    <p :class="ticket.sla_timer.resolution_breached ? 'text-red-600' : 'text-slate-600'">
                        {{ ticket.sla_timer.resolution_due_at ? new Date(ticket.sla_timer.resolution_due_at).toLocaleString() : '—' }}
                    </p>
                </div>
            </div>

            <div v-if="ticket.messages?.length" class="mt-6 border-t border-slate-100 pt-4">
                <h2 class="text-sm font-semibold text-slate-900">Updates</h2>
                <div class="mt-3 space-y-3">
                    <div v-for="message in ticket.messages" :key="message.id" class="rounded-lg bg-slate-50 p-3 text-sm">
                        <div class="flex justify-between text-slate-500">
                            <span>{{ message.user?.name || 'Support' }}</span>
                            <span>{{ new Date(message.created_at).toLocaleString() }}</span>
                        </div>
                        <p class="mt-1 whitespace-pre-wrap text-slate-800">{{ message.body }}</p>
                    </div>
                </div>
            </div>

            <CsatRatingForm
                v-if="csat"
                :csat="csat"
                :ticket="ticket"
                :action="`/portal/my-tickets/${ticket.id}/csat`"
            />
        </div>
    </PortalLayout>
</template>
