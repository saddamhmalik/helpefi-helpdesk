<script setup>
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';

const page = usePage();
const { t } = useI18n();

const currentPath = computed(() => page.url.split('?')[0]);

const tabs = computed(() => [
    { label: t('components.assets_inventory'), href: '/assets', match: (path) => path === '/assets' || path === '/assets/create' || /^\/assets\/\d+$/.test(path) },
    { label: t('components.assets_types'), href: '/assets/types', match: (path) => path === '/assets/types' },
    { label: t('components.assets_discovery'), href: '/assets/discovery', match: (path) => path.startsWith('/assets/discovery') },
]);

const isActive = (tab) => tab.match(currentPath.value);

const tabClass = (tab) => (
    isActive(tab)
        ? 'bg-slate-900 text-white shadow-sm dark:bg-slate-100 dark:text-slate-900'
        : 'agent-text-muted agent-hover-surface hover:text-slate-900 dark:hover:text-slate-100'
);
</script>

<template>
    <nav class="mb-6 flex flex-wrap gap-2 border-b agent-border pb-3" :aria-label="$t('components.assets_sections')">
        <Link
            v-for="tab in tabs"
            :key="tab.href"
            :href="tab.href"
            class="rounded-lg px-3 py-2 text-sm font-medium transition"
            :class="tabClass(tab)"
        >
            {{ tab.label }}
        </Link>
    </nav>
</template>
