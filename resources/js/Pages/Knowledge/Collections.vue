<script setup>
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import AgentLayout from '../../Layouts/AgentLayout.vue';
import AppModal from '../../Components/AppModal.vue';
import AppToggle from '../../Components/AppToggle.vue';
import AppConfirmDialog from '../../Components/AppConfirmDialog.vue';
import AppRowActions from '../../Components/AppRowActions.vue';
import AppEditAction from '../../Components/AppEditAction.vue';
import AppDeleteAction from '../../Components/AppDeleteAction.vue';
import { useConfirmDialog } from '../../composables/useConfirmDialog.js';
import { useI18n } from 'vue-i18n';

defineProps({
    collections: Array,
});

const { t } = useI18n();

const showCreate = ref(false);
const editingCollection = ref(null);
const { state: confirm, ask: askConfirm, close: closeConfirm, confirm: onConfirm } = useConfirmDialog();

const createForm = useForm({
    name: '',
    description: '',
    sort_order: 0,
    is_public: true,
});

const editForm = useForm({
    name: '',
    description: '',
    sort_order: 0,
    is_public: true,
});

const closeCreate = () => {
    showCreate.value = false;
};

const closeEdit = () => {
    editingCollection.value = null;
};

const create = () => {
    createForm.post('/knowledge/collections', {
        onSuccess: () => {
            createForm.reset();
            closeCreate();
        },
    });
};

const startEdit = (collection) => {
    editingCollection.value = collection;
    editForm.name = collection.name;
    editForm.description = collection.description || '';
    editForm.sort_order = collection.sort_order;
    editForm.is_public = collection.is_public;
};

const saveEdit = () => {
    editForm.put(`/knowledge/collections/${editingCollection.value.id}`, {
        onSuccess: closeEdit,
    });
};

const destroy = (collection) => {
    askConfirm({
        title: t('knowledge.delete_collection'),
        message: `Delete "${collection.name}"? Articles in this collection will be unassigned.`,
        confirmLabel: 'Delete',
        action: () => router.delete(`/knowledge/collections/${collection.id}`),
    });
};
</script>

<template>
    <Head :title="$t('knowledge.collections')" />
    <AgentLayout>
        <div class="mb-6 flex items-center justify-between">
            <div>
                <Link href="/knowledge" class="text-sm text-blue-600 transition hover:text-blue-700">← Knowledge base</Link>
                <h1 class="mt-2 text-2xl font-semibold text-slate-900">{{ $t('knowledge.collections') }}</h1>
            </div>
            <button type="button" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white transition hover:bg-blue-700" @click="showCreate = true">{{ $t('knowledge.new_collection') }}</button>
        </div>

        <div class="space-y-4">
            <div v-for="collection in collections" :key="collection.id" class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm transition hover:border-slate-300">
                <h3 class="font-semibold text-slate-900">{{ collection.name }}</h3>
                <p v-if="collection.description" class="mt-1 text-sm text-slate-600">{{ collection.description }}</p>
                <div class="mt-3 flex items-center justify-between text-sm text-slate-500">
                    <span>{{ collection.articles_count }} articles · {{ collection.is_public ? 'Public' : 'Private' }}</span>
                    <div class="flex gap-2">
                        <AppRowActions>
                            <AppEditAction :label="$t('knowledge.edit')" @click="startEdit(collection)" />
                            <AppDeleteAction :label="$t('knowledge.delete')" @click="destroy(collection)" />
                        </AppRowActions>
                    </div>
                </div>
            </div>
            <p v-if="!collections.length" class="rounded-xl border border-dashed border-slate-300 px-6 py-12 text-center text-sm text-slate-500">{{ $t('knowledge.no_collections_yet') }}</p>
        </div>

        <AppModal
            :open="showCreate"
            :title="$t('knowledge.new_collection')"
            size="md"
            @close="closeCreate"
        >
            <form id="create-collection-form" class="space-y-3" @submit.prevent="create">
                <input v-model="createForm.name" type="text" :placeholder="$t('knowledge.name')" required class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm transition focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20" />
                <textarea v-model="createForm.description" rows="3" :placeholder="$t('knowledge.description')" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm transition focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20" />
                <input v-model.number="createForm.sort_order" type="number" min="0" :placeholder="$t('knowledge.sort_order')" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" />
                <AppToggle v-model="createForm.is_public" :label="$t('knowledge.public_on_portal')" />
            </form>

            <template #footer>
                <div class="flex justify-end gap-2">
                    <button type="button" class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 transition hover:bg-white" @click="closeCreate">{{ $t('knowledge.cancel') }}</button>
                    <button type="submit" form="create-collection-form" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white transition hover:bg-blue-700 disabled:opacity-60" :disabled="createForm.processing">{{ $t('knowledge.create') }}</button>
                </div>
            </template>
        </AppModal>

        <AppModal
            :open="!!editingCollection"
            :title="$t('knowledge.edit_collection')"
            size="md"
            @close="closeEdit"
        >
            <form v-if="editingCollection" id="edit-collection-form" class="space-y-3" @submit.prevent="saveEdit">
                <input v-model="editForm.name" type="text" required class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm font-medium" />
                <textarea v-model="editForm.description" rows="2" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" />
                <input v-model.number="editForm.sort_order" type="number" min="0" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" />
                <AppToggle v-model="editForm.is_public" :label="$t('knowledge.public_on_portal')" />
            </form>

            <template #footer>
                <div v-if="editingCollection" class="flex justify-end gap-2">
                    <button type="button" class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 transition hover:bg-white" @click="closeEdit">{{ $t('knowledge.cancel') }}</button>
                    <button type="submit" form="edit-collection-form" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white transition hover:bg-blue-700 disabled:opacity-60" :disabled="editForm.processing">{{ $t('knowledge.save') }}</button>
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
    </AgentLayout>
</template>
