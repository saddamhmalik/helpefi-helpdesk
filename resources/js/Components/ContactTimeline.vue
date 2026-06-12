<script setup>
import { Link } from '@inertiajs/vue3';
import { useDateTime } from '../composables/useDateTime.js';

defineProps({
    events: {
        type: Array,
        default: () => [],
    },
});

const { formatDateTime } = useDateTime();

const iconClass = (type) => {
    if (type === 'ticket_opened') return 'bg-blue-100 text-blue-700 dark:text-blue-300';
    if (type === 'customer_message') return 'bg-indigo-100 text-indigo-700 dark:text-indigo-300';
    if (type === 'csat') return 'bg-emerald-100 text-emerald-700 dark:text-emerald-300';
    if (type === 'chat_session') return 'bg-violet-100 text-violet-700 dark:text-violet-300';
    if (type === 'note') return 'bg-amber-100 text-amber-800';

    return 'bg-slate-100 dark:bg-slate-900 text-slate-700 dark:text-slate-300';
};

const iconLabel = (type) => {
    if (type === 'ticket_opened') return 'T';
    if (type === 'customer_message') return 'M';
    if (type === 'csat') return '★';
    if (type === 'chat_session') return 'C';
    if (type === 'note') return 'N';

    return '•';
};
</script>

<template>
    <div class="rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 p-6 shadow-sm">
        <div class="flex items-center justify-between gap-3">
            <div>
                <h2 class="text-lg font-semibold text-slate-900 dark:text-slate-100">{{ $t('components.customer_360') }}</h2>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ $t('components.tickets_messages_csat_chat_and_notes_in_one_timeline') }}</p>
            </div>
        </div>

        <ul v-if="events.length" class="mt-5 space-y-4">
            <li v-for="event in events" :key="event.id" class="flex gap-3">
                <span
                    class="mt-0.5 flex h-8 w-8 shrink-0 items-center justify-center rounded-full text-xs font-semibold"
                    :class="iconClass(event.type)"
                >
                    {{ iconLabel(event.type) }}
                </span>
                <div class="min-w-0 flex-1 border-b border-slate-100 dark:border-slate-800 pb-4">
                    <div class="flex flex-wrap items-start justify-between gap-2">
                        <p class="text-sm font-medium text-slate-900 dark:text-slate-100">{{ event.title }}</p>
                        <span class="shrink-0 text-xs text-slate-400 dark:text-slate-500">{{ formatDateTime(event.occurred_at) }}</span>
                    </div>
                    <p v-if="event.body" class="mt-1 text-sm text-slate-600 dark:text-slate-400">{{ event.body }}</p>
                    <div class="mt-2 flex flex-wrap items-center gap-2 text-xs text-slate-500 dark:text-slate-400">
                        <span>{{ event.actor }}</span>
                        <Link
                            v-if="event.ticket_id"
                            :href="`/tickets/${event.ticket_id}`"
                            class="font-medium text-blue-600 hover:text-blue-700 dark:hover:text-blue-300 dark:text-blue-300"
                        >
                            {{ event.ticket_number }}
                        </Link>
                        <span v-if="event.meta?.status" class="rounded-full bg-slate-100 dark:bg-slate-900 px-2 py-0.5">{{ event.meta.status }}</span>
                        <span v-if="event.meta?.channel" class="rounded-full bg-slate-100 dark:bg-slate-900 px-2 py-0.5">{{ event.meta.channel }}</span>
                    </div>
                </div>
            </li>
        </ul>

        <p v-else class="mt-5 text-sm text-slate-500 dark:text-slate-400">{{ $t('components.no_customer_history_yet') }}</p>
    </div>
</template>
