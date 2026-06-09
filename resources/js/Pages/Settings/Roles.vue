<script setup>
import { router, useForm } from '@inertiajs/vue3';
import { computed, reactive, ref } from 'vue';
import SettingsLayout from '../../Layouts/SettingsLayout.vue';
import AppModal from '../../Components/AppModal.vue';
import AppConfirmDialog from '../../Components/AppConfirmDialog.vue';
import { avatarColor } from '../../Components/ticketMessage.js';
import { useConfirmDialog } from '../../composables/useConfirmDialog.js';

const props = defineProps({
    roles: Array,
    catalog: Array,
    protectedRoles: Array,
});

const showCreate = ref(false);
const editingRole = ref(null);
const { state: confirm, ask: askConfirm, close: closeConfirm, confirm: onConfirm } = useConfirmDialog();

const createForm = useForm({
    name: '',
    permissions: ['access.agent'],
});

const editForms = reactive({});

props.roles.forEach((role) => {
    editForms[role.id] = useForm({
        name: role.name,
        permissions: role.permissions.map((p) => p.name),
    });
});

const totalPermissions = computed(() =>
    props.catalog.reduce((count, group) => count + group.permissions.length, 0),
);

const sortedRoles = computed(() =>
    [...props.roles].sort((left, right) => {
        const leftProtected = isProtected(left);
        const rightProtected = isProtected(right);

        if (leftProtected !== rightProtected) {
            return leftProtected ? -1 : 1;
        }

        return left.name.localeCompare(right.name);
    }),
);

const formatRoleName = (name) =>
    name
        .split('_')
        .map((part) => part.charAt(0).toUpperCase() + part.slice(1))
        .join(' ');

const roleInitials = (name) => {
    const parts = name.split('_').filter(Boolean);

    if (parts.length === 1) {
        return parts[0].slice(0, 2).toUpperCase();
    }

    return `${parts[0].charAt(0)}${parts[parts.length - 1].charAt(0)}`.toUpperCase();
};

const permissionGroupsForRole = (role) =>
    props.catalog
        .map((group) => ({
            group: group.group,
            count: group.permissions.filter((permission) =>
                role.permissions.some((assigned) => assigned.name === permission.name),
            ).length,
        }))
        .filter((group) => group.count > 0);

const closeCreate = () => {
    showCreate.value = false;
};

const closeEdit = () => {
    editingRole.value = null;
};

const createRole = () => {
    createForm.post('/settings/roles', {
        preserveScroll: true,
        onSuccess: () => {
            createForm.reset();
            createForm.permissions = ['access.agent'];
            closeCreate();
        },
    });
};

const openEdit = (role) => {
    editingRole.value = role;
};

const saveRole = (role) => {
    editForms[role.id].put(`/settings/roles/${role.id}`, {
        preserveScroll: true,
        onSuccess: closeEdit,
    });
};

const deleteRole = (role) => {
    askConfirm({
        title: 'Delete role',
        message: `Delete role "${formatRoleName(role.name)}"? Members with this role will need reassignment.`,
        confirmLabel: 'Delete',
        action: () => router.delete(`/settings/roles/${role.id}`, { preserveScroll: true }),
    });
};

const togglePermission = (form, permission) => {
    const index = form.permissions.indexOf(permission);

    if (index >= 0) {
        form.permissions.splice(index, 1);
    } else {
        form.permissions.push(permission);
    }
};

const groupAllSelected = (form, group) =>
    group.permissions.every((permission) => form.permissions.includes(permission.name));

const toggleGroup = (form, group) => {
    const names = group.permissions.map((permission) => permission.name);

    if (groupAllSelected(form, group)) {
        form.permissions = form.permissions.filter((permission) => !names.includes(permission));
    } else {
        names.forEach((name) => {
            if (!form.permissions.includes(name)) {
                form.permissions.push(name);
            }
        });
    }
};

const isProtected = (role) => props.protectedRoles.includes(role.name);
</script>

