<script setup>
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import AuthLayout from '../../Layouts/AuthLayout.vue';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();
const page = usePage();

const flashSuccess = computed(() => page.props.flash?.success);

const form = useForm({
    email: '',
});

const submit = () => form.post('/forgot-password');

const inputClass = 'w-full rounded-xl border border-slate-200 bg-white px-3.5 py-2.5 text-sm text-slate-900 shadow-sm transition placeholder:text-slate-400 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20';
</script>

<template>
    <Head :title="$t('auth.forgot_password')" />
    <AuthLayout
        :aside-title="$t('auth.forgot_password_title')"
        :aside-description="$t('auth.forgot_password_description')"
    >
        <h1 class="text-2xl font-semibold tracking-tight text-slate-900">{{ $t('auth.forgot_password') }}</h1>
        <p class="mt-2 text-sm text-slate-600">{{ $t('auth.forgot_password_help') }}</p>

        <div
            v-if="flashSuccess"
            class="mt-6 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800"
        >
            {{ flashSuccess }}
        </div>

        <form class="mt-8 space-y-5" @submit.prevent="submit">
            <div>
                <label class="mb-1.5 block text-sm font-medium text-slate-700" for="email">{{ $t('auth.email') }}</label>
                <input
                    id="email"
                    v-model="form.email"
                    type="email"
                    :class="inputClass"
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

        <p class="mt-8 text-center text-sm text-slate-600">
            <Link href="/login" class="font-semibold text-blue-600 transition hover:text-blue-700">{{ $t('auth.back_to_sign_in') }}</Link>
        </p>
    </AuthLayout>
</template>
