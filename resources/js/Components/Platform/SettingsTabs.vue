<script setup>
import { computed } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';

const page = usePage();
const { t } = useI18n();

const tabs = computed(() => [
    { label: t('central.settings_tab_general'), href: '/admin/settings', exact: true },
    { label: t('central.settings_tab_billing'), href: '/admin/settings/billing' },
    { label: t('central.settings_tab_plans'), href: '/admin/settings/plans' },
    { label: t('central.settings_tab_addons'), href: '/admin/settings/addons' },
    { label: t('central.settings_tab_branding'), href: '/admin/settings/branding' },
]);

const isActive = (tab) => (tab.exact ? page.url === tab.href : page.url.startsWith(tab.href));
</script>

<template>
    <nav class="mb-6 flex flex-wrap gap-1 border-b border-slate-200 dark:border-slate-800">
        <Link
            v-for="tab in tabs"
            :key="tab.href"
            :href="tab.href"
            class="-mb-px border-b-2 px-4 py-2.5 text-sm font-medium transition"
            :class="isActive(tab)
                ? 'border-blue-600 text-blue-700 dark:border-blue-400 dark:text-blue-300'
                : 'border-transparent text-slate-500 dark:text-slate-400 hover:border-slate-300 dark:hover:border-slate-600 hover:text-slate-700 dark:hover:text-slate-200'"
        >
            {{ tab.label }}
        </Link>
    </nav>
</template>
