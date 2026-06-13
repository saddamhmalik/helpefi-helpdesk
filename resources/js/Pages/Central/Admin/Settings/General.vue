<script setup>
import { ref } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import AdminLayout from '../../../../Layouts/AdminLayout.vue';
import PageHeader from '../../../../Components/PageHeader.vue';
import SettingsTabs from '../../../../Components/Platform/SettingsTabs.vue';

const props = defineProps({
    settings: Object,
});

const purging = ref(false);

const form = useForm({
    trial_days: props.settings.trial_days,
    tenant_purge_grace_days: props.settings.tenant_purge_grace_days ?? 15,
    tenant_purge_enabled: props.settings.tenant_purge_enabled ?? true,
});

const submit = () => {
    form.put('/admin/settings', { preserveScroll: true });
};

const runPurge = () => {
    if (!window.confirm('Delete all workspaces whose trial or paid access expired beyond the grace period? This drops their databases permanently.')) {
        return;
    }

    purging.value = true;

    router.post('/admin/settings/purge-expired-tenants', {}, {
        preserveScroll: true,
        onFinish: () => {
            purging.value = false;
        },
    });
};
</script>

<template>
    <Head :title="$t('central.platform_settings')" />
    <AdminLayout>
        <div class="mx-auto max-w-4xl px-4 py-8 sm:px-6">
            <PageHeader
                :title="$t('central.platform_settings')"
                :description="$t('central.settings_general_description')"
            />

            <SettingsTabs />

            <form class="space-y-6" @submit.prevent="submit">
                <section class="rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 p-6 shadow-sm">
                    <h2 class="font-semibold text-slate-900 dark:text-slate-100">{{ $t('central.free_trial') }}</h2>
                    <p class="mt-1 text-sm text-slate-600 dark:text-slate-400">{{ $t('central.how_long_new_workspaces_can_use_the_platform_before_choosing_a_paid_pl') }}</p>

                    <div class="mt-5">
                        <label class="mb-1.5 block text-sm font-medium text-slate-700 dark:text-slate-300">{{ $t('central.trial_length_days') }}</label>
                        <input v-model.number="form.trial_days" type="number" min="1" max="365" required class="w-32 rounded-xl border border-slate-200 dark:border-slate-800 px-3.5 py-2.5 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20" />
                        <p v-if="form.errors.trial_days" class="mt-1.5 text-xs text-red-600">{{ form.errors.trial_days }}</p>
                    </div>
                </section>

                <section class="rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 p-6 shadow-sm">
                    <h2 class="font-semibold text-slate-900 dark:text-slate-100">{{ $t('central.expired_workspace_cleanup') }}</h2>
                    <p class="mt-1 text-sm text-slate-600 dark:text-slate-400">
                        Automatically delete workspaces after their trial or paid access ends and the grace period passes.
                        The daily scheduler runs <code class="rounded bg-slate-100 dark:bg-slate-900 px-1 py-0.5 text-xs">tenants:purge-expired</code>.
                    </p>

                    <div class="mt-5 grid gap-5 sm:grid-cols-2">
                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-slate-700 dark:text-slate-300">{{ $t('central.grace_period_after_expiry_days') }}</label>
                            <input v-model.number="form.tenant_purge_grace_days" type="number" min="1" max="365" required class="w-32 rounded-xl border border-slate-200 dark:border-slate-800 px-3.5 py-2.5 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20" />
                            <p v-if="form.errors.tenant_purge_grace_days" class="mt-1.5 text-xs text-red-600">{{ form.errors.tenant_purge_grace_days }}</p>
                        </div>
                        <div class="flex items-end">
                            <label class="flex items-center gap-2 text-sm text-slate-700 dark:text-slate-300">
                                <input v-model="form.tenant_purge_enabled" type="checkbox" class="rounded border-slate-300 dark:border-slate-700 text-blue-600 focus:ring-blue-500" />
                                Enable automatic daily purge
                            </label>
                        </div>
                    </div>

                    <div class="mt-5 flex flex-col gap-3 border-t border-slate-100 dark:border-slate-800 pt-5 sm:flex-row sm:items-center sm:justify-between">
                        <p class="text-sm text-slate-600 dark:text-slate-400">{{ $t('central.run_the_purge_job_immediately_for_all_eligible_workspaces') }}</p>
                        <button
                            type="button"
                            class="rounded-xl bg-red-600 px-4 py-2 text-sm font-semibold text-white hover:bg-red-700 disabled:opacity-60"
                            :disabled="purging"
                            @click="runPurge"
                        >
                            {{ purging ? 'Purging…' : 'Run purge now' }}
                        </button>
                    </div>
                </section>

                <button type="submit" class="rounded-xl bg-blue-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-blue-700 disabled:opacity-60" :disabled="form.processing">{{ $t('central.save_settings') }}</button>
            </form>
        </div>
    </AdminLayout>
</template>
