<script setup>
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import AdminLayout from '../../../../Layouts/AdminLayout.vue';
import PageHeader from '../../../../Components/PageHeader.vue';
import PaginationLinks from '../../../../Components/PaginationLinks.vue';
import { adminInputClass, usePlatformAdmin } from '../../../../composables/usePlatformAdmin.js';
import { useI18n } from 'vue-i18n';
import { useDateTime } from '../../../../composables/useDateTime.js';

const props = defineProps({
    submissions: Object,
    filters: Object,
    types: Object,
    statuses: Object,
    summary: Object,
});

const { formatDateTime } = useDateTime();

const { t } = useI18n();

const { can } = usePlatformAdmin();

const filterForm = useForm({
    type: props.filters.type ?? '',
    status: props.filters.status ?? '',
    search: props.filters.search ?? '',
    tenant_id: props.filters.tenant_id ?? '',
});

const applyFilters = () => {
    router.get('/admin/feedback', filterForm.data(), { preserveState: true, preserveScroll: true });
};

const clearFilters = () => {
    filterForm.type = '';
    filterForm.status = '';
    filterForm.search = '';
    filterForm.tenant_id = '';
    applyFilters();
};

const typeLabel = (type) => props.types?.[type] ?? type;
const statusLabel = (status) => props.statuses?.[status] ?? status;

const workspaceName = (submission) => submission.tenant?.name ?? submission.tenant_name;
const workspaceSlug = (submission) => submission.tenant?.slug ?? null;

const summaryCards = computed(() => {
    const cards = [];

    Object.entries(props.summary ?? {}).forEach(([type, statuses]) => {
        Object.entries(statuses).forEach(([status, total]) => {
            cards.push({
                key: `${type}-${status}`,
                type,
                status,
                total,
            });
        });
    });

    return cards;
});

const updateStatus = (submission, status) => {
    router.put(`/admin/feedback/${submission.id}/status`, { status }, { preserveScroll: true });
};

const formatDate = (value) => {
    if (!value) {
        return '—';
    }

    return formatDateTime(value);
};
</script>

<template>
    <Head :title="$t('central.feedback_feature_requests')" />
    <AdminLayout>
        <div class="mx-auto max-w-6xl px-4 py-8 sm:px-6">
        <PageHeader
            :title="$t('central.feedback_feature_requests')"
            :description="$t('central.feedback_feature_requests_description')"
        />

        <div v-if="summaryCards.length" class="mb-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <div
                v-for="card in summaryCards"
                :key="card.key"
                class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm"
            >
                <p class="text-xs font-medium uppercase tracking-wide text-slate-500">{{ typeLabel(card.type) }}</p>
                <p class="mt-1 text-sm font-medium text-slate-900">{{ statusLabel(card.status) }}</p>
                <p class="mt-2 text-2xl font-semibold text-slate-900">{{ card.total }}</p>
            </div>
        </div>

        <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
            <div class="mb-4 flex flex-wrap items-end gap-3">
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">{{ $t('central.type') }}</label>
                    <select v-model="filterForm.type" :class="adminInputClass">
                        <option value="">{{ $t('central.all') }}</option>
                        <option v-for="(label, value) in types" :key="value" :value="value">{{ label }}</option>
                    </select>
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">{{ $t('central.status') }}</label>
                    <select v-model="filterForm.status" :class="adminInputClass">
                        <option value="">{{ $t('central.all') }}</option>
                        <option v-for="(label, value) in statuses" :key="value" :value="value">{{ label }}</option>
                    </select>
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">{{ $t('common.search') }}</label>
                    <input v-model="filterForm.search" type="text" :class="adminInputClass" :placeholder="$t('central.subject_body_user_workspace')" />
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">{{ $t('central.workspace_id') }}</label>
                    <input v-model="filterForm.tenant_id" type="text" :class="adminInputClass" :placeholder="$t('central.tenant_uuid')" />
                </div>
                <button type="button" class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50" @click="applyFilters">{{ $t('central.filter') }}</button>
                <button type="button" class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50" @click="clearFilters">{{ $t('central.clear') }}</button>
            </div>

            <div v-if="!submissions.data.length" class="rounded-lg border border-dashed border-slate-300 px-6 py-12 text-center text-sm text-slate-500">
                {{ $t('central.no_feedback_submissions') }}
            </div>

            <div v-else class="space-y-4">
                <article
                    v-for="submission in submissions.data"
                    :key="submission.id"
                    class="rounded-xl border border-slate-200 p-5 transition hover:border-slate-300"
                >
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="rounded-full bg-blue-50 px-2.5 py-0.5 text-xs font-semibold text-blue-700">
                                    {{ typeLabel(submission.type) }}
                                </span>
                                <span
                                    class="rounded-full px-2.5 py-0.5 text-xs font-semibold"
                                    :class="submission.status === 'open' ? 'bg-amber-50 text-amber-700' : submission.status === 'reviewed' ? 'bg-sky-50 text-sky-700' : 'bg-slate-100 text-slate-600'"
                                >
                                    {{ statusLabel(submission.status) }}
                                </span>
                            </div>

                            <div class="mt-3 inline-flex max-w-full flex-col rounded-lg border border-violet-200 bg-violet-50/70 px-3 py-2">
                                <span class="text-[10px] font-semibold uppercase tracking-wide text-violet-600">{{ $t('central.workspace') }}</span>
                                <span class="truncate text-sm font-semibold text-violet-950">{{ workspaceName(submission) }}</span>
                                <span v-if="workspaceSlug(submission)" class="truncate font-mono text-xs text-violet-700/80">{{ workspaceSlug(submission) }}</span>
                            </div>

                            <h3 class="mt-3 text-base font-semibold text-slate-900">
                                <Link :href="`/admin/feedback/${submission.id}`" class="hover:text-blue-700">
                                    {{ submission.subject }}
                                </Link>
                            </h3>

                            <p class="mt-1 text-sm text-slate-500">
                                {{ submission.user_name }} ({{ submission.user_email }})
                                · {{ formatDate(submission.created_at) }}
                            </p>
                        </div>

                        <div class="flex shrink-0 flex-col items-end gap-2">
                            <Link
                                :href="`/admin/feedback/${submission.id}`"
                                class="rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs font-medium text-slate-700 transition hover:bg-slate-50"
                            >
                                {{ $t('central.view_details') }}
                            </Link>

                            <div v-if="can('feedback.manage')" class="flex flex-wrap justify-end gap-2">
                                <button
                                    v-for="(label, value) in statuses"
                                    :key="value"
                                    type="button"
                                    class="rounded-lg border px-3 py-1.5 text-xs font-medium transition"
                                    :class="submission.status === value ? 'border-blue-500 bg-blue-50 text-blue-700' : 'border-slate-300 text-slate-600 hover:bg-slate-50'"
                                    @click="updateStatus(submission, value)"
                                >
                                    {{ label }}
                                </button>
                            </div>
                        </div>
                    </div>

                    <p class="mt-4 line-clamp-2 whitespace-pre-wrap text-sm leading-relaxed text-slate-700">{{ submission.body }}</p>
                </article>
            </div>

            <PaginationLinks v-if="submissions.data.length" class="mt-6" :links="submissions.links" />
        </div>
        </div>
    </AdminLayout>
</template>
