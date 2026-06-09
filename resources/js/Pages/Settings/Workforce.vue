<script setup>
import { router, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import SettingsLayout from '../../Layouts/SettingsLayout.vue';
import AppModal from '../../Components/AppModal.vue';
import AppToggle from '../../Components/AppToggle.vue';
import AppConfirmDialog from '../../Components/AppConfirmDialog.vue';
import { useConfirmDialog } from '../../composables/useConfirmDialog.js';

const props = defineProps({
    departments: Array,
    agents: Array,
    meta: Object,
});

const showDepartmentForm = ref(false);
const showTeamForm = ref(false);
const editingDepartment = ref(null);
const editingTeam = ref(null);
const expandedDepartments = ref(new Set(props.departments.map((d) => d.id)));
const { state: confirm, ask: askConfirm, close: closeConfirm, confirm: onConfirm } = useConfirmDialog();

const totalTeams = computed(() => props.departments.reduce((sum, dept) => sum + (dept.teams?.length ?? 0), 0));
const totalAgents = computed(() => props.agents?.length ?? 0);

const blankDepartment = () => ({
    name: '',
    description: '',
    head_user_id: '',
    is_active: true,
    sort_order: 0,
});

const blankTeam = () => ({
    department_id: props.departments[0]?.id ?? '',
    name: '',
    description: '',
    lead_user_id: '',
    is_active: true,
    sort_order: 0,
    members: [],
});

const departmentForm = useForm(blankDepartment());
const teamForm = useForm(blankTeam());

const toggleDepartment = (departmentId) => {
    const next = new Set(expandedDepartments.value);
    if (next.has(departmentId)) {
        next.delete(departmentId);
    } else {
        next.add(departmentId);
    }
    expandedDepartments.value = next;
};

const isExpanded = (departmentId) => expandedDepartments.value.has(departmentId);

const openCreateDepartment = () => {
    editingDepartment.value = null;
    departmentForm.defaults(blankDepartment());
    departmentForm.reset();
    showDepartmentForm.value = true;
};

const openEditDepartment = (department) => {
    editingDepartment.value = department;
    departmentForm.defaults({
        name: department.name,
        description: department.description ?? '',
        head_user_id: department.head_user_id ?? '',
        is_active: department.is_active,
        sort_order: department.sort_order ?? 0,
    });
    departmentForm.reset();
    showDepartmentForm.value = true;
};

const saveDepartment = () => {
    const options = {
        onSuccess: () => { showDepartmentForm.value = false; },
        preserveScroll: true,
    };

    if (editingDepartment.value) {
        departmentForm.put(`/settings/workforce/departments/${editingDepartment.value.id}`, options);
    } else {
        departmentForm.post('/settings/workforce/departments', options);
    }
};

const destroyDepartment = (department) => {
    askConfirm({
        title: 'Delete department',
        message: `Delete "${department.name}" and all its teams?`,
        confirmLabel: 'Delete',
        action: () => router.delete(`/settings/workforce/departments/${department.id}`, { preserveScroll: true }),
    });
};

const openCreateTeam = (departmentId = null) => {
    editingTeam.value = null;
    teamForm.defaults({ ...blankTeam(), department_id: departmentId ?? props.departments[0]?.id ?? '' });
    teamForm.reset();
    showTeamForm.value = true;
};

const openEditTeam = (team) => {
    editingTeam.value = team;
    teamForm.defaults({
        department_id: team.department_id,
        name: team.name,
        description: team.description ?? '',
        lead_user_id: team.lead_user_id ?? '',
        is_active: team.is_active,
        sort_order: team.sort_order ?? 0,
        members: (team.members ?? []).map((member) => ({
            user_id: member.id,
            org_role: member.pivot?.org_role ?? 'member',
        })),
    });
    teamForm.reset();
    showTeamForm.value = true;
};

const addMember = () => {
    teamForm.members.push({ user_id: '', org_role: 'member' });
};

const removeMember = (index) => {
    teamForm.members.splice(index, 1);
};

const saveTeam = () => {
    const options = {
        onSuccess: () => { showTeamForm.value = false; },
        preserveScroll: true,
    };

    if (editingTeam.value) {
        teamForm.put(`/settings/workforce/teams/${editingTeam.value.id}`, options);
    } else {
        teamForm.post('/settings/workforce/teams', options);
    }
};

const destroyTeam = (team) => {
    askConfirm({
        title: 'Delete team',
        message: `Delete team "${team.name}"?`,
        confirmLabel: 'Delete',
        action: () => router.delete(`/settings/workforce/teams/${team.id}`, { preserveScroll: true }),
    });
};
</script>

<template>
    <SettingsLayout title="Departments & teams" description="Organize agents into departments with team leads and department heads.">
        <template #actions>
            <button type="button" class="rounded-lg border border-slate-300 px-4 py-2 text-sm text-slate-700 transition hover:bg-slate-50" @click="openCreateDepartment">Add department</button>
            <button type="button" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white transition hover:bg-blue-700" @click="openCreateTeam()">Add team</button>
        </template>

        <div class="mb-8 grid gap-4 sm:grid-cols-3">
            <div class="rounded-2xl border border-slate-200 bg-white px-5 py-4 shadow-sm">
                <p class="text-xs font-medium uppercase tracking-wide text-slate-500">Departments</p>
                <p class="mt-1 text-3xl font-bold tabular-nums text-slate-900">{{ departments.length }}</p>
            </div>
            <div class="rounded-2xl border border-slate-200 bg-white px-5 py-4 shadow-sm">
                <p class="text-xs font-medium uppercase tracking-wide text-slate-500">Teams</p>
                <p class="mt-1 text-3xl font-bold tabular-nums text-slate-900">{{ totalTeams }}</p>
            </div>
            <div class="rounded-2xl border border-slate-200 bg-white px-5 py-4 shadow-sm">
                <p class="text-xs font-medium uppercase tracking-wide text-slate-500">Agents</p>
                <p class="mt-1 text-3xl font-bold tabular-nums text-slate-900">{{ totalAgents }}</p>
            </div>
        </div>

        <div class="space-y-4">
            <div v-for="department in departments" :key="department.id" class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                <button
                    type="button"
                    class="flex w-full items-start justify-between gap-4 px-6 py-5 text-left transition hover:bg-slate-50/60"
                    @click="toggleDepartment(department.id)"
                >
                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-center gap-2">
                            <h2 class="text-lg font-semibold text-slate-900">{{ department.name }}</h2>
                            <span class="rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-medium text-slate-600">
                                {{ department.teams_count ?? department.teams?.length ?? 0 }} teams
                            </span>
                            <span class="rounded-full px-2 py-0.5 text-xs" :class="department.is_active ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-100 text-slate-600'">
                                {{ department.is_active ? 'Active' : 'Hidden' }}
                            </span>
                        </div>
                        <p v-if="department.description" class="mt-1 text-sm text-slate-600">{{ department.description }}</p>
                        <p class="mt-2 text-xs text-slate-500">
                            Head: <span class="font-medium text-slate-700">{{ department.head?.name ?? 'Not assigned' }}</span>
                        </p>
                    </div>
                    <div class="flex shrink-0 items-center gap-2">
                        <button type="button" class="rounded-lg border border-slate-300 px-3 py-1.5 text-sm text-slate-700 hover:bg-white" @click.stop="openCreateTeam(department.id)">Add team</button>
                        <button type="button" class="rounded-lg border border-slate-300 px-3 py-1.5 text-sm text-slate-700 hover:bg-white" @click.stop="openEditDepartment(department)">Edit</button>
                        <button type="button" class="rounded-lg border border-red-200 px-3 py-1.5 text-sm text-red-700 hover:bg-red-50" @click.stop="destroyDepartment(department)">Delete</button>
                        <svg class="h-5 w-5 text-slate-400 transition" :class="isExpanded(department.id) ? 'rotate-180' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                        </svg>
                    </div>
                </button>

                <div v-show="isExpanded(department.id)" class="border-t border-slate-100 bg-slate-50/40 px-6 py-4">
                    <div v-if="department.teams?.length" class="space-y-3">
                        <div v-for="team in department.teams" :key="team.id" class="rounded-xl border border-slate-200 bg-white p-4">
                            <div class="flex flex-wrap items-start justify-between gap-3">
                                <div>
                                    <div class="flex flex-wrap items-center gap-2">
                                        <p class="font-medium text-slate-900">{{ team.name }}</p>
                                        <span class="rounded-full bg-blue-50 px-2 py-0.5 text-xs font-medium text-blue-700">{{ team.members_count ?? team.members?.length ?? 0 }} members</span>
                                        <span class="rounded-full bg-violet-50 px-2 py-0.5 text-xs font-medium text-violet-700">{{ team.tickets_count ?? 0 }} tickets</span>
                                        <span v-if="!team.is_active" class="rounded-full bg-slate-100 px-2 py-0.5 text-xs text-slate-500">Hidden</span>
                                    </div>
                                    <p v-if="team.description" class="mt-1 text-sm text-slate-600">{{ team.description }}</p>
                                    <p class="mt-2 text-xs text-slate-500">Lead: <span class="font-medium text-slate-700">{{ team.lead?.name ?? 'Not assigned' }}</span></p>
                                    <div v-if="team.members?.length" class="mt-3 flex flex-wrap gap-1.5">
                                        <span v-for="member in team.members" :key="member.id" class="rounded-full bg-slate-100 px-2.5 py-0.5 text-xs text-slate-700">
                                            {{ member.name }}
                                        </span>
                                    </div>
                                </div>
                                <div class="flex gap-2">
                                    <button type="button" class="text-sm text-blue-600 hover:text-blue-700" @click="openEditTeam(team)">Edit</button>
                                    <button type="button" class="text-sm text-red-600 hover:text-red-700" @click="destroyTeam(team)">Delete</button>
                                </div>
                            </div>
                        </div>
                    </div>
                    <p v-else class="py-6 text-center text-sm text-slate-500">No teams in this department yet.</p>
                </div>
            </div>

            <p v-if="!departments.length" class="rounded-2xl border border-dashed border-slate-300 px-6 py-16 text-center text-sm text-slate-500">
                No departments yet. Create one to start organizing your team.
            </p>
        </div>

        <AppModal :open="showDepartmentForm" :title="editingDepartment ? 'Edit department' : 'Add department'" size="md" @close="showDepartmentForm = false">
            <form id="department-form" class="space-y-4" @submit.prevent="saveDepartment">
                <input v-model="departmentForm.name" type="text" required placeholder="Department name" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" />
                <textarea v-model="departmentForm.description" rows="2" placeholder="Description" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" />
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">Department head</label>
                    <select v-model="departmentForm.head_user_id" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                        <option value="">Select head</option>
                        <option v-for="agent in agents" :key="agent.id" :value="agent.id">{{ agent.name }}</option>
                    </select>
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">Sort order</label>
                    <input v-model.number="departmentForm.sort_order" type="number" min="0" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" />
                </div>
                <AppToggle v-model="departmentForm.is_active" label="Active" />
            </form>
            <template #footer>
                <div class="flex justify-end gap-2">
                    <button type="button" class="rounded-lg border border-slate-300 px-4 py-2 text-sm" @click="showDepartmentForm = false">Cancel</button>
                    <button type="submit" form="department-form" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white" :disabled="departmentForm.processing">Save</button>
                </div>
            </template>
        </AppModal>

        <AppModal :open="showTeamForm" :title="editingTeam ? 'Edit team' : 'Add team'" variant="drawer" @close="showTeamForm = false">
            <form id="team-form" class="space-y-4" @submit.prevent="saveTeam">
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">Department</label>
                    <select v-model="teamForm.department_id" required class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                        <option v-for="department in departments" :key="department.id" :value="department.id">{{ department.name }}</option>
                    </select>
                </div>
                <input v-model="teamForm.name" type="text" required placeholder="Team name" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" />
                <textarea v-model="teamForm.description" rows="2" placeholder="Description" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" />
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">Team lead</label>
                    <select v-model="teamForm.lead_user_id" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                        <option value="">Select lead</option>
                        <option v-for="agent in agents" :key="agent.id" :value="agent.id">{{ agent.name }}</option>
                    </select>
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">Sort order</label>
                    <input v-model.number="teamForm.sort_order" type="number" min="0" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" />
                </div>

                <div>
                    <div class="mb-2 flex items-center justify-between">
                        <p class="text-sm font-medium text-slate-700">Members</p>
                        <button type="button" class="text-sm text-blue-600" @click="addMember">Add member</button>
                    </div>
                    <div v-for="(member, index) in teamForm.members" :key="index" class="mb-2 grid gap-2 sm:grid-cols-[1fr_1fr_auto]">
                        <select v-model="member.user_id" required class="rounded-lg border border-slate-300 px-2 py-2 text-sm">
                            <option value="">Select agent</option>
                            <option v-for="agent in agents" :key="agent.id" :value="agent.id">{{ agent.name }}</option>
                        </select>
                        <select v-model="member.org_role" class="rounded-lg border border-slate-300 px-2 py-2 text-sm">
                            <option v-for="role in meta.org_roles" :key="role.value" :value="role.value">{{ role.label }}</option>
                        </select>
                        <button type="button" class="text-sm text-red-600" @click="removeMember(index)">Remove</button>
                    </div>
                </div>

                <AppToggle v-model="teamForm.is_active" label="Active" />
            </form>
            <template #footer>
                <div class="flex justify-end gap-2">
                    <button type="button" class="rounded-lg border border-slate-300 px-4 py-2 text-sm" @click="showTeamForm = false">Cancel</button>
                    <button type="submit" form="team-form" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white" :disabled="teamForm.processing">Save</button>
                </div>
            </template>
        </AppModal>

        <AppConfirmDialog :open="confirm.open" :title="confirm.title" :message="confirm.message" :confirm-label="confirm.confirmLabel" :variant="confirm.variant" @close="closeConfirm" @confirm="onConfirm" />
    </SettingsLayout>
</template>
