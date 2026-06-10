<script setup>
import { Head } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
</script>

<template>
    <Head :title="$t('tenant_blocked.workspace_unavailable')" />
    <div class="flex min-h-screen items-center justify-center bg-slate-100 px-4">
        <div class="max-w-md rounded-2xl border border-slate-200 bg-white p-8 text-center shadow-sm">
            <p class="text-sm font-semibold uppercase tracking-wider text-red-600">{{ $t('tenant_blocked.access_suspended') }}</p>
            <h1 class="mt-2 text-2xl font-semibold text-slate-900">{{ $t('tenant_blocked.this_workspace_has_been_blocked') }}</h1>
            <p class="mt-3 text-sm text-slate-600">
                {{ $t('tenant_blocked.contact_platform_support_if_you_believe_this_is_a_mistake') }}
            </p>
        </div>
    </div>
</template>
