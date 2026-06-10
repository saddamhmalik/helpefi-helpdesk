<script setup>
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { ref } from 'vue';
import AdminLayout from '../../../../Layouts/AdminLayout.vue';
import PageHeader from '../../../../Components/PageHeader.vue';
import PaginationLinks from '../../../../Components/PaginationLinks.vue';
import AppConfirmDialog from '../../../../Components/AppConfirmDialog.vue';
import AppRowActions from '../../../../Components/AppRowActions.vue';
import AppDeleteAction from '../../../../Components/AppDeleteAction.vue';
import { adminInputClass, usePlatformAdmin } from '../../../../composables/usePlatformAdmin.js';
import { useConfirmDialog } from '../../../../composables/useConfirmDialog.js';
import { useI18n } from 'vue-i18n';
import { useDateTime } from '../../../../composables/useDateTime.js';

const props = defineProps({
    backups: Object,
    workspaces: Array,
    retention_days: Number,
    schedule: Object,
    schedule_options: Object,
});

const { formatDateTime } = useDateTime();

const { t } = useI18n();

const page = usePage();
const { can } = usePlatformAdmin();
const selectedTenant = ref('');
const { state: confirm, ask: askConfirm, close: closeConfirm, confirm: onConfirm } = useConfirmDialog();

const backupForm = useForm({
    scope: 'central',
    tenant_id: '',
});

const scheduleForm = useForm({
    enabled: props.schedule?.enabled ?? false,
    frequency: props.schedule?.frequency ?? 'daily',
    weekday: props.schedule?.weekday ?? 1,
    time: props.schedule?.time ?? '02:00',
});

const saveSchedule = () => {
    scheduleForm.put('/admin/backups/schedule', {
        preserveScroll: true,
    });
};

const queueBackup = (scope) => {
    backupForm.scope = scope;
    backupForm.tenant_id = scope === 'tenant' ? selectedTenant.value : '';

    if (scope === 'tenant' && !backupForm.tenant_id) {
        return;
    }

    backupForm.post('/admin/backups', {
        preserveScroll: true,
        onSuccess: () => {
            if (scope === 'tenant') {
                selectedTenant.value = '';
            }
        },
    });
};

const deleteBackup = (backup) => {
    askConfirm({
        title: t('central.delete_backup'),
        message: 'Remove this backup file permanently?',
        confirmLabel: 'Delete',
        variant: 'danger',
        action: () => router.delete(`/admin/backups/${backup.id}`, { preserveScroll: true }),
    });
};

const formatSize = (bytes) => {
    if (!bytes) {
        return '—';
    }

    if (bytes < 1024) {
        return `${bytes} B`;
    }

    if (bytes < 1024 * 1024) {
        return `${(bytes / 1024).toFixed(1)} KB`;
    }

    return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
};

const scopeLabel = (backup) => {
    if (backup.scope === 'central') {
        return 'Central platform';
    }

    return backup.tenant?.name ?? backup.tenant_id ?? 'Workspace';
};

const statusClass = (status) => {
    if (status === 'completed') {
        return 'bg-emerald-100 text-emerald-700 ring-emerald-200';
    }

    if (status === 'failed') {
        return 'bg-red-100 text-red-700 ring-red-200';
    }

    if (status === 'running') {
        return 'bg-blue-100 text-blue-700 ring-blue-200';
    }

    return 'bg-amber-100 text-amber-700 ring-amber-200';
};
</script>

