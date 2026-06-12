<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import AuthLayout from '../../Layouts/AuthLayout.vue';
import { useI18n } from 'vue-i18n';
import { formInputClass } from '../../composables/useFormControls.js';

const props = defineProps({
    token: { type: String, required: true },
    email: { type: String, default: '' },
});

const { t } = useI18n();

const form = useForm({
    token: props.token,
    email: props.email,
    password: '',
    password_confirmation: '',
});

const submit = () => form.post('/reset-password');
</script>

<template>
    <Head :title="$t('auth.reset_password')" />
    <AuthLayout
        :aside-title="$t('auth.reset_password_title')"
        :aside-description="$t('auth.reset_password_description')"
    >
        <h1 class="text-2xl font-semibold tracking-tight text-slate-900 dark:text-slate-100">{{ $t('auth.reset_password') }}</h1>
        <p class="mt-2 text-sm text-slate-600 dark:text-slate-400">{{ $t('auth.reset_password_help') }}</p>

        <form class="mt-8 space-y-5" @submit.prevent="submit">
            <div>
                <label class="mb-1.5 block text-sm font-medium text-slate-700 dark:text-slate-300" for="email">{{ $t('auth.email') }}</label>
                <input
                    id="email"
                    v-model="form.email"
                    type="email"
                    :class="formInputClass"
                    autocomplete="username"
                    required
                />
                <p v-if="form.errors.email" class="mt-1.5 text-xs text-red-600">{{ form.errors.email }}</p>
            </div>

            <div>
                <label class="mb-1.5 block text-sm font-medium text-slate-700 dark:text-slate-300" for="password">{{ $t('auth.password') }}</label>
                <input
                    id="password"
                    v-model="form.password"
                    type="password"
                    :class="formInputClass"
                    autocomplete="new-password"
                    autofocus
                    required
                />
                <p v-if="form.errors.password" class="mt-1.5 text-xs text-red-600">{{ form.errors.password }}</p>
            </div>

            <div>
                <label class="mb-1.5 block text-sm font-medium text-slate-700 dark:text-slate-300" for="password_confirmation">{{ $t('auth.confirm_password') }}</label>
                <input
                    id="password_confirmation"
                    v-model="form.password_confirmation"
                    type="password"
                    :class="formInputClass"
                    autocomplete="new-password"
                    required
                />
            </div>

            <button
                type="submit"
                class="w-full rounded-xl bg-gradient-to-r from-blue-600 to-indigo-600 px-4 py-3 text-sm font-semibold text-white shadow-md shadow-blue-600/25 transition hover:from-blue-700 hover:to-indigo-700 disabled:opacity-60"
                :disabled="form.processing"
            >
                {{ $t('auth.reset_password') }}
            </button>
        </form>

        <p class="mt-8 text-center text-sm text-slate-600 dark:text-slate-400">
            <Link href="/login" class="font-semibold text-blue-600 transition hover:text-blue-700 dark:hover:text-blue-300 dark:text-blue-300">{{ $t('auth.back_to_sign_in') }}</Link>
        </p>
    </AuthLayout>
</template>
