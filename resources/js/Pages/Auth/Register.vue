<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import AuthLayout from '../../Layouts/AuthLayout.vue';
import HelpCenterSearch from '../../Components/HelpCenterSearch.vue';
import { formInputClass } from '../../composables/useFormControls.js';

const form = useForm({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
});

const submit = () => form.post('/register');
</script>

<template>
    <Head :title="$t('auth.register')" />
    <AuthLayout
        :aside-title="$t('auth.workspace_register_title')"
        :aside-description="$t('auth.workspace_register_description')"
    >
        <template #aside-footer>
            <div class="rounded-xl border border-white/10 bg-white/5 p-4 backdrop-blur-sm">
                <p class="text-xs font-medium uppercase tracking-wide text-slate-400 dark:text-slate-500">{{ $t('auth.already_have_account') }}</p>
                <Link href="/login" class="mt-3 inline-flex text-sm font-semibold text-blue-400 transition hover:text-blue-300">
                    {{ $t('auth.sign_in') }} →
                </Link>
            </div>
        </template>

        <HelpCenterSearch variant="hero" class="mb-8" />

        <h1 class="text-2xl font-semibold tracking-tight text-slate-900 dark:text-slate-100">{{ $t('auth.create_account') }}</h1>
        <p class="mt-2 text-sm text-slate-600 dark:text-slate-400">{{ $t('auth.workspace_register_description') }}</p>

        <form class="mt-8 space-y-5" @submit.prevent="submit">
            <div>
                <label class="mb-1.5 block text-sm font-medium text-slate-700 dark:text-slate-300" for="name">{{ $t('auth.name') }}</label>
                <input id="name" v-model="form.name" type="text" :class="formInputClass" autocomplete="name" required />
                <p v-if="form.errors.name" class="mt-1.5 text-xs text-red-600">{{ form.errors.name }}</p>
            </div>

            <div>
                <label class="mb-1.5 block text-sm font-medium text-slate-700 dark:text-slate-300" for="email">{{ $t('auth.email') }}</label>
                <input id="email" v-model="form.email" type="email" :class="formInputClass" :placeholder="$t('auth.email_placeholder')" autocomplete="email" required />
                <p v-if="form.errors.email" class="mt-1.5 text-xs text-red-600">{{ form.errors.email }}</p>
            </div>

            <div>
                <label class="mb-1.5 block text-sm font-medium text-slate-700 dark:text-slate-300" for="password">{{ $t('auth.password') }}</label>
                <input id="password" v-model="form.password" type="password" :class="formInputClass" autocomplete="new-password" required />
                <p v-if="form.errors.password" class="mt-1.5 text-xs text-red-600">{{ form.errors.password }}</p>
            </div>

            <div>
                <label class="mb-1.5 block text-sm font-medium text-slate-700 dark:text-slate-300" for="password_confirmation">{{ $t('auth.confirm_password') }}</label>
                <input id="password_confirmation" v-model="form.password_confirmation" type="password" :class="formInputClass" autocomplete="new-password" required />
            </div>

            <button
                type="submit"
                class="w-full rounded-xl bg-gradient-to-r from-blue-600 to-indigo-600 px-4 py-3 text-sm font-semibold text-white shadow-md shadow-blue-600/25 transition hover:from-blue-700 hover:to-indigo-700 disabled:opacity-60"
                :disabled="form.processing"
            >
                {{ $t('auth.register') }}
            </button>
        </form>

        <p class="mt-8 text-center text-sm text-slate-600 dark:text-slate-400">
            {{ $t('auth.already_have_account') }}
            <Link href="/login" class="font-semibold text-blue-600 transition hover:text-blue-700 dark:hover:text-blue-300 dark:text-blue-300">{{ $t('auth.sign_in') }}</Link>
        </p>
    </AuthLayout>
</template>
