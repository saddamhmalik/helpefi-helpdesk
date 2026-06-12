<script setup>
import { router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import SettingsPage from '../../Components/SettingsPage.vue';
import PlanFeatureBanner from '../../Components/PlanFeatureBanner.vue';
import { usePlanFeature } from '../../composables/usePlanFeature.js';
import CustomFieldEditor from '../../Components/CustomFieldEditor.vue';
import AppConfirmDialog from '../../Components/AppConfirmDialog.vue';
import AppRowActions from '../../Components/AppRowActions.vue';
import AppEditAction from '../../Components/AppEditAction.vue';
import AppDeleteAction from '../../Components/AppDeleteAction.vue';
import { useConfirmDialog } from '../../composables/useConfirmDialog.js';
import { useI18n } from 'vue-i18n';

const props = defineProps({
    brands: Array,
    priorities: Array,
    collections: Array,
});

const { t } = useI18n();
const { hasFeature: canManageBrands } = usePlanFeature('workspace');

const showForm = ref(false);
const editingBrand = ref(null);
const { state: confirm, ask: askConfirm, close: closeConfirm, confirm: onConfirm } = useConfirmDialog();

const blankBrand = () => ({
    name: '',
    slug: '',
    is_active: true,
    portal_title: '',
    primary_color: '',
    accent_color: '',
    ticket_number_prefix: '',
    ticket_fields: [],
    default_ticket_priority_id: '',
    kb_deflection_enabled: null,
    collection_ids: [],
});

const form = useForm(blankBrand());

const collectionsForBrand = (brandId) => props.collections.filter((collection) => collection.brand_id === brandId);

const openCreate = () => {
    editingBrand.value = null;
    form.defaults(blankBrand());
    form.reset();
    showForm.value = true;
};

const openEdit = (brand) => {
    editingBrand.value = brand;
    form.defaults({
        ...blankBrand(),
        ...brand,
        default_ticket_priority_id: brand.default_ticket_priority_id || '',
        collection_ids: collectionsForBrand(brand.id).map((collection) => collection.id),
    });
    form.reset();
    showForm.value = true;
};

const save = () => {
    const payload = { ...form.data() };

    if (editingBrand.value) {
        router.put(`/settings/brands/${editingBrand.value.id}`, payload, {
            preserveScroll: true,
            onSuccess: () => {
                showForm.value = false;
            },
        });
        return;
    }

    router.post('/settings/brands', payload, {
        preserveScroll: true,
        onSuccess: () => {
            showForm.value = false;
        },
    });
};

const destroy = (brand) => {
    askConfirm({
        title: t('settings_brands.delete_brand'),
        message: `Delete ${brand.name}? This cannot be undone.`,
        onConfirm: () => router.delete(`/settings/brands/${brand.id}`, { preserveScroll: true }),
    });
};

const addField = () => form.ticket_fields.push({ name: '', label: '', type: 'text', required: false, options: [] });
const removeField = (index) => form.ticket_fields.splice(index, 1);
</script>

<template>
    <SettingsPage :title="$t('settings.brands')" :description="$t('settings_brands.separate_portal_urls_mailboxes_knowledge_bases_and_ticket_defaults_per')">
        <PlanFeatureBanner feature="workspace" />

        <div class="mb-4 flex justify-end">
            <button
                type="button"
                class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-50"
                :disabled="!canManageBrands"
                @click="openCreate"
            >{{ $t('settings_brands.add_brand') }}</button>
        </div>

        <div class="space-y-4">
            <div v-for="brand in brands" :key="brand.id" class="agent-card">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <div class="flex items-center gap-2">
                            <h2 class="text-lg font-semibold agent-text">{{ brand.name }}</h2>
                            <span v-if="brand.is_default" class="rounded-full bg-blue-100 px-2 py-0.5 text-xs font-medium text-blue-700 dark:text-blue-300">{{ $t('settings_brands.default') }}</span>
                            <span v-if="!brand.is_active" class="rounded-full bg-slate-100 dark:bg-slate-900 px-2 py-0.5 text-xs font-medium agent-text-muted">{{ $t('settings_brands.inactive') }}</span>
                        </div>
                        <p class="mt-1 text-sm agent-text-subtle">{{ brand.portal_url }}</p>
                        <p class="mt-2 text-sm agent-text-muted">
                            {{ brand.collections_count }} collections · {{ brand.inboxes_count }} mailboxes
                            <span v-if="brand.ticket_number_prefix"> · prefix {{ brand.ticket_number_prefix }}</span>
                        </p>
                    </div>
                    <AppRowActions>
                        <AppEditAction :label="$t('settings_brands.edit')" @click="openEdit(brand)" />
                        <AppDeleteAction
                            v-if="!brand.is_default"
                            :label="$t('settings_brands.delete')"
                            @click="destroy(brand)"
                        />
                    </AppRowActions>
                </div>
            </div>
        </div>

        <div v-if="showForm" class="fixed inset-0 z-40 flex items-center justify-center bg-slate-900/40 p-4">
            <form class="max-h-[90vh] w-full max-w-2xl overflow-y-auto rounded-xl bg-white dark:bg-slate-900 p-6 shadow-xl" @submit.prevent="save">
                <h3 class="text-lg font-semibold agent-text">{{ editingBrand ? 'Edit brand' : 'New brand' }}</h3>

                <div class="mt-4 grid gap-4 sm:grid-cols-2">
                    <div>
                        <label class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-300">{{ $t('profile.name') }}</label>
                        <input v-model="form.name" type="text" required class="w-full rounded-lg border agent-border px-3 py-2" />
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-300">{{ $t('settings_brands.slug') }}</label>
                        <input v-model="form.slug" type="text" class="w-full rounded-lg border agent-border px-3 py-2" :placeholder="$t('settings_brands.auto-generated_if_empty')" />
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-300">{{ $t('settings_brands.portal_title') }}</label>
                        <input v-model="form.portal_title" type="text" class="w-full rounded-lg border agent-border px-3 py-2" />
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-300">{{ $t('settings_brands.ticket_prefix') }}</label>
                        <input v-model="form.ticket_number_prefix" type="text" class="w-full rounded-lg border agent-border px-3 py-2 uppercase" :placeholder="$t('settings_brands.ac-')" />
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-300">{{ $t('settings_brands.primary_color') }}</label>
                        <input v-model="form.primary_color" type="text" class="w-full rounded-lg border agent-border px-3 py-2" :placeholder="$t('settings_brands.2563eb')" />
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-300">{{ $t('settings_brands.default_priority') }}</label>
                        <select v-model="form.default_ticket_priority_id" class="w-full rounded-lg border agent-border px-3 py-2">
                            <option value="">{{ $t('settings_brands.normal_global_default') }}</option>
                            <option v-for="priority in priorities" :key="priority.id" :value="priority.id">{{ priority.name }}</option>
                        </select>
                    </div>
                </div>

                <label class="mt-4 flex items-center gap-2 text-sm text-slate-700 dark:text-slate-300">
                    <input v-model="form.is_active" type="checkbox" class="rounded agent-border" />
                    {{ $t('common.active') }}
                </label>

                <div class="mt-4">
                    <label class="mb-2 block text-sm font-medium text-slate-700 dark:text-slate-300">{{ $t('settings_brands.knowledge_collections') }}</label>
                    <div class="max-h-40 space-y-2 overflow-y-auto rounded-lg border agent-border p-3">
                        <label v-for="collection in collections" :key="collection.id" class="flex items-center gap-2 text-sm text-slate-700 dark:text-slate-300">
                            <input v-model="form.collection_ids" type="checkbox" class="rounded agent-border" :value="collection.id" />
                            {{ collection.name }}
                        </label>
                    </div>
                </div>

                <div class="mt-4">
                    <div class="mb-2 flex items-center justify-between">
                        <h4 class="text-sm font-medium text-slate-700 dark:text-slate-300">{{ $t('settings_brands.portal_ticket_fields') }}</h4>
                        <button type="button" class="text-sm text-blue-600 hover:text-blue-700 dark:hover:text-blue-300 dark:text-blue-300" @click="addField">{{ $t('settings_brands.add_field') }}</button>
                    </div>
                    <CustomFieldEditor :fields="form.ticket_fields" @add="addField" @remove="removeField" />
                </div>

                <div class="mt-6 flex justify-end gap-2">
                    <button type="button" class="rounded-lg border agent-border px-4 py-2 text-sm text-slate-700 dark:text-slate-300" @click="showForm = false">{{ $t('common.cancel') }}</button>
                    <button type="submit" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700" :disabled="form.processing">{{ $t('common.save') }}</button>
                </div>
            </form>
        </div>

        <AppConfirmDialog :state="confirm" @close="closeConfirm" @confirm="onConfirm" />
    </SettingsPage>
</template>
