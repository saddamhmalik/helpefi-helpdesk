<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import AdminLayout from '../../../../Layouts/AdminLayout.vue';
import PageHeader from '../../../../Components/PageHeader.vue';
import { usePlatformAdmin } from '../../../../composables/usePlatformAdmin.js';
import { useI18n } from 'vue-i18n';
import { useDateTime } from '../../../../composables/useDateTime.js';

const props = defineProps({
    submission: Object,
    types: Object,
    statuses: Object,
});

const { formatDateTime } = useDateTime();
const { t } = useI18n();
const { can } = usePlatformAdmin();

const typeLabel = (type) => props.types?.[type] ?? type;
const statusLabel = (status) => props.statuses?.[status] ?? status;

const workspaceName = () => props.submission.tenant?.name ?? props.submission.tenant_name;
const workspaceSlug = () => props.submission.tenant?.slug ?? null;

const updateStatus = (status) => {
    router.put(`/admin/feedback/${props.submission.id}/status`, { status }, { preserveScroll: true });
};
</script>

<template>
    <Head :title="submission.subject" />
    <AdminLayout>
        <div class="mx-auto max-w-6xl px-4 py-8 sm:px-6">
        <PageHeader :title="submission.subject" :description="typeLabel(submission.type)">
            <template #actions>
                <Link
                    href="/admin/feedback"
                    class="rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 px-4 py-2 text-sm font-medium text-slate-700 dark:text-slate-300 transition hover:bg-slate-50 dark:bg-slate-950 dark:hover:bg-slate-800"
                >
                    {{ $t('central.back_to_feedback') }}
                </Link>
            </template>
        </PageHeader>

        <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_18rem]">
            <div class="space-y-6">
                <div class="rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 p-6 shadow-sm">
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="rounded-full bg-blue-50 dark:bg-blue-950/40 px-2.5 py-0.5 text-xs font-semibold text-blue-700 dark:text-blue-300">
                            {{ typeLabel(submission.type) }}
                        </span>
                        <span
                            class="rounded-full px-2.5 py-0.5 text-xs font-semibold"
                            :class="submission.status === 'open' ? 'bg-amber-50 dark:bg-amber-950/40 text-amber-700 dark:text-amber-300' : submission.status === 'reviewed' ? 'bg-sky-50 text-sky-700' : 'bg-slate-100 dark:bg-slate-900 text-slate-600 dark:text-slate-400'"
                        >
                            {{ statusLabel(submission.status) }}
                        </span>
                    </div>

                    <p class="mt-6 whitespace-pre-wrap text-sm leading-relaxed text-slate-800 dark:text-slate-200">{{ submission.body }}</p>
                </div>

                <div v-if="submission.user_agent" class="rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 p-6 shadow-sm">
                    <h2 class="text-sm font-semibold text-slate-900 dark:text-slate-100">{{ $t('central.user_agent') }}</h2>
                    <p class="mt-2 break-all text-sm text-slate-600 dark:text-slate-400">{{ submission.user_agent }}</p>
                </div>
            </div>

            <aside class="space-y-4">
                <div class="rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 p-5 shadow-sm">
                    <h2 class="text-sm font-semibold text-slate-900 dark:text-slate-100">{{ $t('central.workspace') }}</h2>
                    <p class="mt-2 text-base font-medium text-slate-900 dark:text-slate-100">{{ workspaceName() }}</p>
                    <p v-if="workspaceSlug()" class="mt-0.5 font-mono text-xs text-slate-500 dark:text-slate-400">{{ workspaceSlug() }}</p>
                    <p class="mt-2 break-all font-mono text-[11px] text-slate-400 dark:text-slate-500">{{ submission.tenant_id }}</p>
                    <Link
                        :href="`/admin/tenants?q=${encodeURIComponent(workspaceSlug() || workspaceName())}`"
                        class="mt-3 inline-flex text-sm font-medium text-blue-600 hover:text-blue-700 dark:hover:text-blue-300 dark:text-blue-300"
                    >
                        {{ $t('central.open_workspace') }}
                    </Link>
                </div>

                <div class="rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 p-5 shadow-sm">
                    <h2 class="text-sm font-semibold text-slate-900 dark:text-slate-100">{{ $t('central.submitted_by') }}</h2>
                    <p class="mt-2 text-sm font-medium text-slate-900 dark:text-slate-100">{{ submission.user_name }}</p>
                    <a :href="`mailto:${submission.user_email}`" class="mt-0.5 block text-sm text-blue-600 hover:text-blue-700 dark:hover:text-blue-300 dark:text-blue-300">
                        {{ submission.user_email }}
                    </a>
                    <p v-if="submission.user_id" class="mt-2 text-xs text-slate-500 dark:text-slate-400">{{ $t('central.user_id') }}: {{ submission.user_id }}</p>
                </div>

                <div class="rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 p-5 shadow-sm">
                    <h2 class="text-sm font-semibold text-slate-900 dark:text-slate-100">{{ $t('central.details') }}</h2>
                    <dl class="mt-3 space-y-3 text-sm">
                        <div>
                            <dt class="text-slate-500 dark:text-slate-400">{{ $t('central.submitted') }}</dt>
                            <dd class="font-medium text-slate-900 dark:text-slate-100">{{ formatDateTime(submission.created_at) }}</dd>
                        </div>
                        <div v-if="submission.updated_at !== submission.created_at">
                            <dt class="text-slate-500 dark:text-slate-400">{{ $t('central.last_updated') }}</dt>
                            <dd class="font-medium text-slate-900 dark:text-slate-100">{{ formatDateTime(submission.updated_at) }}</dd>
                        </div>
                        <div v-if="submission.ip_address">
                            <dt class="text-slate-500 dark:text-slate-400">{{ $t('central.ip_address') }}</dt>
                            <dd class="font-mono text-slate-900 dark:text-slate-100">{{ submission.ip_address }}</dd>
                        </div>
                    </dl>
                </div>

                <div v-if="can('feedback.manage')" class="rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 p-5 shadow-sm">
                    <h2 class="text-sm font-semibold text-slate-900 dark:text-slate-100">{{ $t('central.status') }}</h2>
                    <div class="mt-3 flex flex-wrap gap-2">
                        <button
                            v-for="(label, value) in statuses"
                            :key="value"
                            type="button"
                            class="rounded-lg border px-3 py-1.5 text-xs font-medium transition"
                            :class="submission.status === value ? 'border-blue-500 bg-blue-50 dark:bg-blue-950/40 text-blue-700 dark:text-blue-300' : 'border-slate-300 dark:border-slate-700 text-slate-600 dark:text-slate-400 hover:bg-slate-50 dark:bg-slate-950 dark:hover:bg-slate-800'"
                            @click="updateStatus(value)"
                        >
                            {{ label }}
                        </button>
                    </div>
                </div>
            </aside>
        </div>
        </div>
    </AdminLayout>
</template>
