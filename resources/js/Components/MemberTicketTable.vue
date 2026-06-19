<script setup>
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';

const props = defineProps({
    title: { type: String, required: true },
    tickets: { type: Array, default: () => [] },
    empty: { type: String, default: '' },
});

const { t } = useI18n();

const emptyLabel = computed(() => props.empty || t('components.no_tickets'));

const statusBadgeClass = (name) => {
    const value = (name || '').toLowerCase();
    if (value.includes('open')) return 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/50 dark:text-emerald-300';
    if (value.includes('pending')) return 'bg-amber-100 text-amber-800 dark:bg-amber-950/50 dark:text-amber-300';
    if (value.includes('closed') || value.includes('resolved')) return 'bg-slate-200 text-slate-700 dark:bg-slate-800 dark:text-slate-300';

    return 'bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300';
};

const priorityBadgeClass = (name) => {
    const value = (name || '').toLowerCase();
    if (value.includes('urgent') || value.includes('critical')) return 'bg-red-100 text-red-800 dark:bg-red-950/50 dark:text-red-300';
    if (value.includes('high')) return 'bg-orange-100 text-orange-800 dark:bg-orange-950/50 dark:text-orange-300';
    if (value.includes('low')) return 'bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400';

    return 'bg-blue-100 text-blue-800 dark:bg-blue-950/50 dark:text-blue-300';
};
</script>

<template>
    <section class="overflow-hidden rounded-2xl border agent-border agent-panel shadow-sm">
        <div class="border-b agent-border-subtle px-6 py-4">
            <h2 class="text-base font-semibold agent-text">{{ title }}</h2>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y agent-border-subtle">
                <thead class="agent-panel-muted">
                    <tr>
                        <th class="px-4 py-2.5 text-left text-xs font-medium uppercase agent-text-subtle">{{ $t('components.ticket') }}</th>
                        <th class="px-4 py-2.5 text-left text-xs font-medium uppercase agent-text-subtle">{{ $t('components.status') }}</th>
                        <th class="px-4 py-2.5 text-left text-xs font-medium uppercase agent-text-subtle">{{ $t('components.priority') }}</th>
                        <th class="px-4 py-2.5 text-left text-xs font-medium uppercase agent-text-subtle">{{ $t('components.routing') }}</th>
                        <th class="px-4 py-2.5 text-left text-xs font-medium uppercase agent-text-subtle">{{ $t('components.assignee') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y agent-border-subtle">
                    <tr v-for="ticket in tickets" :key="ticket.id" class="agent-hover-row">
                        <td class="px-4 py-3">
                            <Link :href="`/tickets/${ticket.id}`" class="block">
                                <span class="text-sm font-medium text-blue-600 hover:text-blue-700 dark:hover:text-blue-300 dark:text-blue-300">{{ ticket.number }}</span>
                                <span class="mt-0.5 block max-w-xs truncate text-xs agent-text-subtle">{{ ticket.subject }}</span>
                            </Link>
                        </td>
                        <td class="px-4 py-3">
                            <span class="rounded-full px-2 py-0.5 text-xs font-medium" :class="statusBadgeClass(ticket.status?.name)">{{ ticket.status?.name || $t('components.em_dash') }}</span>
                        </td>
                        <td class="px-4 py-3">
                            <span class="rounded-full px-2 py-0.5 text-xs font-medium" :class="priorityBadgeClass(ticket.priority?.name)">{{ ticket.priority?.name || $t('components.em_dash') }}</span>
                        </td>
                        <td class="px-4 py-3 text-xs agent-text-muted">
                            <span v-if="ticket.department">{{ ticket.department.name }}</span>
                            <span v-if="ticket.team"> · {{ ticket.team.name }}</span>
                            <span v-if="!ticket.department && !ticket.team">{{ $t('components.em_dash') }}</span>
                        </td>
                        <td class="px-4 py-3 text-sm agent-text-muted">{{ ticket.assignee?.name || $t('components.unassigned') }}</td>
                    </tr>
                    <tr v-if="!tickets?.length">
                        <td colspan="5" class="px-4 py-8 text-center text-sm agent-text-subtle">{{ emptyLabel }}</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </section>
</template>
