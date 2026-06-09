<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import AdminLayout from '../../../Layouts/AdminLayout.vue';
import PageHeader from '../../../Components/PageHeader.vue';
import { adminInputClass } from '../../../composables/usePlatformAdmin.js';

const props = defineProps({
    user: Object,
});

const profileForm = useForm({
    name: props.user.name,
    email: props.user.email,
});

const passwordForm = useForm({
    current_password: '',
    password: '',
    password_confirmation: '',
});

const saveProfile = () => {
    profileForm.put('/admin/profile', { preserveScroll: true });
};

const savePassword = () => {
    passwordForm.put('/admin/profile/password', {
        preserveScroll: true,
        onSuccess: () => passwordForm.reset(),
    });
};
</script>

<template>
    <Head title="Profile" />
    <AdminLayout>
        <div class="mx-auto max-w-2xl px-4 py-8 sm:px-6">
            <PageHeader title="Profile" description="Update your platform account details and password." />

            <div class="space-y-6">
                <form class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm" @submit.prevent="saveProfile">
                    <h2 class="text-lg font-semibold text-slate-900">Account</h2>
                    <div class="mt-5 space-y-4">
                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-slate-700">Name</label>
                            <input v-model="profileForm.name" type="text" :class="adminInputClass" />
                            <p v-if="profileForm.errors.name" class="mt-1 text-xs text-red-600">{{ profileForm.errors.name }}</p>
                        </div>
                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-slate-700">Email</label>
                            <input v-model="profileForm.email" type="email" :class="adminInputClass" />
                            <p v-if="profileForm.errors.email" class="mt-1 text-xs text-red-600">{{ profileForm.errors.email }}</p>
                        </div>
                    </div>
                    <button type="submit" class="mt-6 rounded-xl bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-blue-700" :disabled="profileForm.processing">
                        Save profile
                    </button>
                </form>

                <form class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm" @submit.prevent="savePassword">
                    <h2 class="text-lg font-semibold text-slate-900">Password</h2>
                    <p class="mt-1 text-sm text-slate-500">Choose a strong password for your platform admin account.</p>
                    <div class="mt-5 space-y-4">
                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-slate-700">Current password</label>
                            <input v-model="passwordForm.current_password" type="password" :class="adminInputClass" />
                            <p v-if="passwordForm.errors.current_password" class="mt-1 text-xs text-red-600">{{ passwordForm.errors.current_password }}</p>
                        </div>
                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-slate-700">New password</label>
                            <input v-model="passwordForm.password" type="password" :class="adminInputClass" />
                            <p v-if="passwordForm.errors.password" class="mt-1 text-xs text-red-600">{{ passwordForm.errors.password }}</p>
                        </div>
                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-slate-700">Confirm new password</label>
                            <input v-model="passwordForm.password_confirmation" type="password" :class="adminInputClass" />
                        </div>
                    </div>
                    <button type="submit" class="mt-6 rounded-xl bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-blue-700" :disabled="passwordForm.processing">
                        Update password
                    </button>
                </form>
            </div>
        </div>
    </AdminLayout>
</template>
