<script setup>
import AgentTopBar from '../Components/AgentTopBar.vue';
import AppAvatar from '../Components/AppAvatar.vue';
import SetupWarningBanner from '../Components/SetupWarningBanner.vue';
import TrialBanner from '../Components/TrialBanner.vue';
import CancellationGraceBanner from '../Components/CancellationGraceBanner.vue';
import { Link, usePage } from '@inertiajs/vue3';
import { computed, onMounted, ref } from 'vue';
import { useAgentNavigation } from '../composables/useAgentNavigation.js';

const page = usePage();
const { user, navSections, flatNavItems, mainNav, homeHref, settingsHref } = useAgentNavigation();

const pinned = ref(true);
const hovered = ref(false);

onMounted(() => {
    pinned.value = localStorage.getItem('sidebar-collapsed') !== '1';
});

const isOpen = computed(() => pinned.value || hovered.value);

const togglePin = () => {
    pinned.value = !pinned.value;
    hovered.value = false;
    localStorage.setItem('sidebar-collapsed', pinned.value ? '0' : '1');
};

const onSidebarEnter = () => {
    if (!pinned.value) {
        hovered.value = true;
    }
};

const onSidebarLeave = () => {
    hovered.value = false;
};

const isActive = (href) => {
    const path = page.url.split('?')[0];

    if (href === '/workspace') {
        return path === href || path.startsWith('/workspace/');
    }

    if (href === settingsHref.value) {
        return path.startsWith('/settings') || path === '/admin';
    }

    return path === href || path.startsWith(`${href}/`);
};

const navItemClass = (href) => {
    if (isActive(href)) {
        return isOpen.value
            ? 'bg-white/10 text-white'
            : 'bg-white/10 text-white';
    }

    return 'text-slate-400 hover:bg-white/5 hover:text-slate-100';
};

const iconWrapClass = (href) => isActive(href)
    ? 'text-white'
    : 'text-slate-500 group-hover:text-slate-300';
</script>

