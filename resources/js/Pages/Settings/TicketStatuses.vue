<script setup>
import { router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import SettingsPage from '../../Components/SettingsPage.vue';
import AppModal from '../../Components/AppModal.vue';
import AppConfirmDialog from '../../Components/AppConfirmDialog.vue';
import AppRowActions from '../../Components/AppRowActions.vue';
import AppEditAction from '../../Components/AppEditAction.vue';
import AppDeleteAction from '../../Components/AppDeleteAction.vue';
import { useConfirmDialog } from '../../composables/useConfirmDialog.js';
import { formInputClass } from '../../composables/useFormControls.js';
import { useI18n } from 'vue-i18n';

const props = defineProps({
    statuses: Array,
});

const { t } = useI18n();

const colorOptions = ['blue', 'amber', 'green', 'slate', 'red', 'orange', 'purple', 'emerald'];
const protectedSlugs = ['open', 'closed'];

const showForm = ref(false);
const editingStatus = ref(null);
const { state: confirm, ask: askConfirm, close: closeConfirm, confirm: onConfirm } = useConfirmDialog();

const form = useForm({
    name: '',
    color: 'slate',
    is_closed: false,
    sort_order: null,
});

const openCreate = () => {
    editingStatus.value = null;
    form.defaults({ name: '', color: 'slate', is_closed: false, sort_order: null });
    form.reset();
    showForm.value = true;
};

const openEdit = (status) => {
    editingStatus.value = status;
    form.defaults({
        name: status.name,
        color: status.color,
        is_closed: status.is_closed,
        sort_order: status.sort_order,
    });
    form.reset();
    showForm.value = true;
};

const save = () => {
    if (editingStatus.value) {
        form.put(`/settings/ticket-statuses/${editingStatus.value.id}`, {
            preserveScroll: true,
            onSuccess: () => { showForm.value = false; },
        });
    } else {
        form.post('/settings/ticket-statuses', {
            preserveScroll: true,
            onSuccess: () => { showForm.value = false; form.reset(); },
        });
    }
};

const destroyStatus = (status) => {
    askConfirm({
        title: t('settings_ticket_statuses.delete_status'),
        message: `Remove "${status.name}"? Tickets must be reassigned first.`,
        confirmLabel: 'Delete',
        action: () => router.delete(`/settings/ticket-statuses/${status.id}`, { preserveScroll: true }),
    });
};

const canDelete = (status) => !protectedSlugs.includes(status.slug);
</script>

<template>
    <SettingsPage :title="$t('settings.ticket_statuses')" :description="$t('settings_ticket_statuses.customize_workflow_statuses_beyond_the_defaults')">
        <template #actions>
            <button type="button" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white transition hover:bg-blue-700" @click="openCreate">{{ $t('settings_ticket_statuses.add_status') }}</button>
        </template>

        <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="px-4 py-3">{{ $t('profile.name') }}</th>
                        <th class="px-4 py-3">{{ $t('settings_ticket_statuses.color') }}</th>
                        <th class="px-4 py-3">{{ $t('settings_ticket_statuses.closed') }}</th>
                        <th class="px-4 py-3">{{ $t('settings_ticket_statuses.order') }}</th>
                        <th class="px-4 py-3 text-right">{{ $t('settings_ticket_statuses.actions') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <tr v-for="status in statuses" :key="status.id">
                        <td class="px-4 py-3 font-medium text-slate-900">
                            {{ status.name }}
                            <span class="ml-1 text-xs text-slate-400">{{ status.slug }}</span>
                        </td>
                        <td class="px-4 py-3 capitalize text-slate-600">{{ status.color }}</td>
                        <td class="px-4 py-3 text-slate-600">{{ status.is_closed ? 'Yes' : 'No' }}</td>
                        <td class="px-4 py-3 text-slate-600">{{ status.sort_order }}</td>
                        <td class="px-4 py-3 text-right">
                            <AppRowActions>
                                <AppEditAction :label="$t('settings_ticket_statuses.edit')" @click="openEdit(status)" />
                                <AppDeleteAction
                                    v-if="canDelete(status)"
                                    :label="$t('settings_ticket_statuses.delete')"
                                    @click="destroyStatus(status)"
                                />
                            </AppRowActions>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <AppModal :show="showForm" @close="showForm = false">
            <form class="space-y-4" @submit.prevent="save">
                <h2 class="text-lg font-semibold text-slate-900">{{ editingStatus ? 'Edit status' : 'New status' }}</h2>
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">{{ $t('profile.name') }}</label>
                    <input v-model="form.name" type="text" :class="formInputClass" required />
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">{{ $t('settings_ticket_statuses.color') }}</label>
                    <select v-model="form.color" :class="formInputClass">
                        <option v-for="color in colorOptions" :key="color" :value="color">{{ color }}</option>
                    </select>
                </div>
                <div v-if="editingStatus">
                    <label class="mb-1 block text-sm font-medium text-slate-700">{{ $t('settings_ticket_statuses.sort_order') }}</label>
                    <input v-model.number="form.sort_order" type="number" min="0" :class="formInputClass" />
                </div>
                <label class="flex items-center gap-2 text-sm text-slate-700">
                    <input v-model="form.is_closed" type="checkbox" class="rounded border-slate-300" />
                    Counts as closed (stops SLA, triggers CSAT)
                </label>
                <div class="flex justify-end gap-2 pt-2">
                    <button type="button" class="rounded-lg border border-slate-200 px-4 py-2 text-sm" @click="showForm = false">{{ $t('common.cancel') }}</button>
                    <button type="submit" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700" :disabled="form.processing">{{ $t('common.save') }}</button>
                </div>
            </form>
        </AppModal>

        <AppConfirmDialog :state="confirm" @close="closeConfirm" @confirm="onConfirm" />
    </SettingsPage>
</template>
