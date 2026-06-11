<script setup>
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import AuthLayout from '../../Layouts/AuthLayout.vue';
import HelpCenterSearch from '../../Components/HelpCenterSearch.vue';
import { useI18n } from 'vue-i18n';

defineProps({
    sso: Object,
});

const { t } = useI18n();
const page = usePage();

const flashSuccess = computed(() => page.props.flash?.success);
const flashError = computed(() => page.props.flash?.error);

const form = useForm({
    email: '',
    password: '',
    remember: false,
});

const submit = () => form.post('/login');

const inputClass = 'w-full rounded-xl border border-slate-200 bg-white px-3.5 py-2.5 text-sm text-slate-900 shadow-sm transition placeholder:text-slate-400 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20';
</script>

<template>
    <Head :title="$t('auth.login')" />
    <AuthLayout
        :aside-title="$t('auth.workspace_sign_in_title')"
        :aside-description="$t('auth.workspace_sign_in_description')"
    >
        <template #aside-footer>
            <div class="space-y-4">
                <HelpCenterSearch variant="hero" tone="dark" />
                <div class="rounded-xl border border-white/10 bg-white/5 p-4 backdrop-blur-sm">
                    <p class="text-xs font-medium uppercase tracking-wide text-slate-400">{{ $t('auth.looking_for_help') }}</p>
                    <p class="mt-1.5 text-sm text-slate-300">{{ $t('auth.customer_portal_description') }}</p>
                </div>
            </div>
        </template>

        <HelpCenterSearch variant="hero" class="mb-8 sm:hidden" />

        <h1 class="text-2xl font-semibold tracking-tight text-slate-900">{{ $t('auth.sign_in') }}</h1>
        <p class="mt-2 text-sm text-slate-600">{{ $t('auth.access_your_helpdesk') }}</p>

        <div
            v-if="flashSuccess"
            class="mt-6 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800"
        >
            {{ flashSuccess }}
        </div>
        <div
            v-if="flashError"
            class="mt-6 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800"
        >
            {{ flashError }}
        </div>

        <a
            v-if="sso?.enabled"
            :href="sso.redirect_url"
            class="mt-8 flex w-full items-center justify-center gap-2 rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm font-medium text-slate-700 shadow-sm transition hover:border-slate-300 hover:bg-slate-50"
        >
            <svg class="h-4 w-4 text-slate-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z" />
            </svg>
            {{ sso.label }}
        </a>

        <div v-if="sso?.enabled" class="my-6 flex items-center gap-3 text-xs font-medium uppercase tracking-wide text-slate-400">
            <span class="h-px flex-1 bg-slate-200" />
            {{ $t('auth.or_continue_with_email') }}
            <span class="h-px flex-1 bg-slate-200" />
        </div>

        <form class="space-y-5" :class="sso?.enabled ? '' : 'mt-8'" @submit.prevent="submit">
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

            <div>
                <div class="mb-1.5 flex items-center justify-between gap-3">
                    <label class="block text-sm font-medium text-slate-700" for="password">{{ $t('auth.password') }}</label>
                    <Link href="/forgot-password" class="text-sm font-medium text-blue-600 transition hover:text-blue-700">
                        {{ $t('auth.forgot_password') }}
                    </Link>
                </div>
                <input
                    id="password"
                    v-model="form.password"
                    type="password"
                    :class="inputClass"
                    autocomplete="current-password"
                    required
                />
                <p v-if="form.errors.password" class="mt-1.5 text-xs text-red-600">{{ form.errors.password }}</p>
            </div>

            <label class="flex cursor-pointer items-center gap-2.5 text-sm text-slate-600">
                <input v-model="form.remember" type="checkbox" class="h-4 w-4 rounded border-slate-300 text-blue-600 focus:ring-blue-500/20" />
                {{ $t('auth.remember_me') }}
            </label>

            <button
                type="submit"
                class="w-full rounded-xl bg-gradient-to-r from-blue-600 to-indigo-600 px-4 py-3 text-sm font-semibold text-white shadow-md shadow-blue-600/25 transition hover:from-blue-700 hover:to-indigo-700 disabled:opacity-60"
                :disabled="form.processing"
            >
                {{ $t('auth.sign_in') }}
            </button>
        </form>

        <p class="mt-8 text-center text-sm text-slate-600">
            {{ $t('auth.no_account') }}
            <Link href="/register" class="font-semibold text-blue-600 transition hover:text-blue-700">{{ $t('auth.create_one') }}</Link>
        </p>

        <p v-if="page.props.helpCenter" class="mt-4 text-center text-sm text-slate-500 lg:hidden">
            {{ $t('auth.looking_for_help') }}
            <Link :href="page.props.helpCenter.homeUrl" class="font-medium text-blue-600 hover:text-blue-700">{{ page.props.helpCenter.title }}</Link>
        </p>
    </AuthLayout>
</template>