<template>
    <div class="min-h-screen bg-slate-50 lg:flex">
        <aside
            class="sidebar-shell group/sidebar relative hidden shrink-0 transition-[width,box-shadow] duration-300 ease-out lg:sticky lg:top-0 lg:flex lg:h-screen lg:flex-col lg:overflow-hidden"
            :class="[
                isOpen ? 'w-[15.5rem]' : 'w-[4.25rem]',
                !pinned && hovered ? 'z-30 shadow-2xl shadow-slate-900/20' : '',
            ]"
            @mouseenter="onSidebarEnter"
            @mouseleave="onSidebarLeave"
        >
            <button
                type="button"
                class="sidebar-edge-toggle absolute -right-3 top-[4.5rem] z-20 flex h-6 w-6 items-center justify-center rounded-full border border-white/10 bg-slate-800 text-slate-400 shadow-lg transition hover:border-white/20 hover:bg-slate-700 hover:text-white"
                :title="pinned ? 'Collapse sidebar' : 'Pin sidebar open'"
                @click="togglePin"
            >
                <svg
                    class="h-3.5 w-3.5 transition-transform duration-300"
                    :class="pinned ? '' : 'rotate-180'"
                    fill="none"
                    stroke="currentColor"
                    viewBox="0 0 24 24"
                >
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                </svg>
            </button>

            <div class="flex h-14 shrink-0 items-center border-b border-white/[0.06] px-3" :class="isOpen ? 'justify-start' : 'justify-center'">
                <Link :href="homeHref" class="flex min-w-0 items-center gap-3">
                    <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-blue-600">
                        <svg class="h-4 w-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 5.636l-3.536 3.536m0 5.656l3.536 3.536M9.172 9.172L5.636 5.636m3.536 9.192l-3.536 3.536M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </span>
                    <span
                        v-show="isOpen"
                        class="truncate text-[15px] font-semibold text-white"
                    >
                        Helpdesk
                    </span>
                </Link>
            </div>

            <nav class="sidebar-nav-scroll flex-1 overflow-y-auto overflow-x-hidden px-2 py-4">
                <template v-if="!isOpen">
                    <div class="space-y-1">
                        <Link
                            v-for="item in flatNavItems"
                            :key="item.href"
                            :href="item.href"
                            :title="item.label"
                            class="group relative flex items-center justify-center rounded-lg p-2.5 transition"
                            :class="navItemClass(item.href)"
                        >
                            <svg class="h-[1.125rem] w-[1.125rem]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" :d="item.icon" />
                            </svg>
                        </Link>
                    </div>
                </template>

                <template v-else>
                    <div v-for="(section, sectionIndex) in navSections" :key="section.id" :class="sectionIndex > 0 ? 'mt-6' : ''">
                        <p class="mb-1.5 px-2.5 text-[10px] font-semibold uppercase tracking-[0.14em] text-slate-500">
                            {{ section.label }}
                        </p>

                        <div class="space-y-0.5">
                            <Link
                                v-for="item in section.items"
                                :key="item.href"
                                :href="item.href"
                                class="group flex items-center gap-2.5 rounded-lg px-2.5 py-2 text-[13px] font-medium transition"
                                :class="navItemClass(item.href)"
                            >
                                <svg class="h-[1.0625rem] w-[1.0625rem] shrink-0 transition" :class="iconWrapClass(item.href)" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" :d="item.icon" />
                                </svg>
                                <span class="truncate">{{ item.label }}</span>
                            </Link>
                        </div>
                    </div>
                </template>
            </nav>

            <div class="shrink-0 space-y-1 border-t border-white/[0.06] p-2">
                <Link
                    :href="settingsHref"
                    class="group flex items-center gap-2.5 rounded-lg px-2.5 py-2 text-[13px] font-medium transition"
                    :class="navItemClass(settingsHref)"
                    :title="isOpen ? undefined : 'Settings'"
                >
                    <svg class="h-[1.0625rem] w-[1.0625rem] shrink-0" :class="iconWrapClass(settingsHref)" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                    </svg>
                    <span v-show="isOpen" class="truncate">Settings</span>
                </Link>

                <Link
                    :href="settingsHref"
                    class="flex items-center gap-2.5 rounded-lg px-2 py-2 transition hover:bg-white/5"
                    :class="isOpen ? '' : 'justify-center'"
                >
                    <AppAvatar :name="user?.name" :email="user?.email" size="sm" />
                    <div v-show="isOpen" class="min-w-0 flex-1">
                        <p class="truncate text-[13px] font-medium text-white">{{ user?.name }}</p>
                        <p class="truncate text-[11px] text-slate-500">{{ user?.email }}</p>
                    </div>
                </Link>
            </div>
        </aside>

        <div class="flex min-h-screen min-w-0 flex-1 flex-col">
            <AgentTopBar />

            <nav class="flex gap-1 overflow-x-auto border-b border-slate-200 bg-white px-3 py-2 lg:hidden">
                <Link
                    v-for="item in mainNav"
                    :key="item.href"
                    :href="item.href"
                    class="whitespace-nowrap rounded-md px-3 py-1.5 text-sm font-medium"
                    :class="isActive(item.href) ? 'bg-slate-900 text-white' : 'text-slate-600'"
                >
                    {{ item.label }}
                </Link>
            </nav>

            <main class="flex-1 p-4 sm:p-6">
                <div v-if="page.props.flash?.invite_url" class="mb-4 rounded-lg border border-blue-200 bg-blue-50 px-4 py-3 text-sm text-blue-900">
                    <p class="font-medium">Invitation link</p>
                    <a :href="page.props.flash.invite_url" class="break-all underline">{{ page.props.flash.invite_url }}</a>
                </div>
                <TrialBanner />
                <CancellationGraceBanner />
                <SetupWarningBanner />
                <slot />
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

.sidebar-edge-toggle {
    opacity: 0;
}

.group\/sidebar:hover .sidebar-edge-toggle,
.sidebar-edge-toggle:focus-visible {
    opacity: 1;
}
</style>
