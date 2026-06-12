<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import AuthLayout from '../../Layouts/AuthLayout.vue';
import { formInputClass } from '../../composables/useFormControls.js';
import { useI18n } from 'vue-i18n';

const props = defineProps({
    invitation: Object,
});

const { t } = useI18n();

const form = useForm({
    name: '',
    password: '',
    password_confirmation: '',
});

const submit = () => form.post(`/invitations/${props.invitation.token}`);
</script>

<template>
    <Head :title="$t('auth.accept_invitation')" />
    <AuthLayout
        :aside-title="$t('auth.join_helpdesk')"
        :aside-description="$t('auth.accept_invitation')"
    >
        <h1 class="text-2xl font-semibold tracking-tight agent-text">{{ $t('auth.join_helpdesk') }}</h1>
        <p class="mt-2 text-sm agent-text-muted">
            You were invited as <span class="font-medium agent-text">{{ invitation.role }}</span> for {{ invitation.email }}.
        </p>

        <form class="mt-8 space-y-5" @submit.prevent="submit">
            <div>
                <label class="mb-1.5 block text-sm font-medium agent-text-muted">{{ $t('auth.your_name') }}</label>
                <input v-model="form.name" type="text" :class="formInputClass" required autofocus />
                <p v-if="form.errors.name" class="mt-1.5 text-xs text-red-600">{{ form.errors.name }}</p>
            </div>
            <div>
                <label class="mb-1.5 block text-sm font-medium agent-text-muted">{{ $t('auth.password') }}</label>
                <input v-model="form.password" type="password" :class="formInputClass" required />
                <p v-if="form.errors.password" class="mt-1.5 text-xs text-red-600">{{ form.errors.password }}</p>
            </div>
            <div>
                <label class="mb-1.5 block text-sm font-medium agent-text-muted">{{ $t('auth.confirm_password') }}</label>
                <input v-model="form.password_confirmation" type="password" :class="formInputClass" required />
            </div>
            <button
                type="submit"
                class="w-full rounded-xl bg-gradient-to-r from-blue-600 to-indigo-600 px-4 py-3 text-sm font-semibold text-white shadow-md shadow-blue-600/25 transition hover:from-blue-700 hover:to-indigo-700 disabled:opacity-60"
                :disabled="form.processing"
            >
                {{ $t('auth.accept_invitation') }}
            </button>
        </form>
    </AuthLayout>
</template>