<template>
    <SettingsLayout title="Roles & permissions" description="Create roles and control what each role can access.">
        <template #actions>
            <button type="button" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white transition hover:bg-blue-700" @click="showCreate = true">
                Create role
            </button>
        </template>

        <div class="mb-6 grid gap-4 sm:grid-cols-3">
            <div class="rounded-xl border border-slate-200 bg-white px-5 py-4 shadow-sm">
                <p class="text-sm text-slate-500">Roles</p>
                <p class="mt-1 text-2xl font-semibold text-slate-900">{{ roles.length }}</p>
            </div>
            <div class="rounded-xl border border-slate-200 bg-white px-5 py-4 shadow-sm">
                <p class="text-sm text-slate-500">Available permissions</p>
                <p class="mt-1 text-2xl font-semibold text-slate-900">{{ totalPermissions }}</p>
            </div>
            <div class="rounded-xl border border-slate-200 bg-white px-5 py-4 shadow-sm">
                <p class="text-sm text-slate-500">System roles</p>
                <p class="mt-1 text-2xl font-semibold text-slate-900">{{ protectedRoles.length }}</p>
            </div>
        </div>

        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
            <article
                v-for="role in sortedRoles"
                :key="role.id"
                class="flex flex-col overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm transition hover:border-slate-300 hover:shadow-md"
            >
                <div class="flex items-start gap-4 p-5">
                    <span
                        class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl text-sm font-semibold text-white shadow-sm"
                        :style="{ backgroundColor: avatarColor(role.name) }"
                    >
                        {{ roleInitials(role.name) }}
                    </span>

                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-center gap-2">
                            <h2 class="text-base font-semibold text-slate-900">{{ formatRoleName(role.name) }}</h2>
                            <span
                                v-if="isProtected(role)"
                                class="rounded-full bg-slate-100 px-2 py-0.5 text-[11px] font-medium uppercase tracking-wide text-slate-600"
                            >
                                System
                            </span>
                        </div>
                        <p class="mt-0.5 font-mono text-xs text-slate-400">{{ role.name }}</p>
                        <div class="mt-3 flex flex-wrap gap-2 text-xs text-slate-600">
                            <span class="rounded-full bg-blue-50 px-2.5 py-1 font-medium text-blue-700">
                                {{ role.permissions.length }} permissions
                            </span>
                            <span class="rounded-full bg-slate-100 px-2.5 py-1 font-medium text-slate-700">
                                {{ role.users_count ?? 0 }} members
                            </span>
                        </div>
                    </div>
                </div>

                <div v-if="permissionGroupsForRole(role).length" class="border-t border-slate-100 px-5 py-4">
                    <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-400">Access areas</p>
                    <div class="mt-2 flex flex-wrap gap-1.5">
                        <span
                            v-for="group in permissionGroupsForRole(role)"
                            :key="`${role.id}-${group.group}`"
                            class="rounded-md bg-slate-50 px-2 py-1 text-xs text-slate-600 ring-1 ring-inset ring-slate-200"
                        >
                            {{ group.group }}
                            <span class="font-medium text-slate-900">{{ group.count }}</span>
                        </span>
                    </div>
                </div>

                <div class="mt-auto flex gap-2 border-t border-slate-100 bg-slate-50/60 px-4 py-3">
                    <button
                        type="button"
                        class="flex-1 rounded-lg bg-white px-3 py-2 text-sm font-medium text-slate-700 ring-1 ring-inset ring-slate-200 transition hover:bg-slate-50"
                        @click="openEdit(role)"
                    >
                        Edit permissions
                    </button>
                    <button
                        v-if="!isProtected(role)"
                        type="button"
                        class="rounded-lg px-3 py-2 text-sm font-medium text-red-600 transition hover:bg-red-50"
                        @click="deleteRole(role)"
                    >
                        Delete
                    </button>
                </div>
            </article>
        </div>

        <AppModal
            :open="showCreate"
            title="Create role"
            description="Define a new role and assign permissions."
            variant="drawer"
            @close="closeCreate"
        >
            <form id="create-role-form" class="space-y-6" @submit.prevent="createRole">
                <div class="max-w-md">
                    <label class="mb-1 block text-sm font-medium text-slate-700">Role name</label>
                    <input
                        v-model="createForm.name"
                        type="text"
                        required
                        placeholder="support_lead"
                        class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm transition focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20"
                    />
                    <p class="mt-1 text-xs text-slate-500">Stored as a slug, e.g. support_lead</p>
                    <p v-if="createForm.errors.name" class="mt-1 text-sm text-red-600">{{ createForm.errors.name }}</p>
                </div>

                <div class="space-y-5">
                    <div v-for="group in catalog" :key="group.group" class="rounded-xl border border-slate-200 bg-slate-50/60 p-4">
                        <div class="flex items-center justify-between gap-3">
                            <p class="text-sm font-semibold text-slate-900">{{ group.group }}</p>
                            <button
                                type="button"
                                class="text-xs font-medium text-blue-600 hover:text-blue-700"
                                @click="toggleGroup(createForm, group)"
                            >
                                {{ groupAllSelected(createForm, group) ? 'Clear all' : 'Select all' }}
                            </button>
                        </div>
                        <div class="mt-3 grid gap-2 sm:grid-cols-2">
                            <label
                                v-for="permission in group.permissions"
                                :key="permission.name"
                                class="flex cursor-pointer items-center gap-2 rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700 transition hover:border-blue-200 hover:bg-blue-50/50"
                            >
                                <input
                                    type="checkbox"
                                    :checked="createForm.permissions.includes(permission.name)"
                                    @change="togglePermission(createForm, permission.name)"
                                />
                                <span>{{ permission.label }}</span>
                            </label>
                        </div>
                    </div>
                </div>
            </form>

            <template #footer>
                <div class="flex justify-end gap-2">
                    <button type="button" class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 transition hover:bg-white" @click="closeCreate">
                        Cancel
                    </button>
                    <button type="submit" form="create-role-form" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white transition hover:bg-blue-700 disabled:opacity-60" :disabled="createForm.processing">
                        Create role
                    </button>
                </div>
            </template>
        </AppModal>

        <AppModal
            :open="!!editingRole"
            :title="editingRole ? `Edit ${formatRoleName(editingRole.name)}` : 'Edit role'"
            description="Update permissions for this role."
            variant="drawer"
            @close="closeEdit"
        >
            <form v-if="editingRole && editForms[editingRole.id]" :id="`edit-role-${editingRole.id}`" class="space-y-6" @submit.prevent="saveRole(editingRole)">
                <div v-if="!isProtected(editingRole)" class="max-w-md">
                    <label class="mb-1 block text-sm font-medium text-slate-700">Role name</label>
                    <input v-model="editForms[editingRole.id].name" type="text" required class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" />
                </div>
                <div v-else class="rounded-lg border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-600">
                    System roles keep their slug. You can still update which permissions they include.
                </div>

                <div class="space-y-5">
                    <div v-for="group in catalog" :key="`${editingRole.id}-${group.group}`" class="rounded-xl border border-slate-200 bg-slate-50/60 p-4">
                        <div class="flex items-center justify-between gap-3">
                            <p class="text-sm font-semibold text-slate-900">{{ group.group }}</p>
                            <button
                                type="button"
                                class="text-xs font-medium text-blue-600 hover:text-blue-700"
                                @click="toggleGroup(editForms[editingRole.id], group)"
                            >
                                {{ groupAllSelected(editForms[editingRole.id], group) ? 'Clear all' : 'Select all' }}
                            </button>
                        </div>
                        <div class="mt-3 grid gap-2 sm:grid-cols-2">
                            <label
                                v-for="permission in group.permissions"
                                :key="permission.name"
                                class="flex cursor-pointer items-center gap-2 rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700 transition hover:border-blue-200 hover:bg-blue-50/50"
                            >
                                <input
                                    type="checkbox"
                                    :checked="editForms[editingRole.id].permissions.includes(permission.name)"
                                    @change="togglePermission(editForms[editingRole.id], permission.name)"
                                />
                                <span>{{ permission.label }}</span>
                            </label>
                        </div>
                    </div>
                </div>
            </form>

            <template #footer>
                <div v-if="editingRole" class="flex justify-end gap-2">
                    <button type="button" class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 transition hover:bg-white" @click="closeEdit">
                        Cancel
                    </button>
                    <button type="submit" :form="`edit-role-${editingRole.id}`" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white transition hover:bg-blue-700 disabled:opacity-60" :disabled="editForms[editingRole.id]?.processing">
                        Save role
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
