<script setup>
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import AgentLayout from '../../../Layouts/AgentLayout.vue';
import ServiceDeskNav from '../../../Components/ServiceDeskNav.vue';
import PageHeader from '../../../Components/PageHeader.vue';
import DataTable from '../../../Components/DataTable.vue';
import PaginationLinks from '../../../Components/PaginationLinks.vue';
import StatusBadge from '../../../Components/StatusBadge.vue';
import { useI18n } from 'vue-i18n';

const props = defineProps({
    approvals: Object,
    filters: Object,
    pendingMine: Number,
    currentUserId: Number,
});

const { t } = useI18n();

const statusClass = (status) => {
    if (status === 'approved') {
        return 'bg-emerald-100 text-emerald-800 dark:text-emerald-200';
    }

    if (status === 'rejected') {
        return 'bg-red-100 text-red-800 dark:text-red-200';
    }

    return 'bg-amber-100 text-amber-800';
};

const currentApprover = (approval) => {
    const step = (approval.steps ?? []).find((item) => item.step_order === approval.current_step);

    return step?.approver?.name ?? '—';
};

const canDecide = (approval) => {
    const step = (approval.steps ?? []).find((item) => item.step_order === approval.current_step);

    return approval.status === 'pending'
        && step?.status === 'pending'
        && Number(step?.approver?.id) === Number(props.currentUserId);
};

const decisionForm = useForm({ note: '' });

const approve = (approvalId) => {
    decisionForm.post(`/service-desk/approvals/${approvalId}/approve`, {
        preserveScroll: true,
        onSuccess: () => decisionForm.reset(),
    });
};

const reject = (approvalId) => {
    decisionForm.post(`/service-desk/approvals/${approvalId}/reject`, {
        preserveScroll: true,
        onSuccess: () => decisionForm.reset(),
    });
};

const applyFilter = (mine) => {
    router.get('/service-desk/approvals', {
        mine: mine || undefined,
        status: props.filters.status || undefined,
    }, { preserveState: true, replace: true });
};
</script>

<template>
    <Head :title="$t('service_desk.approvals')" />
    <AgentLayout>
        <PageHeader :title="$t('service_desk.approvals')" :description="$t('service_desk.review_and_decide_on_service_catalog_and_change_requests')">
            <template #description>
                {{ $t('service_desk.waiting_on_you', { count: pendingMine }) }}
            </template>
        </PageHeader>

        <ServiceDeskNav />

        <div class="mb-4 flex flex-wrap gap-2">
            <button
                type="button"
                class="rounded-lg px-3 py-1.5 text-sm font-medium transition"
                :class="!filters.mine ? 'bg-slate-900 text-white' : 'bg-white dark:bg-slate-900 text-slate-600 dark:text-slate-400 ring-1 ring-slate-200 dark:ring-slate-700'"
                @click="applyFilter(false)"
            >{{ $t('service_desk.all_pending') }}</button>
            <button
                type="button"
                class="rounded-lg px-3 py-1.5 text-sm font-medium transition"
                :class="filters.mine ? 'bg-slate-900 text-white' : 'bg-white dark:bg-slate-900 text-slate-600 dark:text-slate-400 ring-1 ring-slate-200 dark:ring-slate-700'"
                @click="applyFilter(true)"
            >{{ $t('service_desk.assigned_to_me') }}</button>
        </div>

        <DataTable>
            <template #head>
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">{{ $t('service_desk.request') }}</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">{{ $t('service_desk.requester') }}</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">{{ $t('service_desk.status') }}</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">{{ $t('service_desk.current_approver') }}</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">{{ $t('service_desk.actions') }}</th>
                </tr>
            </template>
            <template #body>
                <tr v-if="approvals.data.length === 0">
                    <td colspan="5" class="px-4 py-10 text-center text-sm text-slate-500 dark:text-slate-400">{{ $t('service_desk.no_approval_requests') }}</td>
                </tr>
                <tr v-for="approval in approvals.data" :key="approval.id" class="border-t border-slate-100 dark:border-slate-800">
                    <td class="px-4 py-3">
                        <Link :href="`/tickets/${approval.ticket_id}`" class="font-medium text-slate-900 dark:text-slate-100 hover:text-blue-600 dark:hover:text-blue-400">
                            {{ approval.subject }}
                        </Link>
                        <p class="text-xs text-slate-500 dark:text-slate-400">{{ approval.ticket?.number }}</p>
                    </td>
                    <td class="px-4 py-3 text-sm text-slate-700 dark:text-slate-300">{{ approval.ticket?.contact?.name || '—' }}</td>
                    <td class="px-4 py-3">
                        <span class="rounded-full px-2 py-0.5 text-xs font-medium capitalize" :class="statusClass(approval.status)">
                            {{ approval.status }}
                        </span>
                    </td>
                    <td class="px-4 py-3 text-sm text-slate-700 dark:text-slate-300">{{ currentApprover(approval) }}</td>
                    <td class="px-4 py-3">
                        <div v-if="canDecide(approval)" class="flex flex-wrap gap-2">
                            <button
                                type="button"
                                class="rounded-lg bg-emerald-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-emerald-700"
                                @click="approve(approval.id)"
                            >{{ $t('service_desk.approve') }}</button>
                            <button
                                type="button"
                                class="rounded-lg bg-red-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-red-700"
                                @click="reject(approval.id)"
                            >{{ $t('service_desk.reject') }}</button>
                        </div>
                        <Link v-else :href="`/tickets/${approval.ticket_id}`" class="text-sm text-blue-600 hover:text-blue-700 dark:hover:text-blue-300 dark:text-blue-300">{{ $t('service_desk.view_ticket') }}</Link>
                    </td>
                </tr>
            </template>
        </DataTable>

        <PaginationLinks :links="approvals.links" class="mt-4" />
    </AgentLayout>
</template>
