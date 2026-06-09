<script setup>
import { router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import SettingsLayout from '../../Layouts/SettingsLayout.vue';
import CustomFieldEditor from '../../Components/CustomFieldEditor.vue';
import AppConfirmDialog from '../../Components/AppConfirmDialog.vue';
import { useConfirmDialog } from '../../composables/useConfirmDialog.js';

const props = defineProps({
    brands: Array,
    priorities: Array,
    collections: Array,
});

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
        title: 'Delete brand',
        message: `Delete ${brand.name}? This cannot be undone.`,
        onConfirm: () => router.delete(`/settings/brands/${brand.id}`, { preserveScroll: true }),
    });
};

const addField = () => form.ticket_fields.push({ name: '', label: '', type: 'text', required: false, options: [] });
const removeField = (index) => form.ticket_fields.splice(index, 1);
</script>

<template>
    <SettingsLayout title="Brands" description="Separate portal URLs, mailboxes, knowledge bases, and ticket defaults per brand.">
        <div class="mb-4 flex justify-end">
            <button type="button" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700" @click="openCreate">
                Add brand
            </button>
        </div>

        <div class="space-y-4">
            <div v-for="brand in brands" :key="brand.id" class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <div class="flex items-center gap-2">
                            <h2 class="text-lg font-semibold text-slate-900">{{ brand.name }}</h2>
                            <span v-if="brand.is_default" class="rounded-full bg-blue-100 px-2 py-0.5 text-xs font-medium text-blue-700">Default</span>
                            <span v-if="!brand.is_active" class="rounded-full bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-600">Inactive</span>
                        </div>
                        <p class="mt-1 text-sm text-slate-500">{{ brand.portal_url }}</p>
                        <p class="mt-2 text-sm text-slate-600">
                            {{ brand.collections_count }} collections · {{ brand.inboxes_count }} mailboxes
                            <span v-if="brand.ticket_number_prefix"> · prefix {{ brand.ticket_number_prefix }}</span>
                        </p>
                    </div>
                    <div class="flex gap-2">
                        <button type="button" class="rounded-lg border border-slate-300 px-3 py-1.5 text-sm text-slate-700 hover:bg-slate-50" @click="openEdit(brand)">Edit</button>
                        <button
                            v-if="!brand.is_default"
                            type="button"
                            class="rounded-lg border border-red-200 px-3 py-1.5 text-sm text-red-700 hover:bg-red-50"
                            @click="destroy(brand)"
                        >
                            Delete
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <div v-if="showForm" class="fixed inset-0 z-40 flex items-center justify-center bg-slate-900/40 p-4">
            <form class="max-h-[90vh] w-full max-w-2xl overflow-y-auto rounded-xl bg-white p-6 shadow-xl" @submit.prevent="save">
                <h3 class="text-lg font-semibold text-slate-900">{{ editingBrand ? 'Edit brand' : 'New brand' }}</h3>

                <div class="mt-4 grid gap-4 sm:grid-cols-2">
                    <div>
                        <label class="mb-1 block text-sm font-medium text-slate-700">Name</label>
                        <input v-model="form.name" type="text" required class="w-full rounded-lg border border-slate-300 px-3 py-2" />
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-slate-700">Slug</label>
                        <input v-model="form.slug" type="text" class="w-full rounded-lg border border-slate-300 px-3 py-2" placeholder="auto-generated if empty" />
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-slate-700">Portal title</label>
                        <input v-model="form.portal_title" type="text" class="w-full rounded-lg border border-slate-300 px-3 py-2" />
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-slate-700">Ticket prefix</label>
                        <input v-model="form.ticket_number_prefix" type="text" class="w-full rounded-lg border border-slate-300 px-3 py-2 uppercase" placeholder="AC-" />
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-slate-700">Primary color</label>
                        <input v-model="form.primary_color" type="text" class="w-full rounded-lg border border-slate-300 px-3 py-2" placeholder="#2563eb" />
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-slate-700">Default priority</label>
                        <select v-model="form.default_ticket_priority_id" class="w-full rounded-lg border border-slate-300 px-3 py-2">
                            <option value="">Normal (global default)</option>
                            <option v-for="priority in priorities" :key="priority.id" :value="priority.id">{{ priority.name }}</option>
                        </select>
                    </div>
                </div>

                <label class="mt-4 flex items-center gap-2 text-sm text-slate-700">
                    <input v-model="form.is_active" type="checkbox" class="rounded border-slate-300" />
                    Active
                </label>

                <div class="mt-4">
                    <label class="mb-2 block text-sm font-medium text-slate-700">Knowledge collections</label>
                    <div class="max-h-40 space-y-2 overflow-y-auto rounded-lg border border-slate-200 p-3">
                        <label v-for="collection in collections" :key="collection.id" class="flex items-center gap-2 text-sm text-slate-700">
                            <input v-model="form.collection_ids" type="checkbox" class="rounded border-slate-300" :value="collection.id" />
                            {{ collection.name }}
                        </label>
                    </div>
                </div>

                <div class="mt-4">
                    <div class="mb-2 flex items-center justify-between">
                        <h4 class="text-sm font-medium text-slate-700">Portal ticket fields</h4>
                        <button type="button" class="text-sm text-blue-600 hover:text-blue-700" @click="addField">Add field</button>
                    </div>
                    <CustomFieldEditor :fields="form.ticket_fields" @add="addField" @remove="removeField" />
                </div>

                <div class="mt-6 flex justify-end gap-2">
                    <button type="button" class="rounded-lg border border-slate-300 px-4 py-2 text-sm text-slate-700" @click="showForm = false">Cancel</button>
                    <button type="submit" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700" :disabled="form.processing">Save</button>
                </div>
            </form>
        </div>

        <AppConfirmDialog :state="confirm" @close="closeConfirm" @confirm="onConfirm" />
    </SettingsLayout>
</template>
