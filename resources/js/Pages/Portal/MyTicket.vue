<script setup>
import { Head, Link } from '@inertiajs/vue3';
import PortalLayout from '../../Layouts/PortalLayout.vue';
import CsatRatingForm from '../../Components/CsatRatingForm.vue';
import TicketMessageContent from '../../Components/TicketMessageContent.vue';
import { usePortalRoutes } from '../../composables/usePortalRoutes.js';
import { useI18n } from 'vue-i18n';
import { useDateTime } from '../../composables/useDateTime.js';

defineProps({
    ticket: Object,
    csat: Object,
});

const { formatDateTime } = useDateTime();

const { t } = useI18n();
const { portalPath } = usePortalRoutes();
</script>

<template>
    <Head :title="ticket.number" />
    <PortalLayout>
        <div class="mb-6">
            <Link :href="portalPath('/my-tickets')" class="text-sm text-blue-600 hover:text-blue-700 dark:hover:text-blue-300 dark:text-blue-300">← My tickets</Link>
        </div>

        <div class="rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 p-6 shadow-sm">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <p class="text-sm font-medium text-slate-500 dark:text-slate-400">{{ ticket.number }}</p>
                    <h1 class="text-2xl font-bold text-slate-900 dark:text-slate-100">{{ ticket.subject }}</h1>
                </div>
                <span class="rounded-full bg-slate-100 dark:bg-slate-900 px-3 py-1 text-sm text-slate-700 dark:text-slate-300">{{ ticket.status?.name }}</span>
            </div>
            <div v-if="ticket.description" class="mt-4">
                <TicketMessageContent :body="ticket.description" />
            </div>

            <div v-if="ticket.sla_timer" class="mt-4 grid gap-3 rounded-lg bg-slate-50 dark:bg-slate-950 p-4 text-sm sm:grid-cols-2">
                <div>
                    <p class="font-medium text-slate-700 dark:text-slate-300">{{ $t('portal.first_response_due') }}</p>
                    <p :class="ticket.sla_timer.first_response_breached ? 'text-red-600' : 'text-slate-600 dark:text-slate-400'">
                        {{ ticket.sla_timer.first_response_due_at ? formatDateTime(ticket.sla_timer.first_response_due_at) : '—' }}
                    </p>
                </div>
                <div>
                    <p class="font-medium text-slate-700 dark:text-slate-300">{{ $t('portal.resolution_due') }}</p>
                    <p :class="ticket.sla_timer.resolution_breached ? 'text-red-600' : 'text-slate-600 dark:text-slate-400'">
                        {{ ticket.sla_timer.resolution_due_at ? formatDateTime(ticket.sla_timer.resolution_due_at) : '—' }}
                    </p>
                </div>
            </div>

            <div v-if="ticket.messages?.length" class="mt-6 border-t border-slate-100 dark:border-slate-800 pt-4">
                <h2 class="text-sm font-semibold text-slate-900 dark:text-slate-100">{{ $t('portal.updates') }}</h2>
                <div class="mt-3 space-y-3">
                    <div v-for="message in ticket.messages" :key="message.id" class="rounded-lg bg-slate-50 dark:bg-slate-950 p-3 text-sm">
                        <div class="flex justify-between text-slate-500 dark:text-slate-400">
                            <span>{{ message.user?.name || 'Support' }}</span>
                            <span>{{ formatDateTime(message.created_at) }}</span>
                        </div>
                        <p class="mt-1 whitespace-pre-wrap text-slate-800 dark:text-slate-200">{{ message.body }}</p>
                    </div>
                </div>
            </div>

            <CsatRatingForm
                v-if="csat"
                :csat="csat"
                :ticket="ticket"
                :action="portalPath(`/my-tickets/${ticket.id}/csat`)"
            />
        </div>
    </PortalLayout>
</template>
