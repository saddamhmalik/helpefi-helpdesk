<script setup>
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useAgentNavigation } from '../composables/useAgentNavigation.js';
import { isSettingsNavActive } from '../composables/useSettingsSection.js';

const page = usePage();
const { settingsNavGroups } = useAgentNavigation();

const currentUrl = computed(() => page.url.split('#')[0]);
</script>

<template>
    <aside class="settings-sidebar hidden w-56 shrink-0 lg:block">
        <Link
            href="/dashboard"
            class="mb-5 inline-flex items-center gap-2 text-sm font-medium text-slate-500 transition hover:text-slate-800"
        >
            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
            </svg>
            Back to app
        </Link>

        <nav class="space-y-6" aria-label="Settings">
            <div v-for="group in settingsNavGroups" :key="group.id">
                <p class="mb-2 px-2 text-[11px] font-semibold uppercase tracking-[0.12em] text-slate-400">
                    {{ group.label }}
                </p>
                <ul class="space-y-0.5">
                    <li v-for="item in group.items" :key="item.href">
                        <Link
                            :href="item.href"
                            class="block rounded-lg px-2.5 py-2 text-sm transition"
                            :class="isSettingsNavActive(item.href, currentUrl)
                                ? 'bg-slate-900 font-medium text-white'
                                : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900'"
                        >
                            {{ item.label }}
                        </Link>
                    </li>
                </ul>
            </div>
        </nav>
    </aside>
</template>
