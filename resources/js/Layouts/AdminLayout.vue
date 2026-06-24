<script setup>
import { Link, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import AppLogo from '../Components/AppLogo.vue';
import PlatformUserMenu from '../Components/Platform/PlatformUserMenu.vue';
import ThemeToggle from '../Components/ThemeToggle.vue';
import { usePlatformAdmin } from '../composables/usePlatformAdmin.js';

const page = usePage();
const { navGroups, can } = usePlatformAdmin();
const mobileNavOpen = ref(false);

const flashSuccess = computed(() => page.props.flash?.success);
const flashError = computed(() => page.props.flash?.error);

const isActive = (item) => item.match(page.url);

const navLinkClass = (item) => (
    isActive(item)
        ? 'bg-blue-600 text-white shadow-sm'
        : 'agent-text-muted hover:bg-slate-100 dark:bg-slate-900 hover:text-slate-900 dark:text-slate-100 dark:hover:bg-slate-800 dark:hover:text-slate-100'
);
</script>

<template>
    <a href="#main-content" class="agent-skip-link">{{ $t('components.skip_to_main_content') }}</a>
    <div class="min-h-screen agent-page-bg">
        <Transition name="slide-over">
            <div v-if="mobileNavOpen" class="fixed inset-0 z-40 lg:hidden">
                <div class="absolute inset-0 bg-slate-900/40 transition-ui" @click="mobileNavOpen = false" />
                <aside class="slide-over-panel absolute inset-y-0 left-0 flex w-72 flex-col agent-panel shadow-xl">
                <div class="flex items-center justify-between border-b agent-border px-5 py-4">
                    <div>
                        <AppLogo size="sm" />
                        <p class="mt-2 font-semibold agent-text">{{ $t('layouts.admin.platform_admin') }}</p>
                    </div>
                    <button type="button" class="rounded-lg p-2 agent-text-subtle agent-hover-surface" @click="mobileNavOpen = false">
                        <span class="sr-only">{{ $t('layouts.admin.close_menu') }}</span>
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
                <nav class="flex-1 space-y-6 overflow-y-auto p-4">
                    <div v-for="group in navGroups" :key="group.label">
                        <p class="mb-2 px-3 text-[11px] font-semibold uppercase tracking-wider text-slate-400 dark:text-slate-500">{{ group.label }}</p>
                        <div class="space-y-1">
                            <template v-for="item in group.items" :key="item.href">
                                <Link
                                    v-if="!item.external"
                                    :href="item.href"
                                    class="block rounded-xl px-3 py-2.5 text-sm font-medium transition-ui"
                                    :class="navLinkClass(item)"
                                    @click="mobileNavOpen = false"
                                >
                                    {{ item.label }}
                                </Link>
                                <a
                                    v-else
                                    :href="item.href"
                                    class="block rounded-xl px-3 py-2.5 text-sm font-medium transition-ui"
                                    :class="navLinkClass(item)"
                                    @click="mobileNavOpen = false"
                                >
                                    {{ item.label }}
                                </a>
                            </template>
                        </div>
                    </div>
                </nav>
                </aside>
            </div>
        </Transition>

        <div class="flex min-h-screen">
            <aside class="hidden w-64 shrink-0 border-r agent-border agent-panel lg:sticky lg:top-0 lg:flex lg:h-screen lg:flex-col lg:overflow-hidden lg:self-start">
                <div class="shrink-0 border-b agent-border px-6 py-5">
                    <AppLogo size="sm" />
                    <p class="mt-2 text-lg font-semibold agent-text">{{ $t('layouts.admin.platform_admin') }}</p>
                </div>
                <nav class="sidebar-nav-scroll flex-1 space-y-6 overflow-y-auto overflow-x-hidden p-4">
                    <div v-for="group in navGroups" :key="group.label">
                        <p class="mb-2 px-3 text-[11px] font-semibold uppercase tracking-wider text-slate-400 dark:text-slate-500">{{ group.label }}</p>
                        <div class="space-y-1">
                            <template v-for="item in group.items" :key="item.href">
                                <Link
                                    v-if="!item.external"
                                    :href="item.href"
                                    class="block rounded-xl px-3 py-2.5 text-sm font-medium transition-ui"
                                    :class="navLinkClass(item)"
                                >
                                    {{ item.label }}
                                </Link>
                                <a
                                    v-else
                                    :href="item.href"
                                    class="block rounded-xl px-3 py-2.5 text-sm font-medium transition-ui"
                                    :class="navLinkClass(item)"
                                >
                                    {{ item.label }}
                                </a>
                            </template>
                        </div>
                    </div>
                </nav>
                <div v-if="can('profile.manage')" class="shrink-0 border-t agent-border p-4">
                    <Link
                        href="/admin/profile"
                        class="block rounded-xl px-3 py-2.5 text-sm font-medium agent-text-muted transition agent-hover-surface"
                        :class="page.url.startsWith('/admin/profile') ? 'bg-blue-50 dark:bg-blue-950/40 text-blue-700 dark:bg-blue-950/50 dark:text-blue-300' : ''"
                    >
                        {{ $t('layouts.admin.profile_password') }}
                    </Link>
                </div>
            </aside>

            <div class="flex min-w-0 flex-1 flex-col">
                <header class="sticky top-0 z-30 border-b agent-border bg-white/90 backdrop-blur dark:bg-slate-900/90">
                    <div class="flex items-center justify-between gap-4 px-4 py-3 sm:px-6">
                        <div class="flex items-center gap-3">
                            <button
                                type="button"
                                class="rounded-lg p-2 agent-text-muted agent-hover-surface lg:hidden"
                                @click="mobileNavOpen = true"
                            >
                                <span class="sr-only">{{ $t('layouts.admin.open_menu') }}</span>
                                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                                </svg>
                            </button>
                            <div class="lg:hidden">
                                <p class="text-sm font-semibold agent-text">{{ $t('layouts.admin.platform_admin') }}</p>
                            </div>
                        </div>
                        <div class="flex items-center gap-2">
                            <ThemeToggle persist="local" />
                            <PlatformUserMenu />
                        </div>
                    </div>
                </header>

                <main id="main-content" class="flex-1">
                    <div v-if="flashSuccess" class="border-b border-emerald-200 dark:border-emerald-900/60 bg-emerald-50 dark:bg-emerald-950/40 px-4 py-3 text-sm text-emerald-800 dark:border-emerald-900 dark:bg-emerald-950/50 dark:text-emerald-200 sm:px-6">
                        {{ flashSuccess }}
                    </div>
                    <div v-if="flashError" class="border-b border-red-200 dark:border-red-900/60 bg-red-50 dark:bg-red-950/40 px-4 py-3 text-sm text-red-800 dark:border-red-900 dark:bg-red-950/50 dark:text-red-200 sm:px-6">
                        {{ flashError }}
                    </div>
                    <slot />
                </main>
            </div>
        </div>
    </div>
</template>

<style scoped>
.sidebar-nav-scroll {
    scrollbar-width: thin;
    scrollbar-color: rgb(203 213 225) transparent;
}

.sidebar-nav-scroll::-webkit-scrollbar {
    width: 6px;
}

.sidebar-nav-scroll::-webkit-scrollbar-thumb {
    border-radius: 9999px;
    background-color: rgb(203 213 225);
}
</style>
