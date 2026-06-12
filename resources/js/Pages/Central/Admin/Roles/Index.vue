<script setup>
import { Head, router, useForm } from '@inertiajs/vue3';
import { reactive, ref } from 'vue';
import AdminLayout from '../../../../Layouts/AdminLayout.vue';
import PageHeader from '../../../../Components/PageHeader.vue';
import AppDeleteAction from '../../../../Components/AppDeleteAction.vue';
import { adminInputClass, usePlatformAdmin } from '../../../../composables/usePlatformAdmin.js';
import { useI18n } from 'vue-i18n';

const props = defineProps({
    roles: Array,
    catalog: Array,
    protectedRoles: Array,
});

const { t } = useI18n();

const { can } = usePlatformAdmin();
const canManage = can('roles.manage');
const showCreate = ref(false);

const createForm = useForm({
    name: '',
    permissions: ['tenants.view'],
});

const editForms = reactive({});

props.roles.forEach((role) => {
    editForms[role.id] = useForm({
        name: role.name,
        permissions: role.permissions.map((permission) => permission.name),
    });
});

const isProtected = (role) => props.protectedRoles.includes(role.name);

const formatRoleName = (name) => name.split('_').map((part) => part.charAt(0).toUpperCase() + part.slice(1)).join(' ');

const createRole = () => {
    createForm.post('/admin/roles', {
        preserveScroll: true,
        onSuccess: () => {
            createForm.reset();
            showCreate.value = false;
        },
    });
};

const updateRole = (role) => {
    editForms[role.id].put(`/admin/roles/${role.id}`, { preserveScroll: true });
};

const deleteRole = (role) => {
    if (!confirm(`Delete role ${role.name}?`)) {
        return;
    }

    router.delete(`/admin/roles/${role.id}`, { preserveScroll: true });
};
</script>

<template>
    <Head :title="$t('central.platform_roles')" />
    <AdminLayout>
        <div class="mx-auto max-w-5xl px-4 py-8 sm:px-6">
            <PageHeader :title="$t('settings.roles_permissions')" :description="$t('central.control_what_platform_users_can_access_on_the_central_domain')">
                <template v-if="canManage" #actions>
                    <button type="button" class="rounded-xl bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-blue-700" @click="showCreate = !showCreate">
                        {{ showCreate ? 'Cancel' : 'New role' }}
                    </button>
                </template>
            </PageHeader>

            <form v-if="showCreate && canManage" class="mb-6 rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 p-6 shadow-sm" @submit.prevent="createRole">
                <h2 class="text-lg font-semibold text-slate-900 dark:text-slate-100">{{ $t('central.create_role') }}</h2>
                <div class="mt-5 grid gap-5">
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-slate-700 dark:text-slate-300">{{ $t('profile.name') }}</label>
                        <input v-model="createForm.name" type="text" :class="adminInputClass" />
                    </div>
                    <div>
                        <p class="mb-2 text-sm font-medium text-slate-700 dark:text-slate-300">{{ $t('central.permissions') }}</p>
                        <div class="grid gap-4 sm:grid-cols-2">
                            <div v-for="group in catalog" :key="group.group" class="rounded-xl border border-slate-200 dark:border-slate-800 p-4">
                                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">{{ group.group }}</p>
                                <div class="mt-3 space-y-2">
                                    <label v-for="permission in group.permissions" :key="permission.name" class="flex items-center gap-2 text-sm text-slate-700 dark:text-slate-300">
                                        <input v-model="createForm.permissions" type="checkbox" :value="permission.name" class="rounded border-slate-300 dark:border-slate-700 text-blue-600" />
                                        {{ permission.label }}
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <button type="submit" class="mt-5 rounded-xl bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-blue-700">{{ $t('central.create_role') }}</button>
            </form>

            <div class="space-y-4">
                <div v-for="role in roles" :key="role.id" class="rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 p-6 shadow-sm">
                    <form @submit.prevent="updateRole(role)">
                        <div class="flex items-start justify-between gap-4">
                            <div>
                                <div class="flex items-center gap-2">
                                    <h2 class="text-lg font-semibold text-slate-900 dark:text-slate-100">{{ formatRoleName(role.name) }}</h2>
                                    <span v-if="isProtected(role)" class="rounded-full bg-slate-100 dark:bg-slate-900 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-slate-600 dark:text-slate-400">{{ $t('central.protected') }}</span>
                                </div>
                                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ role.users_count ?? 0 }} users assigned</p>
                            </div>
                            <div v-if="canManage" class="flex items-center gap-2">
                                <AppDeleteAction
                                    v-if="!isProtected(role)"
                                    :label="$t('central.delete')"
                                    @click="deleteRole(role)"
                                />
                                <button type="submit" class="rounded-lg bg-slate-900 px-3 py-1.5 text-sm font-medium text-white hover:bg-slate-800">{{ $t('common.save') }}</button>
                            </div>
                        </div>

                        <div v-if="canManage && !isProtected(role)" class="mt-5">
                            <label class="mb-1.5 block text-sm font-medium text-slate-700 dark:text-slate-300">{{ $t('central.role_name') }}</label>
                            <input v-model="editForms[role.id].name" type="text" class="max-w-sm" :class="adminInputClass" />
                        </div>

                        <div class="mt-5 grid gap-4 sm:grid-cols-2">
                            <div v-for="group in catalog" :key="`${role.id}-${group.group}`" class="rounded-xl border border-slate-200 dark:border-slate-800 p-4">
                                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">{{ group.group }}</p>
                                <div class="mt-3 space-y-2">
                                    <label v-for="permission in group.permissions" :key="permission.name" class="flex items-center gap-2 text-sm text-slate-700 dark:text-slate-300">
                                        <input
                                            v-model="editForms[role.id].permissions"
                                            type="checkbox"
                                            :value="permission.name"
                                            :disabled="!canManage"
                                            class="rounded border-slate-300 dark:border-slate-700 text-blue-600 disabled:opacity-60"
                                        />
                                        {{ permission.label }}
                                    </label>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>
