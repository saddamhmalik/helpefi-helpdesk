<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import AdminLayout from '../../../../Layouts/AdminLayout.vue';
import PageHeader from '../../../../Components/PageHeader.vue';
import AppRowActions from '../../../../Components/AppRowActions.vue';
import AppEditAction from '../../../../Components/AppEditAction.vue';
import AppDeleteAction from '../../../../Components/AppDeleteAction.vue';
import { usePlatformAdmin } from '../../../../composables/usePlatformAdmin.js';
import { useI18n } from 'vue-i18n';
import { useDateTime } from '../../../../composables/useDateTime.js';

defineProps({
    notices: { type: Array, default: () => [] },
    types: { type: Object, default: () => ({}) },
    audiences: { type: Object, default: () => ({}) },
    priorities: { type: Object, default: () => ({}) },
});

const { formatDateTime } = useDateTime();

const { t } = useI18n();

const { can } = usePlatformAdmin();
const canManage = can('notices.manage');

const publish = (notice) => {
    const target = notice.target_scope === 'all'
        ? t('central.notice_confirm_target_all')
        : t('central.notice_confirm_target_selected');

    if (confirm(t('central.notice_confirm_publish', { title: notice.title, target }))) {
        router.post(`/admin/notices/${notice.id}/publish`);
    }
};

const deactivate = (notice) => {
    if (confirm(t('central.notice_confirm_deactivate', { title: notice.title }))) {
        router.post(`/admin/notices/${notice.id}/deactivate`);
    }
};

const destroy = (notice) => {
    if (confirm(t('central.notice_confirm_delete', { title: notice.title }))) {
        router.delete(`/admin/notices/${notice.id}`);
    }
};

const typeLabel = (type, types) => types[type] ?? type;
const audienceLabel = (audience, audiences) => audiences[audience] ?? audience;
const priorityClass = (priority) => {
    if (priority === 'high') {
        return 'bg-red-100 text-red-700';
    }

    if (priority === 'low') {
        return 'bg-slate-100 text-slate-600';
    }

    return 'bg-blue-100 text-blue-700';
};

const statusClass = (notice) => {
    if (notice.schedule_state === 'live') {
        return 'bg-emerald-100 text-emerald-700';
    }

    if (notice.schedule_state === 'scheduled') {
        return 'bg-sky-100 text-sky-700';
    }

    if (notice.schedule_state === 'expired') {
        return 'bg-amber-100 text-amber-700';
    }

    if (notice.status === 'published') {
        return 'bg-amber-100 text-amber-700';
    }

    return 'bg-slate-100 text-slate-600';
};

const statusLabel = (notice) => {
    if (notice.schedule_state === 'live') {
        return t('central.notice_status_live');
    }

    if (notice.schedule_state === 'scheduled') {
        return t('central.notice_status_scheduled');
    }

    if (notice.schedule_state === 'expired') {
        return t('central.notice_status_expired');
    }

    if (notice.status === 'published') {
        return t('central.notice_status_inactive');
    }

    return t('central.notice_status_draft');
};

const targetLabel = (notice) => {
    if (notice.target_scope === 'all') {
        return t('central.notice_target_all');
    }

    const count = notice.tenants?.length ?? notice.tenant_ids?.length ?? 0;

    return count === 1
        ? t('central.notice_target_one', { count })
        : t('central.notice_target_many', { count });
};
</script>

<template>
    <Head :title="$t('central.platform_notices')" />
    <AdminLayout>
        <div class="mx-auto max-w-6xl px-4 py-8 sm:px-6">
            <PageHeader :title="$t('central.platform_notices')" :description="$t('central.push_maintenance_alerts_offers_and_announcements_to_workspace_admins_o')">
                <template v-if="canManage" #actions>
                    <Link href="/admin/notices/create" class="rounded-xl bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-blue-700">
                        {{ $t('central.new_notice') }}
                    </Link>
                </template>
            </PageHeader>

            <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50">
                        <tr>
                            <th class="px-5 py-3.5 text-left font-medium text-slate-600">{{ $t('central.notice') }}</th>
                            <th class="px-5 py-3.5 text-left font-medium text-slate-600">{{ $t('central.audience') }}</th>
                            <th class="px-5 py-3.5 text-left font-medium text-slate-600">{{ $t('central.target') }}</th>
                            <th class="px-5 py-3.5 text-left font-medium text-slate-600">{{ $t('central.schedule') }}</th>
                            <th class="px-5 py-3.5 text-left font-medium text-slate-600">{{ $t('central.status') }}</th>
                            <th class="px-5 py-3.5 text-right font-medium text-slate-600">{{ $t('central.actions') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr v-if="!notices.length">
                            <td colspan="6" class="px-5 py-10 text-center text-slate-500">{{ $t('central.no_notices_yet') }}</td>
                        </tr>
                        <tr v-for="notice in notices" :key="notice.id" class="hover:bg-slate-50/80">
                            <td class="px-5 py-4 align-top">
                                <div class="flex items-start gap-3">
                                    <img
                                        v-if="notice.image_url"
                                        :src="notice.image_url"
                                        :alt="notice.title"
                                        class="h-12 w-12 shrink-0 rounded-lg border border-slate-200 object-cover"
                                    />
                                    <div class="min-w-0">
                                        <p class="font-medium text-slate-900">{{ notice.title }}</p>
                                        <div class="mt-1 flex flex-wrap items-center gap-2">
                                            <span class="inline-flex rounded-full px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide" :class="priorityClass(notice.priority)">
                                                {{ priorities[notice.priority] ?? notice.priority }}
                                            </span>
                                            <span class="text-xs text-slate-500">{{ typeLabel(notice.notice_type, types) }}</span>
                                        </div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-5 py-4 align-top text-slate-600">{{ audienceLabel(notice.audience, audiences) }}</td>
                            <td class="px-5 py-4 align-top text-slate-600">{{ targetLabel(notice) }}</td>
                            <td class="px-5 py-4 align-top text-xs text-slate-500">
                                <p v-if="notice.starts_at">{{ $t('central.notice_schedule_from', { date: formatDateTime(notice.starts_at) }) }}</p>
                                <p v-if="notice.ends_at">{{ $t('central.notice_schedule_until', { date: formatDateTime(notice.ends_at) }) }}</p>
                                <p v-if="!notice.starts_at && !notice.ends_at">{{ $t('central.always_on_when_live') }}</p>
                            </td>
                            <td class="px-5 py-4 align-top">
                                <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-medium" :class="statusClass(notice)">
                                    {{ statusLabel(notice) }}
                                </span>
                            </td>
                            <td class="px-5 py-4 align-top text-right">
                                <div class="flex items-center justify-end gap-2">
                                    <AppRowActions>
                                        <AppEditAction :label="$t('common.edit')" :href="`/admin/notices/${notice.id}/edit`" />
                                        <AppDeleteAction
                                            v-if="canManage"
                                            :label="$t('central.delete')"
                                            @click="destroy(notice)"
                                        />
                                    </AppRowActions>
                                    <button
                                        v-if="canManage && notice.status === 'draft'"
                                        type="button"
                                        class="rounded-lg px-3 py-1.5 text-xs font-medium text-emerald-700 hover:bg-emerald-50"
                                        @click="publish(notice)"
                                    >{{ $t('central.publish') }}</button>
                                    <button
                                        v-if="canManage && notice.status === 'published' && notice.is_active"
                                        type="button"
                                        class="rounded-lg px-3 py-1.5 text-xs font-medium text-amber-700 hover:bg-amber-50"
                                        @click="deactivate(notice)"
                                    >{{ $t('central.deactivate') }}</button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>
