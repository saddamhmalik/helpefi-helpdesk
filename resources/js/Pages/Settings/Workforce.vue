<script setup>
import { router, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import SettingsPage from '../../Components/SettingsPage.vue';
import AppModal from '../../Components/AppModal.vue';
import AppToggle from '../../Components/AppToggle.vue';
import AppConfirmDialog from '../../Components/AppConfirmDialog.vue';
import AppRowActions from '../../Components/AppRowActions.vue';
import AppEditAction from '../../Components/AppEditAction.vue';
import AppDeleteAction from '../../Components/AppDeleteAction.vue';
import { useConfirmDialog } from '../../composables/useConfirmDialog.js';
import { useI18n } from 'vue-i18n';

const props = defineProps({
    departments: Array,
    agents: Array,
    meta: Object,
});

const { t } = useI18n();

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
        title: t('settings_workforce.delete_department'),
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
        title: t('settings_workforce.delete_team'),
        message: `Delete team "${team.name}"?`,
        confirmLabel: 'Delete',
        action: () => router.delete(`/settings/workforce/teams/${team.id}`, { preserveScroll: true }),
    });
};
</script>

<template>
    <SettingsPage :title="$t('settings.teams_departments')" :description="$t('settings.descriptions.teams_departments')" info-section="teams_departments">
        <template #actions>
            <button type="button" class="rounded-lg border agent-border px-4 py-2 text-sm text-slate-700 dark:text-slate-300 transition agent-hover-surface" @click="openCreateDepartment">{{ $t('settings_workforce.add_department') }}</button>
            <button type="button" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white transition hover:bg-blue-700" @click="openCreateTeam()">{{ $t('settings_workforce.add_team') }}</button>
        </template>

        <div class="mb-8 grid gap-4 sm:grid-cols-3">
            <div class="rounded-2xl border agent-border agent-panel px-5 py-4 shadow-sm">
                <p class="text-xs font-medium uppercase tracking-wide agent-text-subtle">{{ $t('nav.departments') }}</p>
                <p class="mt-1 text-3xl font-bold tabular-nums agent-text">{{ departments.length }}</p>
            </div>
            <div class="rounded-2xl border agent-border agent-panel px-5 py-4 shadow-sm">
                <p class="text-xs font-medium uppercase tracking-wide agent-text-subtle">{{ $t('nav.teams') }}</p>
                <p class="mt-1 text-3xl font-bold tabular-nums agent-text">{{ totalTeams }}</p>
            </div>
            <div class="rounded-2xl border agent-border agent-panel px-5 py-4 shadow-sm">
                <p class="text-xs font-medium uppercase tracking-wide agent-text-subtle">{{ $t('settings.agents') }}</p>
                <p class="mt-1 text-3xl font-bold tabular-nums agent-text">{{ totalAgents }}</p>
            </div>
        </div>

        <div class="space-y-4">
            <div v-for="department in departments" :key="department.id" class="overflow-hidden rounded-2xl border agent-border agent-panel shadow-sm">
                <button
                    type="button"
                    class="flex w-full items-start justify-between gap-4 px-6 py-5 text-left transition agent-hover-surface/60"
                    @click="toggleDepartment(department.id)"
                >
                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-center gap-2">
                            <h2 class="text-lg font-semibold agent-text">{{ department.name }}</h2>
                            <span class="rounded-full bg-slate-100 dark:bg-slate-900 px-2.5 py-0.5 text-xs font-medium agent-text-muted">
                                {{ department.teams_count ?? department.teams?.length ?? 0 }} teams
                            </span>
                            <span class="rounded-full px-2 py-0.5 text-xs" :class="department.is_active ? 'bg-emerald-100 text-emerald-800 dark:text-emerald-200' : 'bg-slate-100 dark:bg-slate-900 agent-text-muted'">
                                {{ department.is_active ? 'Active' : 'Hidden' }}
                            </span>
                        </div>
                        <p v-if="department.description" class="mt-1 text-sm agent-text-muted">{{ department.description }}</p>
                        <p class="mt-2 text-xs agent-text-subtle">
                            Head: <span class="font-medium text-slate-700 dark:text-slate-300">{{ department.head?.name ?? 'Not assigned' }}</span>
                        </p>
                    </div>
                    <div class="flex shrink-0 items-center gap-2">
                        <button type="button" class="rounded-lg border agent-border px-3 py-1.5 text-sm text-slate-700 dark:text-slate-300 agent-hover-surface" @click.stop="openCreateTeam(department.id)">{{ $t('settings_workforce.add_team') }}</button>
                        <AppRowActions>
                            <AppEditAction :label="$t('settings_workforce.edit')" @click.stop="openEditDepartment(department)" />
                            <AppDeleteAction :label="$t('settings_workforce.delete')" @click.stop="destroyDepartment(department)" />
                        </AppRowActions>
                        <svg class="h-5 w-5 text-slate-400 dark:text-slate-500 transition" :class="isExpanded(department.id) ? 'rotate-180' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                        </svg>
                    </div>
                </button>

                <div v-show="isExpanded(department.id)" class="border-t agent-border-subtle agent-panel-muted/40 px-6 py-4">
                    <div v-if="department.teams?.length" class="space-y-3">
                        <div v-for="team in department.teams" :key="team.id" class="rounded-xl border agent-border agent-panel p-4">
                            <div class="flex flex-wrap items-start justify-between gap-3">
                                <div>
                                    <div class="flex flex-wrap items-center gap-2">
                                        <p class="font-medium agent-text">{{ team.name }}</p>
                                        <span class="rounded-full bg-blue-50 dark:bg-blue-950/40 px-2 py-0.5 text-xs font-medium text-blue-700 dark:text-blue-300">{{ team.members_count ?? team.members?.length ?? 0 }} members</span>
                                        <span class="rounded-full bg-violet-50 dark:bg-violet-950/40 px-2 py-0.5 text-xs font-medium text-violet-700 dark:text-violet-300">{{ team.tickets_count ?? 0 }} tickets</span>
                                        <span v-if="!team.is_active" class="rounded-full bg-slate-100 dark:bg-slate-900 px-2 py-0.5 text-xs agent-text-subtle">{{ $t('settings_workforce.hidden') }}</span>
                                    </div>
                                    <p v-if="team.description" class="mt-1 text-sm agent-text-muted">{{ team.description }}</p>
                                    <p class="mt-2 text-xs agent-text-subtle">Lead: <span class="font-medium text-slate-700 dark:text-slate-300">{{ team.lead?.name ?? 'Not assigned' }}</span></p>
                                    <div v-if="team.members?.length" class="mt-3 flex flex-wrap gap-1.5">
                                        <span v-for="member in team.members" :key="member.id" class="rounded-full bg-slate-100 dark:bg-slate-900 px-2.5 py-0.5 text-xs text-slate-700 dark:text-slate-300">
                                            {{ member.name }}
                                        </span>
                                    </div>
                                </div>
                                <div class="flex gap-2">
                                    <AppRowActions>
                                        <AppEditAction :label="$t('settings_workforce.edit')" @click="openEditTeam(team)" />
                                        <AppDeleteAction :label="$t('settings_workforce.delete')" @click="destroyTeam(team)" />
                                    </AppRowActions>
                                </div>
                            </div>
                        </div>
                    </div>
                    <p v-else class="py-6 text-center text-sm agent-text-subtle">{{ $t('settings_workforce.no_teams_in_this_department_yet') }}</p>
                </div>
            </div>

            <p v-if="!departments.length" class="rounded-2xl border border-dashed agent-border px-6 py-16 text-center text-sm agent-text-subtle">
                {{ $t('settings_workforce.no_departments_yet_create_one_to_start_organizing_your_team') }}
            </p>
        </div>

        <AppModal :open="showDepartmentForm" :title="editingDepartment ? 'Edit department' : 'Add department'" size="md" @close="showDepartmentForm = false">
            <form id="department-form" class="space-y-4" @submit.prevent="saveDepartment">
                <input v-model="departmentForm.name" type="text" required :placeholder="$t('settings_workforce.department_name')" class="w-full rounded-lg border agent-border px-3 py-2 text-sm" />
                <textarea v-model="departmentForm.description" rows="2" :placeholder="$t('settings_workforce.description')" class="w-full rounded-lg border agent-border px-3 py-2 text-sm" />
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-300">{{ $t('settings_workforce.department_head') }}</label>
                    <select v-model="departmentForm.head_user_id" class="w-full rounded-lg border agent-border px-3 py-2 text-sm">
                        <option value="">{{ $t('settings_workforce.select_head') }}</option>
                        <option v-for="agent in agents" :key="agent.id" :value="agent.id">{{ agent.name }}</option>
                    </select>
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-300">{{ $t('settings_workforce.sort_order') }}</label>
                    <input v-model.number="departmentForm.sort_order" type="number" min="0" class="w-full rounded-lg border agent-border px-3 py-2 text-sm" />
                </div>
                <AppToggle v-model="departmentForm.is_active" :label="$t('common.active')" />
            </form>
            <template #footer>
                <div class="flex justify-end gap-2">
                    <button type="button" class="rounded-lg border agent-border px-4 py-2 text-sm" @click="showDepartmentForm = false">{{ $t('common.cancel') }}</button>
                    <button type="submit" form="department-form" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white" :disabled="departmentForm.processing">{{ $t('common.save') }}</button>
                </div>
            </template>
        </AppModal>

        <AppModal :open="showTeamForm" :title="editingTeam ? 'Edit team' : 'Add team'" variant="drawer" @close="showTeamForm = false">
            <form id="team-form" class="space-y-4" @submit.prevent="saveTeam">
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-300">{{ $t('settings_workforce.department') }}</label>
                    <select v-model="teamForm.department_id" required class="w-full rounded-lg border agent-border px-3 py-2 text-sm">
                        <option v-for="department in departments" :key="department.id" :value="department.id">{{ department.name }}</option>
                    </select>
                </div>
                <input v-model="teamForm.name" type="text" required :placeholder="$t('settings_workforce.team_name')" class="w-full rounded-lg border agent-border px-3 py-2 text-sm" />
                <textarea v-model="teamForm.description" rows="2" :placeholder="$t('settings_workforce.description')" class="w-full rounded-lg border agent-border px-3 py-2 text-sm" />
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-300">{{ $t('settings_workforce.team_lead') }}</label>
                    <select v-model="teamForm.lead_user_id" class="w-full rounded-lg border agent-border px-3 py-2 text-sm">
                        <option value="">{{ $t('settings_workforce.select_lead') }}</option>
                        <option v-for="agent in agents" :key="agent.id" :value="agent.id">{{ agent.name }}</option>
                    </select>
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-300">{{ $t('settings_workforce.sort_order') }}</label>
                    <input v-model.number="teamForm.sort_order" type="number" min="0" class="w-full rounded-lg border agent-border px-3 py-2 text-sm" />
                </div>

                <div>
                    <div class="mb-2 flex items-center justify-between">
                        <p class="text-sm font-medium text-slate-700 dark:text-slate-300">{{ $t('settings_workforce.members') }}</p>
                        <button type="button" class="text-sm text-blue-600" @click="addMember">{{ $t('settings_workforce.add_member') }}</button>
                    </div>
                    <div v-for="(member, index) in teamForm.members" :key="index" class="mb-2 grid gap-2 sm:grid-cols-[1fr_1fr_auto]">
                        <select v-model="member.user_id" required class="rounded-lg border agent-border px-2 py-2 text-sm">
                            <option value="">{{ $t('settings_workforce.select_agent') }}</option>
                            <option v-for="agent in agents" :key="agent.id" :value="agent.id">{{ agent.name }}</option>
                        </select>
                        <select v-model="member.org_role" class="rounded-lg border agent-border px-2 py-2 text-sm">
                            <option v-for="role in meta.org_roles" :key="role.value" :value="role.value">{{ role.label }}</option>
                        </select>
                        <button type="button" class="text-sm text-red-600" @click="removeMember(index)">{{ $t('settings_workforce.remove') }}</button>
                    </div>
                </div>

                <AppToggle v-model="teamForm.is_active" :label="$t('common.active')" />
            </form>
            <template #footer>
                <div class="flex justify-end gap-2">
                    <button type="button" class="rounded-lg border agent-border px-4 py-2 text-sm" @click="showTeamForm = false">{{ $t('common.cancel') }}</button>
                    <button type="submit" form="team-form" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white" :disabled="teamForm.processing">{{ $t('common.save') }}</button>
                </div>
            </template>
        </AppModal>

        <AppConfirmDialog :open="confirm.open" :title="confirm.title" :message="confirm.message" :confirm-label="confirm.confirmLabel" :variant="confirm.variant" @close="closeConfirm" @confirm="onConfirm" />
    </SettingsPage>
</template>
