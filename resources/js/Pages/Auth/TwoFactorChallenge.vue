<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import AuthLayout from '../../Layouts/AuthLayout.vue';
import { formInputClass } from '../../composables/useFormControls.js';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();

const form = useForm({
    code: '',
});

const submit = () => form.post('/two-factor-challenge');
</script>

<template>
    <Head :title="$t('auth.two-factor_authentication')" />
    <AuthLayout
        :aside-title="$t('auth.two-factor_authentication')"
        :aside-description="$t('auth.enter_the_6-digit_code_from_your_authenticator_app_or_a_recovery_code')"
    >
        <h1 class="text-2xl font-semibold tracking-tight agent-text">{{ $t('auth.authentication_code') }}</h1>
        <p class="mt-2 text-sm agent-text-muted">{{ $t('auth.enter_the_6-digit_code_from_your_authenticator_app_or_a_recovery_code') }}</p>

        <form class="mt-8 space-y-5" @submit.prevent="submit">
            <div>
                <label class="mb-1.5 block text-sm font-medium agent-text-muted" for="code">{{ $t('auth.code') }}</label>
                <input
                    id="code"
                    v-model="form.code"
                    type="text"
                    inputmode="numeric"
                    autocomplete="one-time-code"
                    :class="`${formInputClass} tracking-widest`"
                    required
                    autofocus
                />
                <p v-if="form.errors.code" class="mt-1.5 text-xs text-red-600">{{ form.errors.code }}</p>
            </div>

            <button
                type="submit"
                class="w-full rounded-xl bg-gradient-to-r from-blue-600 to-indigo-600 px-4 py-3 text-sm font-semibold text-white shadow-md shadow-blue-600/25 transition hover:from-blue-700 hover:to-indigo-700 disabled:opacity-60"
                :disabled="form.processing"
            >
                {{ $t('auth.verify') }}
            </button>
        </form>
    </AuthLayout>
</template>
