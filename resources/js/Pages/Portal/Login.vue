<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import PortalLayout from '../../Layouts/PortalLayout.vue';
import { usePortalRoutes } from '../../composables/usePortalRoutes.js';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();
const { portalPath } = usePortalRoutes();

const form = useForm({
    email: '',
    password: '',
    remember: false,
});

const submit = () => form.post(portalPath('/login'));
</script>

<template>
    <Head :title="$t('portal.sign_in')" />
    <PortalLayout>
        <div class="mx-auto max-w-md">
            <Link :href="portalPath()" class="text-sm text-blue-600 hover:text-blue-700">← Help Center</Link>
            <h1 class="mt-2 text-2xl font-bold text-slate-900">{{ $t('portal.sign_in') }}</h1>
            <p class="mt-1 text-sm text-slate-600">{{ $t('portal.view_and_track_your_support_requests') }}</p>

            <form class="mt-6 space-y-4 rounded-xl border border-slate-200 bg-white p-6 shadow-sm" @submit.prevent="submit">
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">{{ $t('portal.email') }}</label>
                    <input v-model="form.email" type="email" required class="w-full rounded-lg border border-slate-300 px-3 py-2" />
                    <p v-if="form.errors.email" class="mt-1 text-sm text-red-600">{{ form.errors.email }}</p>
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">{{ $t('portal.password') }}</label>
                    <input v-model="form.password" type="password" required class="w-full rounded-lg border border-slate-300 px-3 py-2" />
                </div>
                <label class="flex items-center gap-2 text-sm text-slate-600">
                    <input v-model="form.remember" type="checkbox" class="rounded border-slate-300" />
                    {{ $t('portal.remember_me') }}
                </label>
                <button type="submit" class="w-full rounded-lg bg-blue-600 px-4 py-2 font-medium text-white hover:bg-blue-700" :disabled="form.processing">{{ $t('portal.sign_in') }}</button>
            </form>

            <p class="mt-4 text-center text-sm text-slate-600">
                {{ $t('portal.no_account') }}
                <Link :href="portalPath('/register')" class="text-blue-600 hover:text-blue-700">{{ $t('portal.register') }}</Link>
                {{ $t('portal.or') }}
                <Link :href="portalPath('/track')" class="text-blue-600 hover:text-blue-700">{{ $t('portal.track_without_signing_in') }}</Link>
            </p>
        </div>
    </PortalLayout>
</template>
