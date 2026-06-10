<script setup>
import { Head, router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import AgentLayout from '../../Layouts/AgentLayout.vue';
import AssetsNav from '../../Components/AssetsNav.vue';
import AppConfirmDialog from '../../Components/AppConfirmDialog.vue';
import AppModal from '../../Components/AppModal.vue';
import DataTable from '../../Components/DataTable.vue';
import PageHeader from '../../Components/PageHeader.vue';
import AppRowActions from '../../Components/AppRowActions.vue';
import AppEditAction from '../../Components/AppEditAction.vue';
import AppDeleteAction from '../../Components/AppDeleteAction.vue';
import { useConfirmDialog } from '../../composables/useConfirmDialog.js';
import { formInputClass } from '../../composables/useFormControls.js';
import { useI18n } from 'vue-i18n';

defineProps({
    types: Array,
});

const { t } = useI18n();

const showForm = ref(false);
const editing = ref(null);
const { state: confirm, ask: askConfirm, close: closeConfirm, confirm: onConfirm } = useConfirmDialog();

const form = useForm({
    name: '',
});

const openCreate = () => {
    editing.value = null;
    form.clearErrors();
    form.name = '';
    showForm.value = true;
};

const openEdit = (type) => {
    editing.value = type;
    form.clearErrors();
    form.name = type.name;
    showForm.value = true;
};

const submit = () => {
    if (editing.value) {
        form.put(`/assets/types/${editing.value.id}`, {
            preserveScroll: true,
            onSuccess: () => {
                showForm.value = false;
            },
        });
    } else {
        form.post('/assets/types', {
            preserveScroll: true,
            onSuccess: () => {
                showForm.value = false;
                form.reset();
            },
        });
    }
};

const destroy = (type) => {
    askConfirm({
        title: 'Delete asset type?',
        message: type.assets_count > 0
            ? `"${type.name}" has ${type.assets_count} assets and cannot be deleted until they are reassigned.`
            : `Remove "${type.name}" permanently?`,
        confirmLabel: type.assets_count > 0 ? 'OK' : 'Delete',
        variant: type.assets_count > 0 ? 'default' : 'danger',
        action: () => {
            if (type.assets_count > 0) {
                return;
            }

            router.delete(`/assets/types/${type.id}`, { preserveScroll: true });
        },
    });
};
</script>

<template>
    <Head :title="$t('assets.asset_types')" />
    <AgentLayout>
        <PageHeader :description="$t('assets.define_the_categories_used_when_creating_and_importing_assets')">
            <template #actions>
                <button
                    type="button"
                    class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700"
                    @click="openCreate"
                >{{ $t('assets.new_type') }}</button>
            </template>
        </PageHeader>

        <AssetsNav />

        <DataTable>
            <thead class="bg-slate-50">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">{{ $t('assets.name') }}</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">{{ $t('assets.slug') }}</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">{{ $t('assets.assets') }}</th>
                    <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-slate-500">{{ $t('assets.actions') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                <tr v-for="type in types" :key="type.id" class="hover:bg-slate-50">
                    <td class="px-4 py-3 text-sm font-medium text-slate-900">{{ type.name }}</td>
                    <td class="px-4 py-3 text-sm text-slate-500">{{ type.slug }}</td>
                    <td class="px-4 py-3 text-sm text-slate-600">{{ type.assets_count }}</td>
                    <td class="px-4 py-3 text-right text-sm">
                        <AppRowActions>
                            <AppEditAction :label="$t('assets.edit')" @click="openEdit(type)" />
                            <AppDeleteAction
                                :label="$t('common.delete')"
                                :disabled="type.assets_count > 0"
                                @click="destroy(type)"
                            />
                        </AppRowActions>
                    </td>
                </tr>
                <tr v-if="!types?.length">
                    <td colspan="4" class="px-4 py-12 text-center text-sm text-slate-500">No asset types yet.</td>
                </tr>
            </tbody>
        </DataTable>

        <AppModal :show="showForm" @close="showForm = false">
            <form class="w-full max-w-md p-6" @submit.prevent="submit">
                <h2 class="text-lg font-semibold text-slate-900">{{ editing ? 'Edit asset type' : 'New asset type' }}</h2>
                <p class="mt-1 text-sm text-slate-500">{{ $t('assets.examples_printer_router_docking_station_vpn_license') }}</p>
                <div class="mt-4">
                    <label class="mb-1 block text-sm font-medium text-slate-700" for="type-name">{{ $t('assets.name') }}</label>
                    <input id="type-name" v-model="form.name" type="text" required :class="formInputClass" :placeholder="$t('assets.printer')" />
                    <p v-if="form.errors.name" class="mt-1 text-sm text-red-600">{{ form.errors.name }}</p>
                </div>
                <div class="mt-6 flex justify-end gap-2">
                    <button type="button" class="rounded-lg border border-slate-300 px-4 py-2 text-sm text-slate-700 hover:bg-slate-50" @click="showForm = false">{{ $t('assets.cancel') }}</button>
                    <button type="submit" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700" :disabled="form.processing">
                        {{ editing ? 'Save changes' : 'Create type' }}
                    </button>
                </div>
            </form>
        </AppModal>

        <AppConfirmDialog
            :open="confirm.open"
            :title="confirm.title"
            :message="confirm.message"
            :confirm-label="confirm.confirmLabel"
            :variant="confirm.variant"
            @close="closeConfirm"
            @confirm="onConfirm"
        />
    </AgentLayout>
</template>
