<script setup>
import { Link, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import SettingsPage from '../../Components/SettingsPage.vue';
import PlanLimitBanner from '../../Components/PlanLimitBanner.vue';
import SettingsSectionNav from '../../Components/SettingsSectionNav.vue';
import { usePlanLimit } from '../../composables/usePlanFeature.js';
import AppModal from '../../Components/AppModal.vue';
import AppConfirmDialog from '../../Components/AppConfirmDialog.vue';
import AppRowActions from '../../Components/AppRowActions.vue';
import AppIconAction from '../../Components/AppIconAction.vue';
import AppDeleteAction from '../../Components/AppDeleteAction.vue';
import CustomFields from '../../Components/CustomFields.vue';
import { useConfirmDialog } from '../../composables/useConfirmDialog.js';
import { useSettingsSection } from '../../composables/useSettingsSection.js';
import { useI18n } from 'vue-i18n';

const props = defineProps({
    employees: Object,
    pendingInvitations: Array,
    roles: Array,
    customFieldDefinitions: { type: Array, default: () => [] },
    teams: { type: Array, default: () => [] },
});

const { t } = useI18n();
const { atLimit: agentLimitReached } = usePlanLimit('agents');

const page = usePage();
const showInvite = ref(false);
const editingMember = ref(null);
const { state: confirm, ask: askConfirm, close: closeConfirm, confirm: onConfirm } = useConfirmDialog();

const { activeSection } = useSettingsSection({
    defaultSection: 'members',
    sections: ['members', 'invitations'],
});

const sectionTabs = computed(() => [
    { id: 'members', label: t('settings.agents') },
    { id: 'invitations', label: t('settings.invitations') },
]);

const inviteForm = useForm({
    email: '',
    role: 'agent',
    team_id: '',
});

const editFieldsForm = useForm({
    custom_fields: {},
});

const closeInvite = () => {
    showInvite.value = false;
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

const updateRole = (memberId, role) => {
    router.put(`/settings/members/${memberId}`, { role }, { preserveScroll: true });
};

const removeMember = (member) => {
    askConfirm({
        title: t('settings_members.remove_member'),
        message: `Remove ${member.name} from the team? They will lose access immediately.`,
        confirmLabel: 'Remove',
        action: () => router.delete(`/settings/members/${member.id}`, { preserveScroll: true }),
    });
};
</script>

<template>
    <SettingsPage :title="$t('settings_members.team_members')" :description="$t('settings_members.invite_members_description')">
        <template #actions>
            <a href="/settings/members/export" class="rounded-lg border agent-border agent-panel px-4 py-2 text-sm font-medium text-slate-700 dark:text-slate-300 transition agent-hover-surface">{{ $t('settings_members.export_csv') }}</a>
            <Link href="/settings/profile" class="text-sm text-blue-600 transition hover:text-blue-700 dark:hover:text-blue-300 dark:text-blue-300">{{ $t('settings.profile') }}</Link>
            <button
                type="button"
                class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white transition hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-50"
                :disabled="agentLimitReached"
                @click="showInvite = true"
            >{{ $t('settings_members.invite_member') }}</button>
        </template>

        <PlanLimitBanner limit-key="agents" />

        <SettingsSectionNav
            path="/settings/members"
            default-section="members"
            :sections="sectionTabs"
            :active-section="activeSection"
        />

        <div
            v-if="page.props.flash?.invite_url"
            class="mb-4 rounded-lg border border-blue-200 dark:border-blue-900/60 bg-blue-50 dark:bg-blue-950/40 px-4 py-3 text-sm text-blue-900"
        >
            <p class="font-medium">{{ $t('settings_members.share_this_invitation_link') }}</p>
            <p class="mt-1 text-xs text-blue-800">{{ $t('settings_members.outbound_email_is_not_configured_copy_the_link_below_for_the_invitee') }}</p>
            <a :href="page.props.flash.invite_url" class="mt-2 block break-all underline">{{ page.props.flash.invite_url }}</a>
        </div>

        <div v-show="activeSection === 'invitations'" class="agent-card">
            <ul class="space-y-3">
                <li v-for="invitation in pendingInvitations" :key="invitation.id" class="text-sm text-slate-700 dark:text-slate-300">
                    <span class="font-medium">{{ invitation.email }}</span>
                    <span class="agent-text-subtle"> · {{ invitation.role }} · invited by {{ invitation.inviter?.name }}</span>
                </li>
                <li v-if="!pendingInvitations.length" class="text-sm agent-text-subtle">{{ $t('settings_members.no_pending_invitations') }}</li>
            </ul>
        </div>

        <div v-show="activeSection === 'members'" class="overflow-hidden rounded-xl border agent-border agent-panel shadow-sm">
            <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-800 dark:divide-slate-700">
                <thead class="agent-panel-muted">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase agent-text-subtle">{{ $t('profile.name') }}</th>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase agent-text-subtle">{{ $t('profile.email') }}</th>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase agent-text-subtle">{{ $t('settings_members.role') }}</th>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase agent-text-subtle">{{ $t('nav.teams') }}</th>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase agent-text-subtle">{{ $t('settings_members.score') }}</th>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase agent-text-subtle">{{ $t('settings_members.actions') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200 dark:divide-slate-800 dark:divide-slate-700">
                    <tr v-for="member in employees.data" :key="member.id" class="agent-hover-surface/60">
                        <td class="px-4 py-3 text-sm font-medium agent-text">
                            <Link :href="`/settings/members/${member.id}`" class="agent-text hover:text-blue-600 dark:hover:text-blue-400">{{ member.name }}</Link>
                        </td>
                        <td class="px-4 py-3 text-sm agent-text-muted">{{ member.email }}</td>
                        <td class="px-4 py-3">
                            <select
                                :value="member.roles[0]?.name"
                                class="rounded-lg border agent-border px-2 py-1 text-sm transition focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20"
                                :disabled="member.id === page.props.auth.user.id"
                                @change="updateRole(member.id, $event.target.value)"
                            >
                                <option v-for="role in roles" :key="role" :value="role">{{ role }}</option>
                            </select>
                        </td>
                        <td class="px-4 py-3 text-sm agent-text-muted">
                            <span v-if="member.teams?.length">{{ member.teams.map((team) => team.name).join(', ') }}</span>
                            <span v-else class="text-slate-400 dark:text-slate-500">—</span>
                        </td>
                        <td class="px-4 py-3">
                            <Link :href="`/settings/performance/${member.id}`" class="text-sm font-medium" :class="member.performance_score >= 80 ? 'text-emerald-700 dark:text-emerald-300' : member.performance_score >= 60 ? 'text-amber-600' : 'text-red-600'">
                                {{ Number(member.performance_score ?? 100).toFixed(1) }}
                            </Link>
                        </td>
                        <td class="px-4 py-3">
                            <AppRowActions>
                                <AppIconAction
                                    icon="view"
                                    variant="primary"
                                    :label="$t('settings_members.view_profile')"
                                    :href="`/settings/members/${member.id}`"
                                />
                                <AppIconAction
                                    v-if="customFieldDefinitions.length"
                                    icon="edit"
                                    variant="violet"
                                    :label="$t('settings_members.edit_fields')"
                                    @click="openEditFields(member)"
                                />
                                <AppDeleteAction
                                    v-if="member.id !== page.props.auth.user.id"
                                    :label="$t('settings_members.remove_member')"
                                    @click="removeMember(member)"
                                />
                            </AppRowActions>
                        </td>
                    </tr>
                    <tr v-if="!employees.data.length">
                        <td colspan="6" class="px-4 py-6 text-center text-sm agent-text-subtle">No team members yet.</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <AppModal
            :open="showInvite"
            :title="$t('settings_members.invite_member')"
            :description="$t('settings_members.send_an_email_invitation_they_set_their_own_password_when_accepting')"
            size="md"
            @close="closeInvite"
        >
            <form id="invite-form" class="space-y-4" @submit.prevent="invite">
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-300">{{ $t('profile.email') }}</label>
                    <input v-model="inviteForm.email" type="email" class="w-full rounded-lg border agent-border px-3 py-2 transition focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20" required />
                    <p v-if="inviteForm.errors.email" class="mt-1 text-sm text-red-600">{{ inviteForm.errors.email }}</p>
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-300">{{ $t('settings_members.role') }}</label>
                    <select v-model="inviteForm.role" class="w-full rounded-lg border agent-border px-3 py-2 transition focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20">
                        <option v-for="role in roles" :key="role" :value="role">{{ role }}</option>
                    </select>
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-300">{{ $t('settings.groups.team') }}</label>
                    <select v-model="inviteForm.team_id" class="w-full rounded-lg border agent-border px-3 py-2 transition focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20">
                        <option value="">{{ $t('settings_members.none') }}</option>
                        <option v-for="team in teams" :key="team.id" :value="team.id">{{ team.name }}</option>
                    </select>
                    <p v-if="inviteForm.errors.team_id" class="mt-1 text-sm text-red-600">{{ inviteForm.errors.team_id }}</p>
                </div>
            </form>

            <template #footer>
                <div class="flex justify-end gap-2">
                    <button type="button" class="rounded-lg border agent-border px-4 py-2 text-sm font-medium text-slate-700 dark:text-slate-300 transition agent-hover-surface" @click="closeInvite">{{ $t('common.cancel') }}</button>
                    <button type="submit" form="invite-form" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white transition hover:bg-blue-700 disabled:opacity-60" :disabled="inviteForm.processing">{{ $t('settings_members.send_invitation') }}</button>
                </div>
            </template>
        </AppModal>

        <AppModal
            :open="!!editingMember"
            :title="editingMember ? `Fields — ${editingMember.name}` : 'Member fields'"
            :description="$t('settings_members.update_optional_team_member_fields')"
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
                    <button type="button" class="rounded-lg border agent-border px-4 py-2 text-sm font-medium text-slate-700 dark:text-slate-300 transition agent-hover-surface" @click="closeEditFields">{{ $t('common.cancel') }}</button>
                    <button type="submit" form="edit-fields-form" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white transition hover:bg-blue-700 disabled:opacity-60" :disabled="editFieldsForm.processing">{{ $t('settings_members.save_fields') }}</button>
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
