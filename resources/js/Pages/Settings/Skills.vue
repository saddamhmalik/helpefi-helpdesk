<script setup>
import { router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import SettingsLayout from '../../Layouts/SettingsLayout.vue';
import AppModal from '../../Components/AppModal.vue';
import AppConfirmDialog from '../../Components/AppConfirmDialog.vue';
import { useConfirmDialog } from '../../composables/useConfirmDialog.js';
import { formInputClass } from '../../composables/useFormControls.js';

const props = defineProps({
    skills: Array,
});

const showForm = ref(false);
const editingSkill = ref(null);
const { state: confirm, ask: askConfirm, close: closeConfirm, confirm: onConfirm } = useConfirmDialog();

const form = useForm({ name: '' });

const openCreate = () => {
    editingSkill.value = null;
    form.defaults({ name: '' });
    form.reset();
    showForm.value = true;
};

const openEdit = (skill) => {
    editingSkill.value = skill;
    form.defaults({ name: skill.name });
    form.reset();
    showForm.value = true;
};

const save = () => {
    if (editingSkill.value) {
        form.put(`/settings/skills/${editingSkill.value.id}`, {
            preserveScroll: true,
            onSuccess: () => { showForm.value = false; },
        });
    } else {
        form.post('/settings/skills', {
            preserveScroll: true,
            onSuccess: () => { showForm.value = false; form.reset(); },
        });
    }
};

const destroySkill = (skill) => {
    askConfirm({
        title: 'Delete skill',
        message: `Remove "${skill.name}" from all agents and assignment rules?`,
        confirmLabel: 'Delete',
        action: () => router.delete(`/settings/skills/${skill.id}`, { preserveScroll: true }),
    });
};
</script>

<template>
    <SettingsLayout title="Agent skills" description="Tag agents with skills for priority-aware auto-assignment.">
        <template #actions>
            <button type="button" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white transition hover:bg-blue-700" @click="openCreate">Add skill</button>
        </template>

        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
            <div v-for="skill in skills" :key="skill.id" class="flex items-center justify-between rounded-xl border border-slate-200 bg-white px-4 py-3 shadow-sm">
                <div>
                    <p class="font-medium text-slate-900">{{ skill.name }}</p>
                    <p class="text-xs text-slate-500">{{ skill.slug }}</p>
                </div>
                <div class="flex gap-2">
                    <button type="button" class="text-sm text-slate-600 hover:text-slate-900" @click="openEdit(skill)">Edit</button>
                    <button type="button" class="text-sm text-red-600 hover:text-red-700" @click="destroySkill(skill)">Delete</button>
                </div>
            </div>
        </div>

        <div v-if="!skills.length" class="rounded-xl border border-dashed border-slate-300 bg-white px-6 py-12 text-center text-sm text-slate-500">
            No skills yet. Create skills like Billing, Technical, or Spanish to route tickets to qualified agents.
        </div>

        <AppModal :open="showForm" :title="editingSkill ? 'Edit skill' : 'Add skill'" size="sm" @close="showForm = false">
            <form id="skill-form" @submit.prevent="save">
                <label class="mb-1 block text-sm font-medium text-slate-700">Name</label>
                <input v-model="form.name" type="text" required :class="formInputClass" />
            </form>
            <template #footer>
                <button type="button" class="rounded-lg border border-slate-300 px-4 py-2 text-sm text-slate-700" @click="showForm = false">Cancel</button>
                <button type="submit" form="skill-form" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white" :disabled="form.processing">Save</button>
            </template>
        </AppModal>

        <AppConfirmDialog :state="confirm" @close="closeConfirm" @confirm="onConfirm" />
    </SettingsLayout>
</template>
