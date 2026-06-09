<script setup>
import { Link, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import SettingsLayout from '../../Layouts/SettingsLayout.vue';
import AppModal from '../../Components/AppModal.vue';
import AppConfirmDialog from '../../Components/AppConfirmDialog.vue';
import CustomFields from '../../Components/CustomFields.vue';
import { useConfirmDialog } from '../../composables/useConfirmDialog.js';
import { useSettingsSection } from '../../composables/useSettingsSection.js';

const props = defineProps({
    employees: Object,
    pendingInvitations: Array,
    roles: Array,
    customFieldDefinitions: { type: Array, default: () => [] },
    departments: { type: Array, default: () => [] },
    teams: { type: Array, default: () => [] },
});

const page = usePage();
const showInvite = ref(false);
const showAdd = ref(false);
const editingMember = ref(null);
const { state: confirm, ask: askConfirm, close: closeConfirm, confirm: onConfirm } = useConfirmDialog();

const { activeSection } = useSettingsSection({
    defaultSection: 'members',
    sections: ['members', 'invitations'],
});

const inviteForm = useForm({
    email: '',
    role: 'agent',
    team_id: '',
});

const addForm = useForm({
    name: '',
    email: '',
    role: 'agent',
    department_id: '',
    team_id: '',
    custom_fields: {},
});

const filteredTeams = computed(() =>
    props.teams.filter((team) => !addForm.department_id || team.department_id === Number(addForm.department_id)),
);

watch(() => addForm.department_id, () => {
    if (addForm.team_id && !filteredTeams.value.some((team) => team.id === Number(addForm.team_id))) {
        addForm.team_id = '';
    }
});

const editFieldsForm = useForm({
    custom_fields: {},
});

const closeInvite = () => {
    showInvite.value = false;
};

const closeAdd = () => {
    showAdd.value = false;
};

const openEditFields = (member) => {
    editingMember.value = member;
    editFieldsForm.custom_fields = { ...(member.custom_fields ?? {}) };
};

const closeEditFields = () => {
    editingMember.value = null;
    editFieldsForm.reset();
};

const saveMemberFields = () => {
    editFieldsForm.patch(`/settings/members/${editingMember.value.id}/custom-fields`, {
        preserveScroll: true,
        onSuccess: () => closeEditFields(),
    });
};

const invite = () => {
    inviteForm.transform((data) => ({
        email: data.email,
        role: data.role,
        team_id: data.team_id || null,
    })).post('/settings/members/invite', {
        onSuccess: () => {
            inviteForm.reset();
            inviteForm.role = 'agent';
            closeInvite();
        },
    });
};

const addMember = () => {
    addForm.transform((data) => ({
        name: data.name,
        email: data.email,
        role: data.role,
        team_id: data.team_id || null,
        custom_fields: data.custom_fields,
    })).post('/settings/members', {
        onSuccess: () => {
            addForm.reset();
            addForm.role = 'agent';
            closeAdd();
        },
    });
};

const updateRole = (memberId, role) => {
    router.put(`/settings/members/${memberId}`, { role }, { preserveScroll: true });
};

const removeMember = (member) => {
    askConfirm({
        title: 'Remove member',
        message: `Remove ${member.name} from the team? They will lose access immediately.`,
        confirmLabel: 'Remove',
        action: () => router.delete(`/settings/members/${member.id}`, { preserveScroll: true }),
    });
};
</script>

<template>
    <SettingsLayout title="Team members" description="Add members directly or send email invitations.">
        <template #actions>
            <a href="/settings/members/export" class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50">Export CSV</a>
            <Link href="/settings/profile" class="text-sm text-blue-600 transition hover:text-blue-700">Your profile</Link>
            <button type="button" class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50" @click="showAdd = true">Add member</button>
            <button type="button" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white transition hover:bg-blue-700" @click="showInvite = true">Invite member</button>
        </template>

        <div
            v-if="page.props.flash?.invite_url"
            class="mb-4 rounded-lg border border-blue-200 bg-blue-50 px-4 py-3 text-sm text-blue-900"
        >
            <p class="font-medium">Invitation link (local dev)</p>
            <a :href="page.props.flash.invite_url" class="mt-1 break-all underline">{{ page.props.flash.invite_url }}</a>
        </div>

        <div v-show="activeSection === 'invitations'" class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
            <ul class="space-y-3">
                <li v-for="invitation in pendingInvitations" :key="invitation.id" class="text-sm text-slate-700">
                    <span class="font-medium">{{ invitation.email }}</span>
                    <span class="text-slate-500"> · {{ invitation.role }} · invited by {{ invitation.inviter?.name }}</span>
                </li>
                <li v-if="!pendingInvitations.length" class="text-sm text-slate-500">No pending invitations.</li>
            </ul>
        </div>

        <div v-show="activeSection === 'members'" class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
            <table class="min-w-full divide-y divide-slate-200">
                <thead class="bg-slate-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase text-slate-500">Name</th>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase text-slate-500">Email</th>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase text-slate-500">Role</th>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase text-slate-500">Teams</th>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase text-slate-500">Score</th>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase text-slate-500">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200">
                    <tr v-for="member in employees.data" :key="member.id" class="hover:bg-slate-50/60">
                        <td class="px-4 py-3 text-sm font-medium text-slate-900">
                            <Link :href="`/settings/members/${member.id}`" class="text-slate-900 hover:text-blue-600">{{ member.name }}</Link>
                        </td>
                        <td class="px-4 py-3 text-sm text-slate-600">{{ member.email }}</td>
                        <td class="px-4 py-3">
                            <select
                                :value="member.roles[0]?.name"
                                class="rounded-lg border border-slate-300 px-2 py-1 text-sm transition focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20"
                                :disabled="member.id === page.props.auth.user.id"
                                @change="updateRole(member.id, $event.target.value)"
                            >
                                <option v-for="role in roles" :key="role" :value="role">{{ role }}</option>
                            </select>
                        </td>
                        <td class="px-4 py-3 text-sm text-slate-600">
                            <span v-if="member.teams?.length">{{ member.teams.map((team) => team.name).join(', ') }}</span>
                            <span v-else class="text-slate-400">—</span>
                        </td>
                        <td class="px-4 py-3">
                            <Link :href="`/settings/performance/${member.id}`" class="text-sm font-medium" :class="member.performance_score >= 80 ? 'text-emerald-700' : member.performance_score >= 60 ? 'text-amber-600' : 'text-red-600'">
                                {{ Number(member.performance_score ?? 100).toFixed(1) }}
                            </Link>
                        </td>
                        <td class="px-4 py-3">
                            <div class="flex items-center gap-1">
                                <Link
                                    :href="`/settings/members/${member.id}`"
                                    class="rounded-lg p-2 text-slate-400 transition hover:bg-blue-50 hover:text-blue-600"
                                    title="View profile"
                                    aria-label="View profile"
                                >
                                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                    </svg>
                                </Link>
                                <button
                                    v-if="customFieldDefinitions.length"
                                    type="button"
                                    class="rounded-lg p-2 text-slate-400 transition hover:bg-violet-50 hover:text-violet-600"
                                    title="Edit fields"
                                    aria-label="Edit fields"
                                    @click="openEditFields(member)"
                                >
                                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                    </svg>
                                </button>
                                <button
                                    v-if="member.id !== page.props.auth.user.id"
                                    type="button"
                                    class="rounded-lg p-2 text-slate-400 transition hover:bg-red-50 hover:text-red-600"
                                    title="Remove member"
                                    aria-label="Remove member"
                                    @click="removeMember(member)"
                                >
                                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                    </svg>
                                </button>
                            </div>
                        </td>
                    </tr>
                    <tr v-if="!employees.data.length">
                        <td colspan="6" class="px-4 py-6 text-center text-sm text-slate-500">No team members yet.</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <AppModal
            :open="showAdd"
            title="Add member"
            description="Create an account and assign them to a department team. They'll receive an email to set their password."
            size="md"
            @close="closeAdd"
        >
            <form id="add-form" class="space-y-4" @submit.prevent="addMember">
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">Name</label>
                    <input v-model="addForm.name" type="text" class="w-full rounded-lg border border-slate-300 px-3 py-2 transition focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20" required />
                    <p v-if="addForm.errors.name" class="mt-1 text-sm text-red-600">{{ addForm.errors.name }}</p>
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">Email</label>
                    <input v-model="addForm.email" type="email" class="w-full rounded-lg border border-slate-300 px-3 py-2 transition focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20" required />
                    <p v-if="addForm.errors.email" class="mt-1 text-sm text-red-600">{{ addForm.errors.email }}</p>
                </div>
                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label class="mb-1 block text-sm font-medium text-slate-700">Department</label>
                        <select v-model="addForm.department_id" class="w-full rounded-lg border border-slate-300 px-3 py-2 transition focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20">
                            <option value="">None</option>
                            <option v-for="department in departments" :key="department.id" :value="department.id">{{ department.name }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-slate-700">Team</label>
                        <select v-model="addForm.team_id" class="w-full rounded-lg border border-slate-300 px-3 py-2 transition focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20" :disabled="!filteredTeams.length">
                            <option value="">None</option>
                            <option v-for="team in filteredTeams" :key="team.id" :value="team.id">{{ team.name }}</option>
                        </select>
                        <p v-if="addForm.department_id && !filteredTeams.length" class="mt-1 text-xs text-slate-500">No teams in this department.</p>
                        <p v-if="addForm.errors.team_id" class="mt-1 text-sm text-red-600">{{ addForm.errors.team_id }}</p>
                    </div>
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">Role</label>
                    <select v-model="addForm.role" class="w-full rounded-lg border border-slate-300 px-3 py-2 transition focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20">
                        <option v-for="role in roles" :key="role" :value="role">{{ role }}</option>
                    </select>
                </div>
                <CustomFields
                    v-model="addForm.custom_fields"
                    :definitions="customFieldDefinitions"
                    :errors="addForm.errors"
                />
            </form>

            <template #footer>
                <div class="flex justify-end gap-2">
                    <button type="button" class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 transition hover:bg-white" @click="closeAdd">Cancel</button>
                    <button type="submit" form="add-form" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white transition hover:bg-blue-700 disabled:opacity-60" :disabled="addForm.processing">
                        Add member
                    </button>
                </div>
            </template>
        </AppModal>

        <AppModal
            :open="showInvite"
            title="Invite member"
            description="Send an email invitation. They set their own password when accepting."
            size="md"
            @close="closeInvite"
        >
            <form id="invite-form" class="space-y-4" @submit.prevent="invite">
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">Email</label>
                    <input v-model="inviteForm.email" type="email" class="w-full rounded-lg border border-slate-300 px-3 py-2 transition focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20" required />
                    <p v-if="inviteForm.errors.email" class="mt-1 text-sm text-red-600">{{ inviteForm.errors.email }}</p>
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">Role</label>
                    <select v-model="inviteForm.role" class="w-full rounded-lg border border-slate-300 px-3 py-2 transition focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20">
                        <option v-for="role in roles" :key="role" :value="role">{{ role }}</option>
                    </select>
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">Team</label>
                    <select v-model="inviteForm.team_id" class="w-full rounded-lg border border-slate-300 px-3 py-2 transition focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20">
                        <option value="">None</option>
                        <option v-for="team in teams" :key="team.id" :value="team.id">{{ team.name }}</option>
                    </select>
                    <p v-if="inviteForm.errors.team_id" class="mt-1 text-sm text-red-600">{{ inviteForm.errors.team_id }}</p>
                </div>
            </form>

            <template #footer>
                <div class="flex justify-end gap-2">
                    <button type="button" class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 transition hover:bg-white" @click="closeInvite">Cancel</button>
                    <button type="submit" form="invite-form" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white transition hover:bg-blue-700 disabled:opacity-60" :disabled="inviteForm.processing">
                        Send invitation
                    </button>
                </div>
            </template>
        </AppModal>

        <AppModal
            :open="!!editingMember"
            :title="editingMember ? `Fields — ${editingMember.name}` : 'Member fields'"
            description="Update optional team member fields."
            size="md"
            @close="closeEditFields"
        >
            <form id="edit-fields-form" @submit.prevent="saveMemberFields">
                <CustomFields
                    v-model="editFieldsForm.custom_fields"
                    :definitions="customFieldDefinitions"
                    :errors="editFieldsForm.errors"
                />
            </form>

            <template #footer>
                <div class="flex justify-end gap-2">
                    <button type="button" class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 transition hover:bg-white" @click="closeEditFields">Cancel</button>
                    <button type="submit" form="edit-fields-form" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white transition hover:bg-blue-700 disabled:opacity-60" :disabled="editFieldsForm.processing">
                        Save fields
                    </button>
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
