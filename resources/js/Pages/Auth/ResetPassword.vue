<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import AuthLayout from '../../Layouts/AuthLayout.vue';
import { useI18n } from 'vue-i18n';

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

const inputClass = 'w-full rounded-xl border border-slate-200 bg-white px-3.5 py-2.5 text-sm text-slate-900 shadow-sm transition placeholder:text-slate-400 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20';
</script>

<template>
    <Head :title="$t('auth.reset_password')" />
    <AuthLayout
        :aside-title="$t('auth.reset_password_title')"
        :aside-description="$t('auth.reset_password_description')"
    >
        <h1 class="text-2xl font-semibold tracking-tight text-slate-900">{{ $t('auth.reset_password') }}</h1>
        <p class="mt-2 text-sm text-slate-600">{{ $t('auth.reset_password_help') }}</p>

        <form class="mt-8 space-y-5" @submit.prevent="submit">
            <div>
                <label class="mb-1.5 block text-sm font-medium text-slate-700" for="email">{{ $t('auth.email') }}</label>
                <input
                    id="email"
                    v-model="form.email"
                    type="email"
                    :class="inputClass"
                    autocomplete="username"
                    required
                />
                <p v-if="form.errors.email" class="mt-1.5 text-xs text-red-600">{{ form.errors.email }}</p>
            </div>

            <div>
                <label class="mb-1.5 block text-sm font-medium text-slate-700" for="password">{{ $t('auth.password') }}</label>
                <input
                    id="password"
                    v-model="form.password"
                    type="password"
                    :class="inputClass"
                    autocomplete="new-password"
                    autofocus
                    required
                />
                <p v-if="form.errors.password" class="mt-1.5 text-xs text-red-600">{{ form.errors.password }}</p>
            </div>

            <div>
                <label class="mb-1.5 block text-sm font-medium text-slate-700" for="password_confirmation">{{ $t('auth.confirm_password') }}</label>
                <input
                    id="password_confirmation"
                    v-model="form.password_confirmation"
                    type="password"
                    :class="inputClass"
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

        <p class="mt-8 text-center text-sm text-slate-600">
            <Link href="/login" class="font-semibold text-blue-600 transition hover:text-blue-700">{{ $t('auth.back_to_sign_in') }}</Link>
        </p>
    </AuthLayout>
</template>
