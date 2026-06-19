<script setup>
import AgentTopBar from '../Components/AgentTopBar.vue';
import AppAvatar from '../Components/AppAvatar.vue';
import AppLogo from '../Components/AppLogo.vue';
import DummyDataBanner from '../Components/DummyDataBanner.vue';
import PlatformNoticeModal from '../Components/PlatformNoticeModal.vue';
import TrialBanner from '../Components/TrialBanner.vue';
import CancellationGraceBanner from '../Components/CancellationGraceBanner.vue';
import { Link, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { useAgentNavigation } from '../composables/useAgentNavigation.js';

const { t } = useI18n();

const page = usePage();
const { user, navSections, mobileNav, homeHref, settingsHref } = useAgentNavigation();
const profileHref = '/settings/profile';

const expanded = ref(false);

const isOpen = computed(() => expanded.value);
const pageKey = computed(() => page.url.split('?')[0]);

const isFullHeightPage = computed(() => {
    const path = pageKey.value;

    if (path === '/workspace' || path.startsWith('/workspace/')) {
        return true;
    }

    return /^\/tickets\/\d+/.test(path);
});

const isSettingsPage = computed(() => {
    const path = pageKey.value;

    return path === '/settings' || path.startsWith('/settings/');
});

const isConstrainedHeightPage = computed(() => isFullHeightPage.value || isSettingsPage.value);

const isSetupPage = computed(() => pageKey.value === '/setup');

const isActive = (href) => {
    const path = page.url.split('?')[0];

    if (href === '/workspace') {
        return path === href || path.startsWith('/workspace/');
    }

    if (href === settingsHref.value) {
        return path.startsWith('/settings') || path === '/admin';
    }

    if (href === '/how-to') {
        return path === '/how-to';
    }

    return path === href || path.startsWith(`${href}/`);
};

const navItemClass = (href) => {
    if (isActive(href)) {
        return 'bg-white/10 text-white';
    }

    return 'text-slate-400 hover:bg-white/5 hover:text-slate-100';
};

const iconWrapClass = (href) => isActive(href)
    ? 'text-white'
    : 'text-slate-500 dark:text-slate-400 group-hover:text-slate-300';
</script>

<template>
    <div class="h-screen overflow-hidden agent-page-bg lg:flex">
        <aside
            class="sidebar-shell group/sidebar fixed left-0 top-0 z-40 hidden h-screen flex-col overflow-hidden transition-sidebar lg:flex"
            :class="[
                isOpen ? 'w-[15.5rem] shadow-2xl shadow-slate-900/25' : 'w-[4.25rem]',
            ]"
            @mouseenter="expanded = true"
            @mouseleave="expanded = false"
        >
            <div
                class="flex h-16 shrink-0 items-center border-b border-white/[0.06] px-3 transition-sidebar"
                :class="isOpen ? 'justify-start' : 'justify-center'"
            >
                <Link :href="homeHref" class="flex min-w-0 items-center gap-3">
                    <AppLogo :mark-only="!isOpen" :size="isOpen ? 'md' : 'sm'" />
                </Link>
            </div>

            <nav class="sidebar-nav-scroll flex-1 overflow-y-auto overflow-x-hidden px-2 py-4">
                <div
                    v-for="(section, sectionIndex) in navSections"
                    :key="section.id"
                    class="transition-sidebar"
                    :class="sectionIndex > 0 ? (isOpen ? 'mt-6' : 'mt-1') : ''"
                >
                    <p
                        class="sidebar-section-label px-2.5 text-[10px] font-semibold uppercase tracking-[0.14em] text-slate-500 dark:text-slate-400"
                        :class="{ 'sidebar-section-label--open': isOpen }"
                    >
                        {{ section.label }}
                    </p>

                    <div class="space-y-0.5">
                        <Link
                            v-for="item in section.items"
                            :key="item.href"
                            :href="item.href"
                            :title="isOpen ? undefined : item.label"
                            class="group flex items-center rounded-lg text-[13px] font-medium transition-ui"
                            :class="[
                                navItemClass(item.href),
                                isOpen ? 'gap-2.5 px-2.5 py-2' : 'justify-center p-2.5',
                            ]"
                        >
                            <svg class="h-[1.0625rem] w-[1.0625rem] shrink-0 transition-ui" :class="iconWrapClass(item.href)" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" :d="item.icon" />
                            </svg>
                            <span
                                class="sidebar-label truncate"
                                :class="{ 'sidebar-label--open': isOpen }"
                            >
                                {{ item.label }}
                            </span>
                        </Link>
                    </div>
                </div>
            </nav>

            <div class="shrink-0 space-y-1 border-t border-white/[0.06] p-2">
                <Link
                    :href="settingsHref"
                    class="group flex items-center rounded-lg text-[13px] font-medium transition-ui"
                    :class="[
                        navItemClass(settingsHref),
                        isOpen ? 'gap-2.5 px-2.5 py-2' : 'justify-center p-2.5',
                    ]"
                    :title="isOpen ? undefined : t('common.settings')"
                >
                    <svg class="h-[1.0625rem] w-[1.0625rem] shrink-0 transition-ui" :class="iconWrapClass(settingsHref)" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                    </svg>
                    <span
                        class="sidebar-label truncate"
                        :class="{ 'sidebar-label--open': isOpen }"
                    >
                        {{ t('common.settings') }}
                    </span>
                </Link>

                <Link
                    :href="profileHref"
                    class="flex items-center rounded-lg px-2 py-2 transition-ui hover:bg-white/5"
                    :class="isOpen ? 'gap-2.5' : 'justify-center'"
                >
                    <AppAvatar :name="user?.name" :email="user?.email" :image-url="user?.avatar_url" size="sm" />
                    <div
                        class="sidebar-label min-w-0 flex-1"
                        :class="{ 'sidebar-label--open': isOpen }"
                    >
                        <p class="truncate text-[13px] font-medium text-white">{{ user?.name }}</p>
                        <p class="truncate text-[11px] text-slate-500 dark:text-slate-400">{{ user?.email }}</p>
                    </div>
                </Link>
            </div>
        </aside>

        <div
            class="flex h-screen max-h-screen min-w-0 flex-1 flex-col overflow-hidden transition-sidebar"
            :class="isOpen ? 'lg:pl-[15.5rem]' : 'lg:pl-[4.25rem]'"
        >
            <AgentTopBar />

            <nav class="flex gap-1 overflow-x-auto border-b agent-border agent-panel px-3 py-2 lg:hidden">
                <Link
                    v-for="item in mobileNav"
                    :key="item.href"
                    :href="item.href"
                    class="whitespace-nowrap rounded-md px-3 py-1.5 text-sm font-medium transition-ui"
                    :class="isActive(item.href) ? 'bg-slate-900 text-white dark:bg-slate-100 dark:bg-slate-900 dark:text-slate-900 dark:text-slate-100' : 'agent-text-muted'"
                >
                    {{ item.label }}
                </Link>
            </nav>

            <main class="flex min-h-0 flex-1 flex-col overflow-hidden">
                <div class="mb-1.5 shrink-0 space-y-1 empty:mb-0 empty:hidden px-4 pt-2 sm:px-6">
                    <TrialBanner />
                    <CancellationGraceBanner />
                    <DummyDataBanner v-if="!isSetupPage" />
                </div>
                <PlatformNoticeModal />
                <div
                    class="flex min-h-0 flex-1 flex-col overflow-x-hidden pb-4 pt-3 sm:pb-6 sm:pt-4"
                    :class="[
                        isConstrainedHeightPage ? 'overflow-hidden' : 'overflow-y-auto',
                        isSettingsPage ? 'px-0' : 'px-4 sm:px-6',
                    ]"
                >
                    <Transition name="page" mode="out-in">
                        <div
                            :key="pageKey"
                            :class="isFullHeightPage ? 'flex h-full min-h-0 flex-1 flex-col' : isSettingsPage ? 'flex min-h-0 flex-1 flex-col' : 'w-full'"
                        >
                            <slot />
                        </div>
                    </Transition>
                </div>
            </main>
        </div>
    </div>
</template>

<style scoped>
.sidebar-shell {
    background: #111827;
    border-right: 1px solid rgba(255, 255, 255, 0.06);
}

.sidebar-nav-scroll {
    scrollbar-width: thin;
    scrollbar-color: rgba(148, 163, 184, 0.2) transparent;
}

.sidebar-nav-scroll::-webkit-scrollbar {
    width: 4px;
}

.sidebar-nav-scroll::-webkit-scrollbar-thumb {
    background: rgba(148, 163, 184, 0.2);
    border-radius: 999px;
}
</style>
