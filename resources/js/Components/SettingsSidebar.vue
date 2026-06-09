<script setup>
import { Link, usePage } from '@inertiajs/vue3';
import { computed, nextTick, ref, watch } from 'vue';
import { useAgentNavigation } from '../composables/useAgentNavigation.js';
import { isSettingsNavActive } from '../composables/useSettingsSection.js';

const page = usePage();
const { settingsNavGroups } = useAgentNavigation();
const navRef = ref(null);

const currentUrl = computed(() => page.url.split('#')[0]);

const scrollActiveIntoView = () => {
    nextTick(() => {
        const nav = navRef.value;

        if (!nav) {
            return;
        }

        const active = nav.querySelector('[data-settings-nav-active="true"]');

        if (active) {
            active.scrollIntoView({ block: 'nearest' });
        }
    });
};

watch(currentUrl, scrollActiveIntoView, { immediate: true });
</script>

<template>
    <aside class="settings-sidebar hidden w-56 shrink-0 border-r border-transparent pr-2 lg:sticky lg:top-16 lg:z-10 lg:flex lg:h-[calc(100vh-4rem)] lg:max-h-[calc(100vh-4rem)] lg:flex-col lg:overflow-hidden lg:self-start lg:border-slate-200">
        <Link
            href="/dashboard"
            class="mb-3 inline-flex shrink-0 items-center gap-2 text-sm font-medium text-slate-500 transition hover:text-slate-800"
        >
            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
            </svg>
            Back to app
        </Link>

        <nav ref="navRef" class="settings-sidebar-scroll min-h-0 flex-1 space-y-4 overflow-y-auto pr-1" aria-label="Settings">
            <div v-for="group in settingsNavGroups" :key="group.id">
                <p class="mb-1.5 px-2 text-[11px] font-semibold uppercase tracking-[0.12em] text-slate-400">
                    {{ group.label }}
                </p>
                <ul class="space-y-0.5">
                    <li v-for="item in group.items" :key="item.href">
                        <Link
                            :href="item.href"
                            class="flex items-center justify-between gap-2 rounded-lg px-2.5 py-2 text-sm font-medium transition"
                            :class="isSettingsNavActive(item.href, currentUrl)
                                ? 'bg-slate-900 text-white'
                                : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900'"
                            :title="item.description"
                            :data-settings-nav-active="isSettingsNavActive(item.href, currentUrl) ? 'true' : undefined"
                        >
                            <span class="truncate">{{ item.label }}</span>
                            <span
                                v-if="item.locked"
                                class="shrink-0 rounded-full px-1.5 py-0.5 text-[10px] font-semibold uppercase tracking-wide"
                                :class="isSettingsNavActive(item.href, currentUrl)
                                    ? 'bg-white/15 text-white/90'
                                    : 'bg-amber-50 text-amber-700'"
                            >
                                {{ item.lockedLabel }}
                            </span>
                        </Link>
                    </li>
                </ul>
            </div>
        </nav>
    </aside>
</template>

<style scoped>
.settings-sidebar-scroll {
    scrollbar-width: thin;
    scrollbar-color: rgb(203 213 225) transparent;
}

.settings-sidebar-scroll::-webkit-scrollbar {
    width: 6px;
}

.settings-sidebar-scroll::-webkit-scrollbar-thumb {
    border-radius: 9999px;
    background-color: rgb(203 213 225);
}
</style>
