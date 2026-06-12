<script setup>
import AgentGlobalSearch from './AgentGlobalSearch.vue';
import AgentUserMenu from './AgentUserMenu.vue';
import NotificationBell from './NotificationBell.vue';
import { Link, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { useAgentBreadcrumbs } from '../composables/useAgentBreadcrumbs.js';

const { crumbs } = useAgentBreadcrumbs();
const searchRef = ref(null);
const helpCenter = computed(() => usePage().props.helpCenter);

const openSearch = () => {
    searchRef.value?.openSearch();
};
</script>

<template>
    <header class="sticky top-0 z-20 border-b agent-border bg-white/90 backdrop-blur-md dark:bg-slate-900/90">
        <div class="hidden h-16 items-center gap-4 px-6 lg:flex">
            <nav :aria-label="$t('components.breadcrumb')" class="flex min-w-0 flex-1 items-center gap-1.5">
                <template v-for="(crumb, index) in crumbs" :key="`${crumb.label}-${index}`">
                    <span v-if="index > 0" class="text-slate-300 dark:text-slate-600">/</span>
                    <Link
                        v-if="crumb.href && index < crumbs.length - 1"
                        :href="crumb.href"
                        class="truncate text-sm agent-text-subtle transition hover:text-slate-800 dark:hover:text-slate-200"
                    >
                        {{ crumb.label }}
                    </Link>
                    <span
                        v-else
                        class="truncate text-sm font-medium agent-text"
                        :class="index < crumbs.length - 1 ? 'agent-text-subtle' : ''"
                    >
                        {{ crumb.label }}
                    </span>
                </template>
            </nav>

            <div class="mx-auto w-full max-w-md xl:max-w-xl">
                <AgentGlobalSearch ref="searchRef" />
            </div>

            <div class="flex shrink-0 items-center gap-1">
                <Link
                    v-if="helpCenter"
                    :href="helpCenter.homeUrl"
                    class="hidden rounded-lg px-3 py-2 text-sm font-medium agent-text-muted transition hover:bg-slate-100 hover:text-slate-900 dark:hover:bg-slate-800 dark:hover:text-slate-100 xl:inline-flex"
                >
                    {{ helpCenter.title }}
                </Link>
                <Link
                    href="/tickets/create"
                    class="inline-flex items-center gap-1.5 rounded-lg bg-blue-600 px-3 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-blue-700"
                >
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                    </svg>
                    {{ $t('components.new_ticket') }}
                </Link>
                <NotificationBell />
                <AgentUserMenu />
            </div>
        </div>

        <div class="flex h-14 items-center gap-2 px-4 lg:hidden">
            <nav :aria-label="$t('components.breadcrumb')" class="min-w-0 flex-1">
                <p class="truncate text-sm font-semibold agent-text">
                    {{ crumbs[crumbs.length - 1]?.label }}
                </p>
                <p v-if="crumbs.length > 1" class="truncate text-xs agent-text-subtle">
                    {{ crumbs[0]?.label }}
                </p>
            </nav>

            <button
                type="button"
                class="rounded-lg p-2 agent-text-subtle transition hover:bg-slate-100 hover:text-slate-700 dark:hover:bg-slate-800 dark:hover:text-slate-200"
                :aria-label="$t('components.search')"
                @click="openSearch"
            >
                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                </svg>
            </button>

            <Link
                href="/tickets/create"
                class="rounded-lg p-2 text-blue-600 transition hover:bg-blue-50 dark:hover:bg-blue-950/50"
                :aria-label="$t('components.new_ticket')"
            >
                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                </svg>
            </Link>

            <NotificationBell />
            <AgentUserMenu />
        </div>
    </header>
</template>
