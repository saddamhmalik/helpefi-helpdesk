<script setup>
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const page = usePage();
const currentPath = computed(() => page.url.split('?')[0]);

const tabs = [
    { label: 'Inventory', href: '/assets', match: (path) => path === '/assets' || path === '/assets/create' || /^\/assets\/\d+$/.test(path) },
    { label: 'Types', href: '/assets/types', match: (path) => path === '/assets/types' },
    { label: 'Discovery', href: '/assets/discovery', match: (path) => path.startsWith('/assets/discovery') },
];

const isActive = (tab) => tab.match(currentPath.value);
</script>

<template>
    <nav class="mb-6 flex flex-wrap gap-2 border-b border-slate-200 pb-3" aria-label="Assets sections">
        <Link
            v-for="tab in tabs"
            :key="tab.href"
            :href="tab.href"
            class="rounded-lg px-3 py-2 text-sm font-medium transition"
            :class="isActive(tab)
                ? 'bg-slate-900 text-white'
                : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900'"
        >
            {{ tab.label }}
        </Link>
    </nav>
</template>
