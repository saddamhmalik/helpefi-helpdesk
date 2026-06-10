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
    skills: Array,
});

const { t } = useI18n();

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
        title: t('settings_skills.delete_skill'),
        message: `Remove "${skill.name}" from all agents and assignment rules?`,
        confirmLabel: 'Delete',
        action: () => router.delete(`/settings/skills/${skill.id}`, { preserveScroll: true }),
    });
};
</script>

<template>
    <SettingsPage :title="$t('settings_skills.agent_skills')" :description="$t('settings_skills.tag_agents_with_skills_for_priority-aware_auto-assignment')">
        <template #actions>
            <button type="button" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white transition hover:bg-blue-700" @click="openCreate">{{ $t('settings_skills.add_skill') }}</button>
        </template>

        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
            <div v-for="skill in skills" :key="skill.id" class="flex items-center justify-between rounded-xl border border-slate-200 bg-white px-4 py-3 shadow-sm">
                <div>
                    <p class="font-medium text-slate-900">{{ skill.name }}</p>
                    <p class="text-xs text-slate-500">{{ skill.slug }}</p>
                </div>
                <div class="flex gap-2">
                    <AppRowActions>
                        <AppEditAction :label="$t('settings_skills.edit')" @click="openEdit(skill)" />
                        <AppDeleteAction :label="$t('settings_skills.delete')" @click="destroySkill(skill)" />
                    </AppRowActions>
                </div>
            </div>
        </div>

        <div v-if="!skills.length" class="rounded-xl border border-dashed border-slate-300 bg-white px-6 py-12 text-center text-sm text-slate-500">
            No skills yet. Create skills like Billing, Technical, or Spanish to route tickets to qualified agents.
        </div>

        <AppModal :open="showForm" :title="editingSkill ? 'Edit skill' : 'Add skill'" size="sm" @close="showForm = false">
            <form id="skill-form" @submit.prevent="save">
                <label class="mb-1 block text-sm font-medium text-slate-700">{{ $t('profile.name') }}</label>
                <input v-model="form.name" type="text" required :class="formInputClass" />
            </form>
            <template #footer>
                <button type="button" class="rounded-lg border border-slate-300 px-4 py-2 text-sm text-slate-700" @click="showForm = false">{{ $t('common.cancel') }}</button>
                <button type="submit" form="skill-form" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white" :disabled="form.processing">{{ $t('common.save') }}</button>
            </template>
        </AppModal>

        <AppConfirmDialog :state="confirm" @close="closeConfirm" @confirm="onConfirm" />
    </SettingsPage>
</template>
