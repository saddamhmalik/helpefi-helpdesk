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
    if (value.includes('open')) return 'bg-emerald-100 text-emerald-800';
    if (value.includes('pending')) return 'bg-amber-100 text-amber-800';
    if (value.includes('closed') || value.includes('resolved')) return 'bg-slate-200 text-slate-700';

    return 'bg-slate-100 text-slate-700';
};

const priorityBadgeClass = (name) => {
    const value = (name || '').toLowerCase();
    if (value.includes('urgent') || value.includes('critical')) return 'bg-red-100 text-red-800';
    if (value.includes('high')) return 'bg-orange-100 text-orange-800';
    if (value.includes('low')) return 'bg-slate-100 text-slate-600';

    return 'bg-blue-100 text-blue-800';
};
</script>

<template>
    <section class="overflow-hidden rounded-2xl border border-white/60 bg-white/90 shadow-sm ring-1 ring-slate-100 backdrop-blur">
        <div class="border-b border-slate-100 px-6 py-4">
            <h2 class="text-base font-semibold text-slate-900">{{ title }}</h2>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-100">
                <thead class="bg-slate-50/80">
                    <tr>
                        <th class="px-4 py-2.5 text-left text-xs font-medium uppercase text-slate-500">{{ $t('components.ticket') }}</th>
                        <th class="px-4 py-2.5 text-left text-xs font-medium uppercase text-slate-500">{{ $t('components.status') }}</th>
                        <th class="px-4 py-2.5 text-left text-xs font-medium uppercase text-slate-500">{{ $t('components.priority') }}</th>
                        <th class="px-4 py-2.5 text-left text-xs font-medium uppercase text-slate-500">{{ $t('components.routing') }}</th>
                        <th class="px-4 py-2.5 text-left text-xs font-medium uppercase text-slate-500">{{ $t('components.assignee') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <tr v-for="ticket in tickets" :key="ticket.id" class="hover:bg-slate-50/60">
                        <td class="px-4 py-3">
                            <Link :href="`/tickets/${ticket.id}`" class="block">
                                <span class="text-sm font-medium text-blue-600 hover:text-blue-700">{{ ticket.number }}</span>
                                <span class="mt-0.5 block max-w-xs truncate text-xs text-slate-500">{{ ticket.subject }}</span>
                            </Link>
                        </td>
                        <td class="px-4 py-3">
                            <span class="rounded-full px-2 py-0.5 text-xs font-medium" :class="statusBadgeClass(ticket.status?.name)">{{ ticket.status?.name || $t('components.em_dash') }}</span>
                        </td>
                        <td class="px-4 py-3">
                            <span class="rounded-full px-2 py-0.5 text-xs font-medium" :class="priorityBadgeClass(ticket.priority?.name)">{{ ticket.priority?.name || $t('components.em_dash') }}</span>
                        </td>
                        <td class="px-4 py-3 text-xs text-slate-600">
                            <span v-if="ticket.department">{{ ticket.department.name }}</span>
                            <span v-if="ticket.team"> · {{ ticket.team.name }}</span>
                            <span v-if="!ticket.department && !ticket.team">{{ $t('components.em_dash') }}</span>
                        </td>
                        <td class="px-4 py-3 text-sm text-slate-600">{{ ticket.assignee?.name || $t('components.unassigned') }}</td>
                    </tr>
                    <tr v-if="!tickets?.length">
                        <td colspan="5" class="px-4 py-8 text-center text-sm text-slate-400">{{ emptyLabel }}</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </section>
</template>