<template>
    <Head :title="$t('central.backups')" />
    <AdminLayout>
        <PageHeader
            :title="$t('central.backups')"
            :description="`Protect customer data with database snapshots. Completed backups are kept for ${retention_days} days.`"
        />

        <div
            v-if="page.props.flash?.success"
            class="mb-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800"
        >
            {{ page.props.flash.success }}
        </div>

        <div
            v-if="!can('backups.manage') && schedule"
            class="mb-6 rounded-xl border border-slate-200 bg-white px-5 py-4 text-sm text-slate-600 shadow-sm"
        >
            {{ schedule.summary }}
        </div>

        <div v-if="can('backups.manage')" class="mb-6 rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <h2 class="text-sm font-semibold text-slate-900">{{ $t('central.automatic_backups') }}</h2>
                    <p class="mt-1 text-sm text-slate-500">
                        {{ $t('central.queue_central_and_workspace_database_snapshots_on_a_schedule_expired_b') }}
                    </p>
                    <p class="mt-2 text-xs text-slate-500">{{ schedule?.summary }}</p>
                </div>
                <label class="inline-flex items-center gap-2 text-sm text-slate-700">
                    <input
                        v-model="scheduleForm.enabled"
                        type="checkbox"
                        class="rounded border-slate-300"
                    />
                    Enable automatic backups
                </label>
            </div>

            <form class="mt-4 grid gap-4 md:grid-cols-4" @submit.prevent="saveSchedule">
                <div>
                    <label class="mb-1 block text-xs font-medium text-slate-500">{{ $t('central.frequency') }}</label>
                    <select v-model="scheduleForm.frequency" :class="adminInputClass" :disabled="!scheduleForm.enabled">
                        <option
                            v-for="option in schedule_options?.frequencies ?? []"
                            :key="option.value"
                            :value="option.value"
                        >
                            {{ option.label }}
                        </option>
                    </select>
                </div>

                <div v-if="scheduleForm.frequency === 'weekly'">
                    <label class="mb-1 block text-xs font-medium text-slate-500">{{ $t('central.day') }}</label>
                    <select
                        v-model="scheduleForm.weekday"
                        :class="adminInputClass"
                        :disabled="!scheduleForm.enabled"
                    >
                        <option
                            v-for="option in schedule_options?.weekdays ?? []"
                            :key="option.value"
                            :value="option.value"
                        >
                            {{ option.label }}
                        </option>
                    </select>
                </div>

                <div>
                    <label class="mb-1 block text-xs font-medium text-slate-500">{{ $t('central.time') }}</label>
                    <input
                        v-model="scheduleForm.time"
                        type="time"
                        :class="adminInputClass"
                        :disabled="!scheduleForm.enabled"
                    />
                </div>

                <div class="flex items-end">
                    <button
                        type="submit"
                        class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 disabled:opacity-60"
                        :disabled="scheduleForm.processing"
                    >{{ $t('central.save_schedule') }}</button>
                </div>
            </form>
        </div>

        <div v-if="can('backups.manage')" class="mb-6 grid gap-4 lg:grid-cols-3">
            <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                <h2 class="text-sm font-semibold text-slate-900">{{ $t('central.central_platform') }}</h2>
                <p class="mt-1 text-sm text-slate-500">{{ $t('central.backup_tenants_subscriptions_payments_and_platform_settings') }}</p>
                <button
                    type="button"
                    class="mt-4 rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 disabled:opacity-60"
                    :disabled="backupForm.processing"
                    @click="queueBackup('central')"
                >{{ $t('central.backup_central_db') }}</button>
            </div>

            <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                <h2 class="text-sm font-semibold text-slate-900">{{ $t('central.all_workspaces') }}</h2>
                <p class="mt-1 text-sm text-slate-500">{{ $t('central.queue_a_database_snapshot_for_every_customer_workspace') }}</p>
                <button
                    type="button"
                    class="mt-4 rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50 disabled:opacity-60"
                    :disabled="backupForm.processing || !workspaces.length"
                    @click="queueBackup('all_tenants')"
                >{{ $t('central.backup_all_workspaces') }}</button>
            </div>

            <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                <h2 class="text-sm font-semibold text-slate-900">{{ $t('central.single_workspace') }}</h2>
                <p class="mt-1 text-sm text-slate-500">{{ $t('central.backup_tickets_customers_and_settings_for_one_workspace') }}</p>
                <select v-model="selectedTenant" :class="[adminInputClass, 'mt-4']">
                    <option value="">{{ $t('central.choose_workspace') }}</option>
                    <option v-for="workspace in workspaces" :key="workspace.id" :value="workspace.id">
                        {{ workspace.name }} ({{ workspace.slug }})
                    </option>
                </select>
                <button
                    type="button"
                    class="mt-3 rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50 disabled:opacity-60"
                    :disabled="backupForm.processing || !selectedTenant"
                    @click="queueBackup('tenant')"
                >{{ $t('central.backup_workspace') }}</button>
            </div>
        </div>

        <div class="rounded-xl border border-slate-200 bg-white shadow-sm">
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-50">
                        <tr class="text-left text-slate-500">
                            <th class="px-4 py-3 font-medium">{{ $t('central.created') }}</th>
                            <th class="px-4 py-3 font-medium">{{ $t('central.scope') }}</th>
                            <th class="px-4 py-3 font-medium">{{ $t('central.status') }}</th>
                            <th class="px-4 py-3 font-medium">{{ $t('central.size') }}</th>
                            <th class="px-4 py-3 font-medium">{{ $t('central.created_by') }}</th>
                            <th class="px-4 py-3 font-medium text-right">{{ $t('central.actions') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr v-for="backup in backups.data" :key="backup.id">
                            <td class="px-4 py-3 text-slate-600">{{ formatDateTime(backup.created_at) }}</td>
                            <td class="px-4 py-3 text-slate-900">{{ scopeLabel(backup) }}</td>
                            <td class="px-4 py-3">
                                <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium ring-1 ring-inset" :class="statusClass(backup.status)">
                                    {{ backup.status }}
                                </span>
                                <p v-if="backup.error_message" class="mt-1 text-xs text-red-600">{{ backup.error_message }}</p>
                            </td>
                            <td class="px-4 py-3 text-slate-600">{{ formatSize(backup.size_bytes) }}</td>
                            <td class="px-4 py-3 text-slate-600">{{ backup.creator?.name ?? backup.creator?.email ?? '—' }}</td>
                            <td class="px-4 py-3 text-right">
                                <div class="flex justify-end gap-2">
                                    <a
                                        v-if="backup.status === 'completed'"
                                        :href="`/admin/backups/${backup.id}/download`"
                                        class="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50"
                                    >
                                        {{ $t('central.download') }}
                                    </a>
                                    <AppDeleteAction
                                        v-if="can('backups.manage')"
                                        :label="$t('central.delete')"
                                        @click="deleteBackup(backup)"
                                    />
                                </div>
                            </td>
                        </tr>
                        <tr v-if="!backups.data?.length">
                            <td colspan="6" class="px-4 py-12 text-center text-slate-500">No backups yet.</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div v-if="backups.links?.length > 3" class="border-t border-slate-100 px-4 py-3">
                <PaginationLinks
                    :links="backups.links"
                    :from="backups.from"
                    :to="backups.to"
                    :total="backups.total"
                />
            </div>
        </div>

        <AppConfirmDialog
            :open="confirm.open"
            :title="confirm.title"
            :message="confirm.message"
            :confirm-label="confirm.confirmLabel"
            :variant="confirm.variant"
            @close="closeConfirm"
            @confirm="onConfirm"
        />
    </AdminLayout>
</template>
