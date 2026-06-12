<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import AdminLayout from '../../../Layouts/AdminLayout.vue';
import PageHeader from '../../../Components/PageHeader.vue';
import { adminInputClass } from '../../../composables/usePlatformAdmin.js';
import { useI18n } from 'vue-i18n';

const props = defineProps({
    user: Object,
});

const { t } = useI18n();

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
    <Head :title="$t('settings.profile')" />
    <AdminLayout>
        <div class="mx-auto max-w-2xl px-4 py-8 sm:px-6">
            <PageHeader :title="$t('settings.profile')" :description="$t('central.update_your_platform_account_details_and_password')" />

            <div class="space-y-6">
                <form class="rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 p-6 shadow-sm" @submit.prevent="saveProfile">
                    <h2 class="text-lg font-semibold text-slate-900 dark:text-slate-100">{{ $t('central.account') }}</h2>
                    <div class="mt-5 space-y-4">
                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-slate-700 dark:text-slate-300">{{ $t('profile.name') }}</label>
                            <input v-model="profileForm.name" type="text" :class="adminInputClass" />
                            <p v-if="profileForm.errors.name" class="mt-1 text-xs text-red-600">{{ profileForm.errors.name }}</p>
                        </div>
                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-slate-700 dark:text-slate-300">{{ $t('profile.email') }}</label>
                            <input v-model="profileForm.email" type="email" :class="adminInputClass" />
                            <p v-if="profileForm.errors.email" class="mt-1 text-xs text-red-600">{{ profileForm.errors.email }}</p>
                        </div>
                    </div>
                    <button type="submit" class="mt-6 rounded-xl bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-blue-700" :disabled="profileForm.processing">{{ $t('profile.save_profile') }}</button>
                </form>

                <form class="rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 p-6 shadow-sm" @submit.prevent="savePassword">
                    <h2 class="text-lg font-semibold text-slate-900 dark:text-slate-100">{{ $t('settings.password') }}</h2>
                    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ $t('central.choose_a_strong_password_for_your_platform_admin_account') }}</p>
                    <div class="mt-5 space-y-4">
                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-slate-700 dark:text-slate-300">{{ $t('profile.current_password') }}</label>
                            <input v-model="passwordForm.current_password" type="password" :class="adminInputClass" />
                            <p v-if="passwordForm.errors.current_password" class="mt-1 text-xs text-red-600">{{ passwordForm.errors.current_password }}</p>
                        </div>
                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-slate-700 dark:text-slate-300">{{ $t('profile.new_password') }}</label>
                            <input v-model="passwordForm.password" type="password" :class="adminInputClass" />
                            <p v-if="passwordForm.errors.password" class="mt-1 text-xs text-red-600">{{ passwordForm.errors.password }}</p>
                        </div>
                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-slate-700 dark:text-slate-300">{{ $t('central.confirm_new_password') }}</label>
                            <input v-model="passwordForm.password_confirmation" type="password" :class="adminInputClass" />
                        </div>
                    </div>
                    <button type="submit" class="mt-6 rounded-xl bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-blue-700" :disabled="passwordForm.processing">{{ $t('profile.update_password') }}</button>
                </form>
            </div>
        </div>
    </AdminLayout>
</template>
