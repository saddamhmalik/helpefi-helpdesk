<script setup>
import { router, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import SettingsLayout from '../../Layouts/SettingsLayout.vue';
import AppModal from '../../Components/AppModal.vue';
import AppToggle from '../../Components/AppToggle.vue';
import AppConfirmDialog from '../../Components/AppConfirmDialog.vue';
import { useConfirmDialog } from '../../composables/useConfirmDialog.js';
import { formInputClass, formSelectClass } from '../../composables/useFormControls.js';

const props = defineProps({
    rules: Array,
    meta: Object,
    departments: Array,
    teams: Array,
    channels: Array,
    priorities: Array,
    skills: Array,
});

const showForm = ref(false);
const editingRule = ref(null);
const { state: confirm, ask: askConfirm, close: closeConfirm, confirm: onConfirm } = useConfirmDialog();

const blankRule = () => ({
    name: '',
    strategy: props.meta.strategies[0]?.value ?? 'round_robin',
    is_active: true,
    sort_order: 0,
    team_id: '',
    department_id: '',
    channel_ids: [],
    ticket_priority_id: '',
    skill_ids: [],
});

const form = useForm(blankRule());

const strategyLabel = (value) => props.meta.strategies.find((item) => item.value === value)?.label ?? value;

const teamLabel = (teamId) => props.teams.find((team) => team.id === teamId)?.name ?? 'Any team';

const departmentLabel = (departmentId) => props.departments.find((department) => department.id === departmentId)?.name ?? 'Any department';

const skillLabel = (skillId) => props.skills.find((skill) => skill.id === skillId)?.name ?? skillId;
const priorityLabel = (priorityId) => props.priorities.find((priority) => priority.id === priorityId)?.name ?? 'Any priority';

const toggleSkill = (skillId) => {
    const id = Number(skillId);
    const index = form.skill_ids.indexOf(id);

    if (index === -1) {
        form.skill_ids.push(id);
    } else {
        form.skill_ids.splice(index, 1);
    }
};
const channelLabels = (channelIds) => {
    if (!channelIds?.length) {
        return 'All channels';
    }

    return channelIds
        .map((id) => props.channels.find((channel) => channel.id === id)?.name ?? id)
        .join(', ');
};

const filteredTeams = computed(() => {
    if (!form.department_id) {
        return props.teams;
    }

    return props.teams.filter((team) => team.department_id === Number(form.department_id));
});

const closeForm = () => {
    showForm.value = false;
};

const openCreate = () => {
    editingRule.value = null;
    form.defaults(blankRule());
    form.reset();
    showForm.value = true;
};

const openEdit = (rule) => {
    editingRule.value = rule;
    form.defaults({
        name: rule.name,
        strategy: rule.strategy,
        is_active: rule.is_active,
        sort_order: rule.sort_order ?? 0,
        team_id: rule.team_id ?? '',
        department_id: rule.department_id ?? '',
        channel_ids: rule.channel_ids ? [...rule.channel_ids] : [],
        ticket_priority_id: rule.ticket_priority_id ?? '',
        skill_ids: rule.skill_ids ? [...rule.skill_ids] : [],
    });
    form.reset();
    showForm.value = true;
};

const toggleChannel = (channelId) => {
    const id = Number(channelId);
    const index = form.channel_ids.indexOf(id);

    if (index === -1) {
        form.channel_ids.push(id);
    } else {
        form.channel_ids.splice(index, 1);
    }
};

const save = () => {
    const payload = {
        ...form.data(),
        team_id: form.team_id || null,
        department_id: form.department_id || null,
    };

    if (editingRule.value) {
        form.transform(() => payload).put(`/settings/assignment/${editingRule.value.id}`, {
            preserveScroll: true,
            onSuccess: () => { showForm.value = false; form.transform((data) => data); },
        });
    } else {
        form.transform(() => payload).post('/settings/assignment', {
            preserveScroll: true,
            onSuccess: () => { showForm.value = false; form.transform((data) => data); },
        });
    }
};

const destroyRule = (rule) => {
    askConfirm({
        title: 'Delete rule',
        message: `Delete "${rule.name}"? This cannot be undone.`,
        confirmLabel: 'Delete',
        action: () => router.delete(`/settings/assignment/${rule.id}`, { preserveScroll: true }),
    });
};
</script>

<template>
    <SettingsLayout title="Auto-assignment" description="Distribute unassigned tickets using round-robin or load-based rules.">
        <template #actions>
            <button type="button" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white transition hover:bg-blue-700" @click="openCreate">New rule</button>
        </template>

        <div class="space-y-4">
            <div v-for="rule in rules" :key="rule.id" class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm transition hover:border-slate-300">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <div class="flex items-center gap-2">
                            <h2 class="text-lg font-semibold text-slate-900">{{ rule.name }}</h2>
                            <span class="rounded-full px-2 py-0.5 text-xs font-medium" :class="rule.is_active ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-100 text-slate-600'">
                                {{ rule.is_active ? 'Active' : 'Paused' }}
                            </span>
                        </div>
                        <p class="mt-1 text-sm text-slate-500">{{ strategyLabel(rule.strategy) }} · Order {{ rule.sort_order }}</p>
                    </div>
                    <div class="flex gap-2">
                        <button type="button" class="rounded-lg border border-slate-300 px-3 py-1.5 text-sm text-slate-700 transition hover:bg-slate-50" @click="openEdit(rule)">Edit</button>
                        <button type="button" class="rounded-lg border border-red-200 px-3 py-1.5 text-sm text-red-700 transition hover:bg-red-50" @click="destroyRule(rule)">Delete</button>
                    </div>
                </div>

                <dl class="mt-4 grid gap-3 text-sm sm:grid-cols-2">
                    <div>
                        <dt class="text-xs font-medium uppercase text-slate-500">Scope</dt>
                        <dd class="mt-1 text-slate-700">
                            {{ departmentLabel(rule.department_id) }}
                            <span v-if="rule.team_id"> · {{ teamLabel(rule.team_id) }}</span>
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium uppercase text-slate-500">Channels</dt>
                        <dd class="mt-1 text-slate-700">{{ channelLabels(rule.channel_ids) }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium uppercase text-slate-500">Priority</dt>
                        <dd class="mt-1 text-slate-700">{{ rule.ticket_priority_id ? priorityLabel(rule.ticket_priority_id) : 'Any priority' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium uppercase text-slate-500">Skills</dt>
                        <dd class="mt-1 text-slate-700">
                            {{ rule.skill_ids?.length ? rule.skill_ids.map((id) => skillLabel(id)).join(', ') : 'Any agent' }}
                        </dd>
                    </div>
                </dl>
            </div>

            <div v-if="!rules.length" class="rounded-xl border border-dashed border-slate-300 bg-white px-6 py-12 text-center text-sm text-slate-500">
                No assignment rules yet. Unassigned tickets will stay in the queue until an agent picks them up.
            </div>
        </div>

        <AppModal
            :open="showForm"
            :title="editingRule ? 'Edit rule' : 'New rule'"
            description="Rules run in sort order when a ticket is created or unassigned."
            variant="drawer"
            @close="closeForm"
        >
            <form id="assignment-form" class="space-y-5" @submit.prevent="save">
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">Name</label>
                    <input v-model="form.name" type="text" required :class="formInputClass" />
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label class="mb-1 block text-sm font-medium text-slate-700">Strategy</label>
                        <select v-model="form.strategy" :class="formSelectClass">
                            <option v-for="strategy in meta.strategies" :key="strategy.value" :value="strategy.value">{{ strategy.label }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-slate-700">Sort order</label>
                        <input v-model.number="form.sort_order" type="number" min="0" :class="formInputClass" />
                    </div>
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label class="mb-1 block text-sm font-medium text-slate-700">Department</label>
                        <select v-model="form.department_id" :class="formSelectClass">
                            <option value="">Any department</option>
                            <option v-for="department in departments" :key="department.id" :value="department.id">{{ department.name }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-slate-700">Team</label>
                        <select v-model="form.team_id" :class="formSelectClass">
                            <option value="">Any team</option>
                            <option v-for="team in filteredTeams" :key="team.id" :value="team.id">{{ team.name }}</option>
                        </select>
                    </div>
                </div>

                <div>
                    <p class="mb-2 text-sm font-medium text-slate-700">Channels</p>
                    <p class="mb-3 text-xs text-slate-500">Leave empty to apply on all channels.</p>
                    <div class="flex flex-wrap gap-2">
                        <button
                            v-for="channel in channels"
                            :key="channel.id"
                            type="button"
                            class="rounded-full px-3 py-1 text-xs font-medium transition"
                            :class="form.channel_ids.includes(channel.id) ? 'bg-blue-600 text-white' : 'bg-slate-100 text-slate-700 hover:bg-slate-200'"
                            @click="toggleChannel(channel.id)"
                        >
                            {{ channel.name }}
                        </button>
                    </div>
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">Priority</label>
                    <select v-model="form.ticket_priority_id" :class="formSelectClass">
                        <option value="">Any priority</option>
                        <option v-for="priority in priorities" :key="priority.id" :value="priority.id">{{ priority.name }}</option>
                    </select>
                </div>

                <div>
                    <p class="mb-2 text-sm font-medium text-slate-700">Required skills</p>
                    <p class="mb-3 text-xs text-slate-500">Only agents with all selected skills are eligible.</p>
                    <div class="flex flex-wrap gap-2">
                        <button
                            v-for="skill in skills"
                            :key="skill.id"
                            type="button"
                            class="rounded-full px-3 py-1 text-xs font-medium transition"
                            :class="form.skill_ids.includes(skill.id) ? 'bg-blue-600 text-white' : 'bg-slate-100 text-slate-700 hover:bg-slate-200'"
                            @click="toggleSkill(skill.id)"
                        >
                            {{ skill.name }}
                        </button>
                    </div>
                </div>

                <AppToggle v-model="form.is_active" label="Active" description="Paused rules are skipped." />
            </form>

            <template #footer>
                <button type="button" class="rounded-lg border border-slate-300 px-4 py-2 text-sm text-slate-700 hover:bg-slate-50" @click="closeForm">Cancel</button>
                <button type="submit" form="assignment-form" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700" :disabled="form.processing">
                    {{ editingRule ? 'Save changes' : 'Create rule' }}
                </button>
            </template>
        </AppModal>

        <AppConfirmDialog :state="confirm" @close="closeConfirm" @confirm="onConfirm" />
    </SettingsLayout>
</template>
