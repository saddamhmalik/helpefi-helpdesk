<script setup>
import { router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import SettingsLayout from '../../Layouts/SettingsLayout.vue';
import AppModal from '../../Components/AppModal.vue';
import AppToggle from '../../Components/AppToggle.vue';
import AppConfirmDialog from '../../Components/AppConfirmDialog.vue';
import { useConfirmDialog } from '../../composables/useConfirmDialog.js';

const props = defineProps({
    categories: Array,
    meta: Object,
});

const showCategoryForm = ref(false);
const showItemForm = ref(false);
const editingCategory = ref(null);
const editingItem = ref(null);
const { state: confirm, ask: askConfirm, close: closeConfirm, confirm: onConfirm } = useConfirmDialog();

const blankCategory = () => ({
    name: '',
    description: '',
    sort_order: 0,
    is_active: true,
});

const blankItem = () => ({
    service_category_id: props.categories[0]?.id ?? '',
    name: '',
    description: '',
    ticket_type: props.meta.ticket_types[1]?.value ?? 'service_request',
    ticket_priority_id: '',
    fields: [],
    sort_order: 0,
    is_public: true,
    is_active: true,
});

const categoryForm = useForm(blankCategory());
const itemForm = useForm(blankItem());

const typeLabel = (value) => props.meta.ticket_types.find((item) => item.value === value)?.label ?? value;

const closeCategoryForm = () => {
    showCategoryForm.value = false;
};

const closeItemForm = () => {
    showItemForm.value = false;
};

const openCreateCategory = () => {
    editingCategory.value = null;
    categoryForm.defaults(blankCategory());
    categoryForm.reset();
    showCategoryForm.value = true;
};

const openEditCategory = (category) => {
    editingCategory.value = category;
    categoryForm.defaults({
        name: category.name,
        description: category.description ?? '',
        sort_order: category.sort_order ?? 0,
        is_active: category.is_active,
    });
    categoryForm.reset();
    showCategoryForm.value = true;
};

const saveCategory = () => {
    if (editingCategory.value) {
        categoryForm.put(`/settings/service-catalog/categories/${editingCategory.value.id}`, {
            onSuccess: closeCategoryForm,
        });
    } else {
        categoryForm.post('/settings/service-catalog/categories', {
            onSuccess: closeCategoryForm,
        });
    }
};

const destroyCategory = (category) => {
    askConfirm({
        title: 'Delete category',
        message: `Delete "${category.name}" and all its services? This cannot be undone.`,
        confirmLabel: 'Delete',
        action: () => router.delete(`/settings/service-catalog/categories/${category.id}`, { preserveScroll: true }),
    });
};

const openCreateItem = (categoryId = null) => {
    editingItem.value = null;
    itemForm.defaults({ ...blankItem(), service_category_id: categoryId ?? props.categories[0]?.id ?? '' });
    itemForm.reset();
    showItemForm.value = true;
};

const openEditItem = (item) => {
    editingItem.value = item;
    itemForm.defaults({
        service_category_id: item.service_category_id,
        name: item.name,
        description: item.description ?? '',
        ticket_type: item.ticket_type,
        ticket_priority_id: item.ticket_priority_id ?? '',
        fields: item.fields ? JSON.parse(JSON.stringify(item.fields)) : [],
        sort_order: item.sort_order ?? 0,
        is_public: item.is_public,
        is_active: item.is_active,
    });
    itemForm.reset();
    showItemForm.value = true;
};

const addField = () => {
    itemForm.fields.push({ name: '', label: '', type: 'text', required: false, options: [] });
};

const removeField = (index) => {
    itemForm.fields.splice(index, 1);
};

const saveItem = () => {
    if (editingItem.value) {
        itemForm.put(`/settings/service-catalog/items/${editingItem.value.id}`, {
            onSuccess: closeItemForm,
        });
    } else {
        itemForm.post('/settings/service-catalog/items', {
            onSuccess: closeItemForm,
        });
    }
};

const destroyItem = (item) => {
    askConfirm({
        title: 'Delete service',
        message: `Remove "${item.name}" from the catalog?`,
        confirmLabel: 'Delete',
        action: () => router.delete(`/settings/service-catalog/items/${item.id}`, { preserveScroll: true }),
    });
};
</script>

<template>
    <SettingsLayout title="Service catalog" description="ITSM service categories and requestable items for the customer portal.">
        <template #actions>
            <button type="button" class="rounded-lg border border-slate-300 px-4 py-2 text-sm text-slate-700 transition hover:bg-slate-50" @click="openCreateCategory">Add category</button>
            <button type="button" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white transition hover:bg-blue-700" @click="openCreateItem()">Add service</button>
        </template>

        <div class="space-y-6">
            <div v-for="category in categories" :key="category.id" class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm transition hover:border-slate-300">
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <div class="flex items-center gap-2">
                            <h2 class="text-lg font-semibold text-slate-900">{{ category.name }}</h2>
                            <span class="rounded-full px-2 py-0.5 text-xs" :class="category.is_active ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-100 text-slate-600'">
                                {{ category.is_active ? 'Active' : 'Hidden' }}
                            </span>
                        </div>
                        <p v-if="category.description" class="mt-1 text-sm text-slate-600">{{ category.description }}</p>
                    </div>
                    <div class="flex gap-2">
                        <button type="button" class="rounded-lg border border-slate-300 px-3 py-1.5 text-sm text-slate-700 transition hover:bg-slate-50" @click="openCreateItem(category.id)">Add service</button>
                        <button type="button" class="rounded-lg border border-slate-300 px-3 py-1.5 text-sm text-slate-700 transition hover:bg-slate-50" @click="openEditCategory(category)">Edit</button>
                        <button type="button" class="rounded-lg border border-red-200 px-3 py-1.5 text-sm text-red-700 transition hover:bg-red-50" @click="destroyCategory(category)">Delete</button>
                    </div>
                </div>

                <div class="mt-4 space-y-3">
                    <div v-for="item in category.items" :key="item.id" class="rounded-lg border border-slate-100 p-4 transition hover:border-slate-200">
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div>
                                <p class="font-medium text-slate-900">{{ item.name }}</p>
                                <p v-if="item.description" class="mt-1 text-sm text-slate-600">{{ item.description }}</p>
                                <div class="mt-2 flex flex-wrap gap-2 text-xs text-slate-500">
                                    <span>{{ typeLabel(item.ticket_type) }}</span>
                                    <span v-if="item.priority">Priority: {{ item.priority.name }}</span>
                                    <span>{{ item.is_public ? 'Public' : 'Internal' }}</span>
                                    <span>/portal/services/{{ item.slug }}</span>
                                </div>
                            </div>
                            <div class="flex gap-2">
                                <button type="button" class="text-sm text-blue-600 transition hover:text-blue-700" @click="openEditItem(item)">Edit</button>
                                <button type="button" class="text-sm text-red-600 transition hover:text-red-700" @click="destroyItem(item)">Delete</button>
                            </div>
                        </div>
                    </div>
                    <p v-if="!category.items?.length" class="text-sm text-slate-500">No services in this category.</p>
                </div>
            </div>
            <p v-if="!categories.length" class="rounded-xl border border-dashed border-slate-300 px-6 py-12 text-center text-sm text-slate-500">No categories yet.</p>
        </div>

        <AppModal
            :open="showCategoryForm"
            :title="editingCategory ? 'Edit category' : 'Add category'"
            size="md"
            @close="closeCategoryForm"
        >
            <form id="category-form" class="space-y-4" @submit.prevent="saveCategory">
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">Name</label>
                    <input v-model="categoryForm.name" type="text" required class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm transition focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20" />
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">Description</label>
                    <textarea v-model="categoryForm.description" rows="3" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm transition focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20" />
                </div>
                <AppToggle v-model="categoryForm.is_active" label="Active" description="Hidden categories are not shown on the portal." />
            </form>

            <template #footer>
                <div class="flex justify-end gap-2">
                    <button type="button" class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 transition hover:bg-white" @click="closeCategoryForm">Cancel</button>
                    <button type="submit" form="category-form" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white transition hover:bg-blue-700 disabled:opacity-60" :disabled="categoryForm.processing">Save</button>
                </div>
            </template>
        </AppModal>

        <AppModal
            :open="showItemForm"
            :title="editingItem ? 'Edit service' : 'Add service'"
            description="Configure how this service appears on the customer portal."
            variant="drawer"
            @close="closeItemForm"
        >
            <form id="item-form" class="space-y-5" @submit.prevent="saveItem">
                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="sm:col-span-2">
                        <label class="mb-1 block text-sm font-medium text-slate-700">Category</label>
                        <select v-model="itemForm.service_category_id" required class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                            <option v-for="category in categories" :key="category.id" :value="category.id">{{ category.name }}</option>
                        </select>
                    </div>
                    <div class="sm:col-span-2">
                        <label class="mb-1 block text-sm font-medium text-slate-700">Name</label>
                        <input v-model="itemForm.name" type="text" required class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" />
                    </div>
                    <div class="sm:col-span-2">
                        <label class="mb-1 block text-sm font-medium text-slate-700">Description</label>
                        <textarea v-model="itemForm.description" rows="2" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" />
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-slate-700">Ticket type</label>
                        <select v-model="itemForm.ticket_type" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                            <option v-for="type in meta.ticket_types" :key="type.value" :value="type.value">{{ type.label }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-slate-700">Default priority</label>
                        <select v-model="itemForm.ticket_priority_id" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                            <option value="">Default</option>
                            <option v-for="priority in meta.priorities" :key="priority.id" :value="priority.id">{{ priority.name }}</option>
                        </select>
                    </div>
                </div>

                <div>
                    <div class="mb-3 flex items-center justify-between">
                        <p class="text-sm font-medium text-slate-700">Custom fields</p>
                        <button type="button" class="rounded-lg border border-blue-200 bg-blue-50 px-3 py-1.5 text-sm font-medium text-blue-700 transition hover:bg-blue-100" @click="addField">Add field</button>
                    </div>

                    <p v-if="!itemForm.fields.length" class="rounded-lg border border-dashed border-slate-200 bg-slate-50 px-4 py-6 text-center text-sm text-slate-500">
                        No custom fields — customers submit with the default form.
                    </p>

                    <TransitionGroup name="list" tag="div" class="space-y-3">
                        <div v-for="(field, index) in itemForm.fields" :key="`field-${index}`" class="rounded-lg border border-slate-200 bg-slate-50/50 p-3">
                            <div class="grid gap-3 sm:grid-cols-2">
                                <input v-model="field.name" type="text" placeholder="Field key" class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm" />
                                <input v-model="field.label" type="text" placeholder="Label" class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm" />
                                <select v-model="field.type" class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm">
                                    <option v-for="fieldType in meta.field_types" :key="fieldType.value" :value="fieldType.value">{{ fieldType.label }}</option>
                                </select>
                                <AppToggle v-model="field.required" label="Required" />
                            </div>
                            <input
                                v-if="field.type === 'select'"
                                :value="(field.options ?? []).join(', ')"
                                type="text"
                                placeholder="Options (comma separated)"
                                class="mt-3 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm"
                                @input="field.options = $event.target.value.split(',').map((item) => item.trim()).filter(Boolean)"
                            />
                            <button type="button" class="mt-2 text-xs text-red-600 transition hover:text-red-700" @click="removeField(index)">Remove field</button>
                        </div>
                    </TransitionGroup>
                </div>

                <div class="space-y-3 rounded-lg border border-slate-200 bg-slate-50/50 p-4">
                    <AppToggle v-model="itemForm.is_public" label="Public on portal" description="Internal services are agent-only." />
                    <AppToggle v-model="itemForm.is_active" label="Active" />
                </div>
            </form>

            <template #footer>
                <div class="flex justify-end gap-2">
                    <button type="button" class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 transition hover:bg-white" @click="closeItemForm">Cancel</button>
                    <button type="submit" form="item-form" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white transition hover:bg-blue-700 disabled:opacity-60" :disabled="itemForm.processing">Save</button>
                </div>
            </template>
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
    </SettingsLayout>
</template>
