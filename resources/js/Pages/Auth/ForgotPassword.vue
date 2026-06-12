<script setup>
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import AuthLayout from '../../Layouts/AuthLayout.vue';
import { useI18n } from 'vue-i18n';
import { formInputClass } from '../../composables/useFormControls.js';

const { t } = useI18n();
const page = usePage();

const flashSuccess = computed(() => page.props.flash?.success);

const form = useForm({
    email: '',
});

const submit = () => form.post('/forgot-password');
</script>

<template>
    <Head :title="$t('auth.forgot_password')" />
    <AuthLayout
        :aside-title="$t('auth.forgot_password_title')"
        :aside-description="$t('auth.forgot_password_description')"
    >
        <h1 class="text-2xl font-semibold tracking-tight text-slate-900 dark:text-slate-100">{{ $t('auth.forgot_password') }}</h1>
        <p class="mt-2 text-sm text-slate-600 dark:text-slate-400">{{ $t('auth.forgot_password_help') }}</p>

        <div
            v-if="flashSuccess"
            class="mt-6 rounded-xl border border-emerald-200 dark:border-emerald-900/60 bg-emerald-50 dark:bg-emerald-950/40 px-4 py-3 text-sm text-emerald-800 dark:text-emerald-200"
        >
            {{ flashSuccess }}
        </div>

        <form class="mt-8 space-y-5" @submit.prevent="submit">
            <div>
                <label class="mb-1.5 block text-sm font-medium text-slate-700 dark:text-slate-300" for="email">{{ $t('auth.email') }}</label>
                <input
                    id="email"
                    v-model="form.email"
                    type="email"
                    :class="formInputClass"
                    :placeholder="$t('auth.email_placeholder')"
                    autocomplete="email"
                    autofocus
                    required
                />
                <p v-if="form.errors.email" class="mt-1.5 text-xs text-red-600">{{ form.errors.email }}</p>
            </div>

            <button
                type="submit"
                class="w-full rounded-xl bg-gradient-to-r from-blue-600 to-indigo-600 px-4 py-3 text-sm font-semibold text-white shadow-md shadow-blue-600/25 transition hover:from-blue-700 hover:to-indigo-700 disabled:opacity-60"
                :disabled="form.processing"
            >
                {{ $t('auth.send_reset_link') }}
            </button>
        </form>

        <p class="mt-8 text-center text-sm text-slate-600 dark:text-slate-400">
            <Link href="/login" class="font-semibold text-blue-600 transition hover:text-blue-700 dark:hover:text-blue-300 dark:text-blue-300">{{ $t('auth.back_to_sign_in') }}</Link>
        </p>
    </AuthLayout>
</template>
