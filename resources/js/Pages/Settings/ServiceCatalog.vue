<script setup>
import { router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import SettingsPage from '../../Components/SettingsPage.vue';
import PlanFeatureBanner from '../../Components/PlanFeatureBanner.vue';
import AppModal from '../../Components/AppModal.vue';
import AppToggle from '../../Components/AppToggle.vue';
import AppConfirmDialog from '../../Components/AppConfirmDialog.vue';
import AppRowActions from '../../Components/AppRowActions.vue';
import AppEditAction from '../../Components/AppEditAction.vue';
import AppDeleteAction from '../../Components/AppDeleteAction.vue';
import { useConfirmDialog } from '../../composables/useConfirmDialog.js';
import { useI18n } from 'vue-i18n';

const props = defineProps({
    categories: Array,
    meta: Object,
    agents: Array,
});

const { t } = useI18n();

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
    requires_approval: false,
    approver_user_ids: [],
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
        title: t('settings_service_catalog.delete_category'),
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
        requires_approval: item.requires_approval ?? false,
        approver_user_ids: item.approver_user_ids ?? [],
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

const toggleApprover = (agentId) => {
    const ids = new Set(itemForm.approver_user_ids.map(Number));
    const id = Number(agentId);

    if (ids.has(id)) {
        ids.delete(id);
    } else {
        ids.add(id);
    }

    itemForm.approver_user_ids = Array.from(ids);
};

const destroyItem = (item) => {
    askConfirm({
        title: t('settings_service_catalog.delete_service'),
        message: `Remove "${item.name}" from the catalog?`,
        confirmLabel: 'Delete',
        action: () => router.delete(`/settings/service-catalog/items/${item.id}`, { preserveScroll: true }),
    });
};
</script>

<template>
    <SettingsPage :title="$t('settings.service_catalog')" :description="$t('settings.descriptions.service_catalog')" info-section="service_catalog">
        <PlanFeatureBanner feature="service_catalog" />

        <template #actions>
            <button type="button" class="rounded-lg border agent-border px-4 py-2 text-sm text-slate-700 dark:text-slate-300 transition agent-hover-surface" @click="openCreateCategory">{{ $t('settings_service_catalog.add_category') }}</button>
            <button type="button" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white transition hover:bg-blue-700" @click="openCreateItem()">{{ $t('settings_service_catalog.add_service') }}</button>
        </template>

        <div class="space-y-6">
            <div v-for="category in categories" :key="category.id" class="agent-card transition hover:border-slate-300 dark:border-slate-700 dark:hover:border-slate-600">
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <div class="flex items-center gap-2">
                            <h2 class="text-lg font-semibold agent-text">{{ category.name }}</h2>
                            <span class="rounded-full px-2 py-0.5 text-xs" :class="category.is_active ? 'bg-emerald-100 text-emerald-800 dark:text-emerald-200' : 'bg-slate-100 dark:bg-slate-900 agent-text-muted'">
                                {{ category.is_active ? 'Active' : 'Hidden' }}
                            </span>
                        </div>
                        <p v-if="category.description" class="mt-1 text-sm agent-text-muted">{{ category.description }}</p>
                    </div>
                    <div class="flex gap-2">
                        <button type="button" class="rounded-lg border agent-border px-3 py-1.5 text-sm text-slate-700 dark:text-slate-300 transition agent-hover-surface" @click="openCreateItem(category.id)">{{ $t('settings_service_catalog.add_service') }}</button>
                        <AppRowActions>
                            <AppEditAction :label="$t('settings_service_catalog.edit')" @click="openEditCategory(category)" />
                            <AppDeleteAction :label="$t('settings_service_catalog.delete')" @click="destroyCategory(category)" />
                        </AppRowActions>
                    </div>
                </div>

                <div class="mt-4 space-y-3">
                    <div v-for="item in category.items" :key="item.id" class="rounded-lg border agent-border-subtle p-4 transition hover:border-slate-300 dark:border-slate-700 dark:hover:border-slate-600">
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div>
                                <p class="font-medium agent-text">{{ item.name }}</p>
                                <p v-if="item.description" class="mt-1 text-sm agent-text-muted">{{ item.description }}</p>
                                <div class="mt-2 flex flex-wrap gap-2 text-xs agent-text-subtle">
                                    <span>{{ typeLabel(item.ticket_type) }}</span>
                                    <span v-if="item.priority">Priority: {{ item.priority.name }}</span>
                                    <span>{{ item.is_public ? 'Public' : 'Internal' }}</span>
                                    <span>/portal/services/{{ item.slug }}</span>
                                </div>
                            </div>
                            <div class="flex gap-2">
                                <AppRowActions>
                                    <AppEditAction :label="$t('settings_service_catalog.edit')" @click="openEditItem(item)" />
                                    <AppDeleteAction :label="$t('settings_service_catalog.delete')" @click="destroyItem(item)" />
                                </AppRowActions>
                            </div>
                        </div>
                    </div>
                    <p v-if="!category.items?.length" class="text-sm agent-text-subtle">{{ $t('settings_service_catalog.no_services_in_this_category') }}</p>
                </div>
            </div>
            <p v-if="!categories.length" class="rounded-xl border border-dashed agent-border px-6 py-12 text-center text-sm agent-text-subtle">{{ $t('settings_service_catalog.no_categories_yet') }}</p>
        </div>

        <AppModal
            :open="showCategoryForm"
            :title="editingCategory ? 'Edit category' : 'Add category'"
            size="md"
            @close="closeCategoryForm"
        >
            <form id="category-form" class="space-y-4" @submit.prevent="saveCategory">
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-300">{{ $t('profile.name') }}</label>
                    <input v-model="categoryForm.name" type="text" required class="w-full rounded-lg border agent-border px-3 py-2 text-sm transition focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20" />
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-300">{{ $t('settings_service_catalog.description') }}</label>
                    <textarea v-model="categoryForm.description" rows="3" class="w-full rounded-lg border agent-border px-3 py-2 text-sm transition focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20" />
                </div>
                <AppToggle v-model="categoryForm.is_active" :label="$t('common.active')" :description="$t('settings_service_catalog.hidden_categories_are_not_shown_on_the_portal')" />
            </form>

            <template #footer>
                <div class="flex justify-end gap-2">
                    <button type="button" class="rounded-lg border agent-border px-4 py-2 text-sm font-medium text-slate-700 dark:text-slate-300 transition agent-hover-surface" @click="closeCategoryForm">{{ $t('common.cancel') }}</button>
                    <button type="submit" form="category-form" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white transition hover:bg-blue-700 disabled:opacity-60" :disabled="categoryForm.processing">{{ $t('common.save') }}</button>
                </div>
            </template>
        </AppModal>

        <AppModal
            :open="showItemForm"
            :title="editingItem ? 'Edit service' : 'Add service'"
            :description="$t('settings_service_catalog.configure_how_this_service_appears_on_the_customer_portal')"
            variant="drawer"
            @close="closeItemForm"
        >
            <form id="item-form" class="space-y-5" @submit.prevent="saveItem">
                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="sm:col-span-2">
                        <label class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-300">{{ $t('settings_service_catalog.category') }}</label>
                        <select v-model="itemForm.service_category_id" required class="w-full rounded-lg border agent-border px-3 py-2 text-sm">
                            <option v-for="category in categories" :key="category.id" :value="category.id">{{ category.name }}</option>
                        </select>
                    </div>
                    <div class="sm:col-span-2">
                        <label class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-300">{{ $t('profile.name') }}</label>
                        <input v-model="itemForm.name" type="text" required class="w-full rounded-lg border agent-border px-3 py-2 text-sm" />
                    </div>
                    <div class="sm:col-span-2">
                        <label class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-300">{{ $t('settings_service_catalog.description') }}</label>
                        <textarea v-model="itemForm.description" rows="2" class="w-full rounded-lg border agent-border px-3 py-2 text-sm" />
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-300">{{ $t('settings_service_catalog.ticket_type') }}</label>
                        <select v-model="itemForm.ticket_type" class="w-full rounded-lg border agent-border px-3 py-2 text-sm">
                            <option v-for="type in meta.ticket_types" :key="type.value" :value="type.value">{{ type.label }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-300">{{ $t('settings_service_catalog.default_priority') }}</label>
                        <select v-model="itemForm.ticket_priority_id" class="w-full rounded-lg border agent-border px-3 py-2 text-sm">
                            <option value="">{{ $t('settings_service_catalog.default') }}</option>
                            <option v-for="priority in meta.priorities" :key="priority.id" :value="priority.id">{{ priority.name }}</option>
                        </select>
                    </div>
                </div>

                <div v-if="meta.service_desk_available" class="rounded-xl border border-violet-200 dark:border-violet-900/60 bg-violet-50 dark:bg-violet-950/40/50 p-4">
                    <AppToggle v-model="itemForm.requires_approval" :label="$t('settings_service_catalog.require_approval_before_fulfillment')" />
                    <p class="mt-2 text-xs agent-text-muted">{{ $t('settings_service_catalog.creates_a_pending_ticket_and_routes_sequential_approvers_from_service_') }}</p>
                    <div v-if="itemForm.requires_approval" class="mt-4">
                        <p class="mb-2 text-sm font-medium text-slate-700 dark:text-slate-300">{{ $t('settings_service_catalog.approvers_in_order') }}</p>
                        <div class="flex flex-wrap gap-2">
                            <button
                                v-for="agent in agents"
                                :key="agent.id"
                                type="button"
                                class="rounded-full px-3 py-1 text-xs font-medium transition"
                                :class="itemForm.approver_user_ids.includes(agent.id) ? 'bg-violet-700 text-white' : 'bg-white dark:bg-slate-900 text-slate-700 dark:text-slate-300 ring-1 agent-border'"
                                @click="toggleApprover(agent.id)"
                            >
                                {{ agent.name }}
                            </button>
                        </div>
                    </div>
                </div>

                <div>
                    <div class="mb-3 flex items-center justify-between">
                        <p class="text-sm font-medium text-slate-700 dark:text-slate-300">{{ $t('settings_service_catalog.custom_fields') }}</p>
                        <button type="button" class="rounded-lg border border-blue-200 dark:border-blue-900/60 bg-blue-50 dark:bg-blue-950/40 px-3 py-1.5 text-sm font-medium text-blue-700 dark:text-blue-300 transition hover:bg-blue-100" @click="addField">{{ $t('settings_service_catalog.add_field') }}</button>
                    </div>

                    <p v-if="!itemForm.fields.length" class="rounded-lg border border-dashed agent-border agent-panel-muted px-4 py-6 text-center text-sm agent-text-subtle">
                        No custom fields — customers submit with the default form.
                    </p>

                    <TransitionGroup name="list" tag="div" class="space-y-3">
                        <div v-for="(field, index) in itemForm.fields" :key="`field-${index}`" class="rounded-lg border agent-border agent-panel-muted/50 p-3">
                            <div class="grid gap-3 sm:grid-cols-2">
                                <input v-model="field.name" type="text" :placeholder="$t('settings_service_catalog.field_key')" class="rounded-lg border agent-border agent-panel px-3 py-2 text-sm" />
                                <input v-model="field.label" type="text" placeholder="Label" class="rounded-lg border agent-border agent-panel px-3 py-2 text-sm" />
                                <select v-model="field.type" class="rounded-lg border agent-border agent-panel px-3 py-2 text-sm">
                                    <option v-for="fieldType in meta.field_types" :key="fieldType.value" :value="fieldType.value">{{ fieldType.label }}</option>
                                </select>
                                <AppToggle v-model="field.required" :label="$t('settings_service_catalog.required')" />
                            </div>
                            <input
                                v-if="field.type === 'select'"
                                :value="(field.options ?? []).join(', ')"
                                type="text"
                                :placeholder="$t('settings_service_catalog.options_comma_separated')"
                                class="mt-3 w-full rounded-lg border agent-border agent-panel px-3 py-2 text-sm"
                                @input="field.options = $event.target.value.split(',').map((item) => item.trim()).filter(Boolean)"
                            />
                            <button type="button" class="mt-2 text-xs text-red-600 transition hover:text-red-700 dark:text-red-300" @click="removeField(index)">{{ $t('settings_service_catalog.remove_field') }}</button>
                        </div>
                    </TransitionGroup>
                </div>

                <div class="space-y-3 rounded-lg border agent-border agent-panel-muted/50 p-4">
                    <AppToggle v-model="itemForm.is_public" :label="$t('settings_service_catalog.public_on_portal')" :description="$t('settings_service_catalog.internal_services_are_agent-only')" />
                    <AppToggle v-model="itemForm.is_active" :label="$t('common.active')" />
                </div>
            </form>

            <template #footer>
                <div class="flex justify-end gap-2">
                    <button type="button" class="rounded-lg border agent-border px-4 py-2 text-sm font-medium text-slate-700 dark:text-slate-300 transition agent-hover-surface" @click="closeItemForm">{{ $t('common.cancel') }}</button>
                    <button type="submit" form="item-form" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white transition hover:bg-blue-700 disabled:opacity-60" :disabled="itemForm.processing">{{ $t('common.save') }}</button>
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
    </SettingsPage>
</template>
