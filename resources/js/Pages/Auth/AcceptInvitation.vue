<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';
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
    <AppLayout>
        <div class="mx-auto max-w-md rounded-xl border border-slate-200 bg-white p-8 shadow-sm">
            <h1 class="text-2xl font-semibold text-slate-900">{{ $t('auth.join_helpdesk') }}</h1>
            <p class="mt-2 text-sm text-slate-600">
                You were invited as <span class="font-medium">{{ invitation.role }}</span> for {{ invitation.email }}.
            </p>

            <form class="mt-6 space-y-4" @submit.prevent="submit">
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">{{ $t('auth.your_name') }}</label>
                    <input v-model="form.name" type="text" class="w-full rounded-lg border border-slate-300 px-3 py-2" required />
                    <p v-if="form.errors.name" class="mt-1 text-sm text-red-600">{{ form.errors.name }}</p>
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">{{ $t('auth.password') }}</label>
                    <input v-model="form.password" type="password" class="w-full rounded-lg border border-slate-300 px-3 py-2" required />
                    <p v-if="form.errors.password" class="mt-1 text-sm text-red-600">{{ form.errors.password }}</p>
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">{{ $t('auth.confirm_password') }}</label>
                    <input v-model="form.password_confirmation" type="password" class="w-full rounded-lg border border-slate-300 px-3 py-2" required />
                </div>
                <button type="submit" class="w-full rounded-lg bg-blue-600 px-4 py-2 font-medium text-white hover:bg-blue-700" :disabled="form.processing">{{ $t('auth.accept_invitation') }}</button>
            </form>
        </div>
    </AppLayout>
</template>
