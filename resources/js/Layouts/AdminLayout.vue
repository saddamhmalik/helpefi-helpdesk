<script setup>
import { Link, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import AppLogo from '../Components/AppLogo.vue';
import PlatformUserMenu from '../Components/Platform/PlatformUserMenu.vue';
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
        : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900'
);
</script>

<template>
    <div class="min-h-screen bg-slate-50">
        <Transition name="slide-over">
            <div v-if="mobileNavOpen" class="fixed inset-0 z-40 lg:hidden">
                <div class="absolute inset-0 bg-slate-900/40 transition-ui" @click="mobileNavOpen = false" />
                <aside class="slide-over-panel absolute inset-y-0 left-0 flex w-72 flex-col bg-white shadow-xl">
                <div class="flex items-center justify-between border-b border-slate-200 px-5 py-4">
                    <div>
                        <AppLogo size="sm" />
                        <p class="mt-2 font-semibold text-slate-900">{{ $t('layouts.admin.platform_admin') }}</p>
                    </div>
                    <button type="button" class="rounded-lg p-2 text-slate-500 hover:bg-slate-100" @click="mobileNavOpen = false">
                        <span class="sr-only">{{ $t('layouts.admin.close_menu') }}</span>
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
                <nav class="flex-1 space-y-6 overflow-y-auto p-4">
                    <div v-for="group in navGroups" :key="group.label">
                        <p class="mb-2 px-3 text-[11px] font-semibold uppercase tracking-wider text-slate-400">{{ group.label }}</p>
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
            <aside class="hidden w-64 shrink-0 border-r border-slate-200 bg-white lg:sticky lg:top-0 lg:flex lg:h-screen lg:flex-col lg:overflow-hidden lg:self-start">
                <div class="shrink-0 border-b border-slate-200 px-6 py-5">
                    <AppLogo size="sm" />
                    <p class="mt-2 text-lg font-semibold text-slate-900">{{ $t('layouts.admin.platform_admin') }}</p>
                </div>
                <nav class="sidebar-nav-scroll flex-1 space-y-6 overflow-y-auto overflow-x-hidden p-4">
                    <div v-for="group in navGroups" :key="group.label">
                        <p class="mb-2 px-3 text-[11px] font-semibold uppercase tracking-wider text-slate-400">{{ group.label }}</p>
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
                <div v-if="can('profile.manage')" class="shrink-0 border-t border-slate-200 p-4">
                    <Link
                        href="/admin/profile"
                        class="block rounded-xl px-3 py-2.5 text-sm font-medium text-slate-600 transition hover:bg-slate-100 hover:text-slate-900"
                        :class="page.url.startsWith('/admin/profile') ? 'bg-blue-50 text-blue-700' : ''"
                    >
                        {{ $t('layouts.admin.profile_password') }}
                    </Link>
                </div>
            </aside>

            <div class="flex min-w-0 flex-1 flex-col">
                <header class="sticky top-0 z-30 border-b border-slate-200 bg-white/90 backdrop-blur">
                    <div class="flex items-center justify-between gap-4 px-4 py-3 sm:px-6">
                        <div class="flex items-center gap-3">
                            <button
                                type="button"
                                class="rounded-lg p-2 text-slate-600 hover:bg-slate-100 lg:hidden"
                                @click="mobileNavOpen = true"
                            >
                                <span class="sr-only">{{ $t('layouts.admin.open_menu') }}</span>
                                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                                </svg>
                            </button>
                            <div class="lg:hidden">
                                <p class="text-sm font-semibold text-slate-900">{{ $t('layouts.admin.platform_admin') }}</p>
                            </div>
                        </div>
                        <PlatformUserMenu />
                    </div>
                </header>

                <main class="flex-1">
                    <div v-if="flashSuccess" class="border-b border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800 sm:px-6">
                        {{ flashSuccess }}
                    </div>
                    <div v-if="flashError" class="border-b border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800 sm:px-6">
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
