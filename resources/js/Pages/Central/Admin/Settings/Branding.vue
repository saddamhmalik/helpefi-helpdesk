<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import AdminLayout from '../../../../Layouts/AdminLayout.vue';
import PageHeader from '../../../../Components/PageHeader.vue';
import SettingsTabs from '../../../../Components/Platform/SettingsTabs.vue';

const props = defineProps({
    settings: Object,
});

const socialPlatforms = props.settings.social_links ?? [];

const form = useForm({
    social_links: Object.fromEntries(socialPlatforms.map((social) => [social.key, social.url ?? ''])),
});

const submit = () => {
    form.put('/admin/settings', { preserveScroll: true });
};
</script>

<template>
    <Head :title="$t('central.settings_tab_branding')" />
    <AdminLayout>
        <div class="mx-auto max-w-4xl px-4 py-8 sm:px-6">
            <PageHeader
                :title="$t('central.platform_settings')"
                :description="$t('central.settings_branding_description')"
            />

            <SettingsTabs />

            <form class="space-y-6" @submit.prevent="submit">
                <section class="rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 p-6 shadow-sm">
                    <h2 class="font-semibold text-slate-900 dark:text-slate-100">{{ $t('central.social_links') }}</h2>
                    <p class="mt-1 text-sm text-slate-600 dark:text-slate-400">{{ $t('central.social_links_hint') }}</p>

                    <div class="mt-5 grid gap-4 sm:grid-cols-2">
                        <div v-for="social in socialPlatforms" :key="social.key">
                            <label class="mb-1.5 block text-sm font-medium text-slate-700 dark:text-slate-300">{{ social.label }}</label>
                            <input
                                v-model.trim="form.social_links[social.key]"
                                type="url"
                                inputmode="url"
                                :placeholder="social.placeholder"
                                class="w-full rounded-xl border border-slate-200 dark:border-slate-800 px-3.5 py-2.5 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20"
                            />
                            <p v-if="form.errors[`social_links.${social.key}`]" class="mt-1.5 text-xs text-red-600">{{ form.errors[`social_links.${social.key}`] }}</p>
                        </div>
                    </div>
                </section>

                <button type="submit" class="rounded-xl bg-blue-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-blue-700 disabled:opacity-60" :disabled="form.processing">{{ $t('central.save_settings') }}</button>
            </form>
        </div>
    </AdminLayout>
</template>
