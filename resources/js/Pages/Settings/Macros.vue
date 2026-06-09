<script setup>
import { router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import SettingsLayout from '../../Layouts/SettingsLayout.vue';
import AppModal from '../../Components/AppModal.vue';
import AppConfirmDialog from '../../Components/AppConfirmDialog.vue';
import { useConfirmDialog } from '../../composables/useConfirmDialog.js';
import { formInputClass, formTextareaClass } from '../../composables/useFormControls.js';

const props = defineProps({
    responses: Array,
    placeholders: Array,
});

const showForm = ref(false);
const editing = ref(null);
const { state: confirm, ask: askConfirm, close: closeConfirm, confirm: onConfirm } = useConfirmDialog();

const blank = () => ({
    title: '',
    shortcut: '',
    body: '',
    is_shared: false,
});

const form = useForm(blank());

const openCreate = () => {
    editing.value = null;
    form.defaults(blank());
    form.reset();
    showForm.value = true;
};

const openEdit = (response) => {
    editing.value = response;
    form.defaults({
        title: response.title,
        shortcut: response.shortcut ?? '',
        body: response.body,
        is_shared: response.is_shared,
    });
    form.reset();
    showForm.value = true;
};

const submit = () => {
    if (editing.value) {
        form.put(`/settings/macros/${editing.value.id}`, {
            preserveScroll: true,
            onSuccess: () => { showForm.value = false; },
        });
    } else {
        form.post('/settings/macros', {
            preserveScroll: true,
            onSuccess: () => { showForm.value = false; },
        });
    }
};

const destroy = (response) => {
    askConfirm({
        title: 'Delete macro?',
        message: `Remove "${response.title}" permanently?`,
        action: () => router.delete(`/settings/macros/${response.id}`, { preserveScroll: true }),
    });
};
</script>

<template>
    <SettingsLayout title="Macros">
        <div class="mx-auto max-w-4xl space-y-6">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-bold text-slate-900">Canned responses</h1>
                    <p class="mt-1 text-sm text-slate-500">Save reusable replies with placeholders for ticket and customer details.</p>
                </div>
                <button type="button" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700" @click="openCreate">
                    New macro
                </button>
            </div>

            <div class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Placeholders</p>
                <div class="mt-2 flex flex-wrap gap-2">
                    <code v-for="item in placeholders" :key="item.token" class="rounded bg-white px-2 py-1 text-xs text-slate-700 ring-1 ring-slate-200">{{ item.token }}</code>
                </div>
            </div>

            <div class="overflow-hidden rounded-xl border border-slate-200 bg-white">
                <ul class="divide-y divide-slate-100">
                    <li v-for="response in responses" :key="response.id" class="flex items-start justify-between gap-4 px-4 py-4">
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <p class="font-medium text-slate-900">{{ response.title }}</p>
                                <span v-if="response.shortcut" class="rounded bg-slate-100 px-2 py-0.5 text-[11px] text-slate-600">#{{ response.shortcut }}</span>
                                <span v-if="response.is_shared" class="rounded bg-blue-50 px-2 py-0.5 text-[11px] font-medium text-blue-700">Shared</span>
                            </div>
                            <p class="mt-1 line-clamp-2 text-sm text-slate-500">{{ response.body.replace(/<[^>]+>/g, ' ').trim() }}</p>
                        </div>
                        <div class="flex shrink-0 gap-2">
                            <button type="button" class="text-sm text-blue-600 hover:text-blue-700" @click="openEdit(response)">Edit</button>
                            <button type="button" class="text-sm text-red-600 hover:text-red-700" @click="destroy(response)">Delete</button>
                        </div>
                    </li>
                    <li v-if="!responses?.length" class="px-4 py-10 text-center text-sm text-slate-500">No macros yet.</li>
                </ul>
            </div>
        </div>

        <AppModal :show="showForm" @close="showForm = false">
            <form class="space-y-4" @submit.prevent="submit">
                <h2 class="text-lg font-semibold text-slate-900">{{ editing ? 'Edit macro' : 'New macro' }}</h2>
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">Title</label>
                    <input v-model="form.title" type="text" required :class="formInputClass" />
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">Shortcut</label>
                    <input v-model="form.shortcut" type="text" placeholder="refund" :class="formInputClass" />
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">Body</label>
                    <textarea v-model="form.body" rows="8" required :class="formTextareaClass" />
                </div>
                <label class="flex items-center gap-2 text-sm text-slate-700">
                    <input v-model="form.is_shared" type="checkbox" class="rounded border-slate-300 text-blue-600" />
                    Share with all agents
                </label>
                <div class="flex justify-end gap-2">
                    <button type="button" class="rounded-lg border border-slate-200 px-4 py-2 text-sm" @click="showForm = false">Cancel</button>
                    <button type="submit" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white" :disabled="form.processing">Save</button>
                </div>
            </form>
        </AppModal>

        <AppConfirmDialog :state="confirm" @close="closeConfirm" @confirm="onConfirm" />
    </SettingsLayout>
</template>
