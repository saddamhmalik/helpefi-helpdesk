<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import AppLogo from '../../../Components/AppLogo.vue';

const { t } = useI18n();

const form = useForm({
    email: '',
    password: '',
    remember: false,
});

const submit = () => {
    form.post('/admin/login');
};
</script>

<template>
    <Head :title="$t('central.platform_admin')" />
    <div class="flex min-h-screen items-center justify-center bg-slate-950 px-4">
        <div class="w-full max-w-sm rounded-2xl border border-white/10 bg-white dark:bg-slate-900 p-8 shadow-2xl">
            <div class="flex justify-center">
                <AppLogo size="lg" />
            </div>
            <h1 class="mt-6 text-xl font-semibold text-slate-900 dark:text-slate-100">{{ $t('central.platform_admin') }}</h1>
            <p class="mt-2 text-sm text-slate-600 dark:text-slate-400">{{ $t('central.sign_in_to_manage_workspaces_and_platform_settings') }}</p>
            <form class="mt-6 space-y-4" @submit.prevent="submit">
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-slate-700 dark:text-slate-300">{{ $t('profile.email') }}</label>
                    <input v-model="form.email" type="email" required class="w-full rounded-xl border border-slate-200 dark:border-slate-800 px-3.5 py-2.5 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20" autofocus />
                    <p v-if="form.errors.email" class="mt-1.5 text-xs text-red-600">{{ form.errors.email }}</p>
                </div>
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-slate-700 dark:text-slate-300">{{ $t('settings.password') }}</label>
                    <input v-model="form.password" type="password" required class="w-full rounded-xl border border-slate-200 dark:border-slate-800 px-3.5 py-2.5 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20" />
                    <p v-if="form.errors.password" class="mt-1.5 text-xs text-red-600">{{ form.errors.password }}</p>
                </div>
                <label class="flex items-center gap-2 text-sm text-slate-600 dark:text-slate-400">
                    <input v-model="form.remember" type="checkbox" class="rounded border-slate-300 dark:border-slate-700" />
                    Remember me
                </label>
                <button type="submit" class="w-full rounded-xl bg-blue-600 py-2.5 text-sm font-semibold text-white hover:bg-blue-700" :disabled="form.processing">{{ $t('central.sign_in') }}</button>
            </form>
            <Link href="/" class="mt-4 block text-center text-sm text-slate-500 dark:text-slate-400 hover:text-slate-700 dark:hover:text-slate-300 dark:text-slate-300">← Back to site</Link>
        </div>
    </div>
</template>
