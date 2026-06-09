<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import AdminLayout from '../../../../Layouts/AdminLayout.vue';
import AppAvatar from '../../../../Components/AppAvatar.vue';
import PageHeader from '../../../../Components/PageHeader.vue';
import PaginationLinks from '../../../../Components/PaginationLinks.vue';
import { usePlatformAdmin } from '../../../../composables/usePlatformAdmin.js';

defineProps({
    users: Object,
    roles: Array,
});

const { can } = usePlatformAdmin();
const canManage = can('users.manage');

const destroyUser = (user) => {
    if (!confirm(`Delete ${user.name}?`)) {
        return;
    }

    router.delete(`/admin/users/${user.id}`, { preserveScroll: true });
};

const formatRole = (name) => name.split('_').map((part) => part.charAt(0).toUpperCase() + part.slice(1)).join(' ');
</script>

<template>
    <Head title="Platform users" />
    <AdminLayout>
        <div class="mx-auto max-w-7xl px-4 py-8 sm:px-6">
            <PageHeader title="Platform users" description="Manage central admin accounts and role assignments.">
                <template v-if="canManage" #actions>
                    <Link href="/admin/users/create" class="rounded-xl bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-blue-700">
                        Add user
                    </Link>
                </template>
            </PageHeader>

            <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50">
                        <tr>
                            <th class="px-5 py-3.5 text-left font-medium text-slate-600">User</th>
                            <th class="px-5 py-3.5 text-left font-medium text-slate-600">Roles</th>
                            <th class="px-5 py-3.5 text-left font-medium text-slate-600">Status</th>
                            <th v-if="canManage" class="px-5 py-3.5 text-right font-medium text-slate-600">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr v-for="user in users.data" :key="user.id" class="hover:bg-slate-50/80">
                            <td class="px-5 py-4">
                                <div class="flex items-center gap-3">
                                    <AppAvatar :name="user.name" :email="user.email" size="sm" />
                                    <div>
                                        <p class="font-medium text-slate-900">{{ user.name }}</p>
                                        <p class="text-xs text-slate-500">{{ user.email }}</p>
                                    </div>
                                </div>
                            </td>
                            <td class="px-5 py-4">
                                <div class="flex flex-wrap gap-1.5">
                                    <span
                                        v-for="role in user.roles"
                                        :key="role.id"
                                        class="rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-medium text-slate-700"
                                    >
                                        {{ formatRole(role.name) }}
                                    </span>
                                    <span v-if="!user.roles?.length" class="text-slate-400">—</span>
                                </div>
                            </td>
                            <td class="px-5 py-4">
                                <span class="rounded-full px-2.5 py-1 text-xs font-medium" :class="user.is_active ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-600'">
                                    {{ user.is_active ? 'Active' : 'Inactive' }}
                                </span>
                            </td>
                            <td v-if="canManage" class="px-5 py-4 text-right">
                                <Link :href="`/admin/users/${user.id}/edit`" class="font-medium text-blue-600 hover:text-blue-700">Edit</Link>
                                <button type="button" class="ml-4 font-medium text-red-600 hover:text-red-700" @click="destroyUser(user)">Delete</button>
                            </td>
                        </tr>
                        <tr v-if="!users.data.length">
                            <td :colspan="canManage ? 4 : 3" class="px-5 py-12 text-center text-sm text-slate-500">
                                No platform users yet.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="mt-6">
                <PaginationLinks
                    :links="users.links"
                    :from="users.from"
                    :to="users.to"
                    :total="users.total"
                />
            </div>
        </div>
    </AdminLayout>
</template>
