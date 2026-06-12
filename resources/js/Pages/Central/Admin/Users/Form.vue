<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import AdminLayout from '../../../../Layouts/AdminLayout.vue';
import PageHeader from '../../../../Components/PageHeader.vue';
import { adminInputClass } from '../../../../composables/usePlatformAdmin.js';
import { useI18n } from 'vue-i18n';

const props = defineProps({
    user: { type: Object, default: null },
    roles: Array,
});

const { t } = useI18n();

const editing = !!props.user;

const form = useForm({
    name: props.user?.name ?? '',
    email: props.user?.email ?? '',
    password: '',
    password_confirmation: '',
    is_active: props.user?.is_active ?? true,
    roles: props.user?.roles ?? ['support'],
});

const submit = () => {
    if (editing) {
        form.put(`/admin/users/${props.user.id}`);
        return;
    }

    form.post('/admin/users');
};

const formatRole = (name) => name.split('_').map((part) => part.charAt(0).toUpperCase() + part.slice(1)).join(' ');
</script>

<template>
    <Head :title="editing ? 'Edit user' : 'Create user'" />
    <AdminLayout>
        <div class="mx-auto max-w-2xl px-4 py-8 sm:px-6">
            <PageHeader :title="editing ? 'Edit user' : 'Create user'" :description="editing ? 'Update account details and role assignments.' : 'Add a new platform administrator.'" />

            <form class="rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 p-6 shadow-sm" @submit.prevent="submit">
                <div class="space-y-5">
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-slate-700 dark:text-slate-300">{{ $t('profile.name') }}</label>
                        <input v-model="form.name" type="text" :class="adminInputClass" />
                        <p v-if="form.errors.name" class="mt-1 text-xs text-red-600">{{ form.errors.name }}</p>
                    </div>
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-slate-700 dark:text-slate-300">{{ $t('profile.email') }}</label>
                        <input v-model="form.email" type="email" :class="adminInputClass" />
                        <p v-if="form.errors.email" class="mt-1 text-xs text-red-600">{{ form.errors.email }}</p>
                    </div>
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-slate-700 dark:text-slate-300">{{ editing ? 'New password (optional)' : 'Password' }}</label>
                        <input v-model="form.password" type="password" :class="adminInputClass" />
                        <p v-if="form.errors.password" class="mt-1 text-xs text-red-600">{{ form.errors.password }}</p>
                    </div>
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-slate-700 dark:text-slate-300">{{ $t('profile.confirm_password_disable') }}</label>
                        <input v-model="form.password_confirmation" type="password" :class="adminInputClass" />
                    </div>
                    <label class="flex items-center gap-2 text-sm text-slate-700 dark:text-slate-300">
                        <input v-model="form.is_active" type="checkbox" class="rounded border-slate-300 dark:border-slate-700 text-blue-600" />
                        Active account
                    </label>
                    <div>
                        <p class="mb-2 text-sm font-medium text-slate-700 dark:text-slate-300">{{ $t('central.roles') }}</p>
                        <div class="grid gap-2 sm:grid-cols-2">
                            <label
                                v-for="role in roles"
                                :key="role"
                                class="flex cursor-pointer items-center gap-3 rounded-xl border px-3 py-2.5 text-sm transition"
                                :class="form.roles.includes(role) ? 'border-blue-500 bg-blue-50 dark:bg-blue-950/40 text-blue-900' : 'border-slate-200 dark:border-slate-800 text-slate-700 dark:text-slate-300 hover:border-slate-300 dark:hover:border-slate-600 dark:border-slate-700'"
                            >
                                <input v-model="form.roles" type="checkbox" :value="role" class="rounded border-slate-300 dark:border-slate-700 text-blue-600" />
                                {{ formatRole(role) }}
                            </label>
                        </div>
                        <p v-if="form.errors.roles" class="mt-1 text-xs text-red-600">{{ form.errors.roles }}</p>
                    </div>
                </div>

                <div class="mt-8 flex items-center gap-3 border-t border-slate-100 dark:border-slate-800 pt-6">
                    <button type="submit" class="rounded-xl bg-blue-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-blue-700" :disabled="form.processing">
                        {{ editing ? 'Save changes' : 'Create user' }}
                    </button>
                    <Link href="/admin/users" class="text-sm font-medium text-slate-600 dark:text-slate-400 hover:text-slate-800 dark:text-slate-200">{{ $t('common.cancel') }}</Link>
                </div>
            </form>
        </div>
    </AdminLayout>
</template>
