<script setup>
import { Link } from '@inertiajs/vue3';
import { onMounted, onUnmounted, ref } from 'vue';

defineProps({
    brand: { type: String, default: 'Helpdesk' },
    trialDays: { type: Number, default: 14 },
    transparentNav: { type: Boolean, default: false },
    showFooter: { type: Boolean, default: true },
});

const mobileOpen = ref(false);
const scrolled = ref(false);

const onScroll = () => {
    scrolled.value = window.scrollY > 24;
};

onMounted(() => {
    window.addEventListener('scroll', onScroll, { passive: true });
    onScroll();
});

onUnmounted(() => {
    window.removeEventListener('scroll', onScroll);
});

const navLinks = [
    { href: '/#product', label: 'Product' },
    { href: '/#features', label: 'Features' },
    { href: '/#how-it-works', label: 'How it works' },
    { href: '/#pricing', label: 'Pricing' },
    { href: '/#faq', label: 'FAQ' },
];
</script>

<template>
    <div class="flex min-h-screen flex-col">
        <header
            class="sticky top-0 z-40 border-b backdrop-blur-xl transition-all duration-300"
            :class="[
                transparentNav && !scrolled
                    ? 'border-white/10 bg-slate-950/60'
                    : transparentNav && scrolled
                        ? 'border-slate-200/80 bg-white/95 shadow-sm'
                        : 'border-slate-200/80 bg-white/95 shadow-sm',
            ]"
        >
            <div class="mx-auto flex h-16 max-w-7xl items-center justify-between px-4 sm:px-6 lg:px-8">
                <Link href="/" class="flex items-center gap-2.5">
                    <span
                        class="flex h-9 w-9 items-center justify-center rounded-xl text-sm font-bold shadow-sm"
                        :class="transparentNav && !scrolled ? 'bg-blue-500 text-white' : 'bg-slate-900 text-white'"
                    >
                        {{ brand.charAt(0) }}
                    </span>
                    <span
                        class="text-base font-semibold tracking-tight"
                        :class="transparentNav && !scrolled ? 'text-white' : 'text-slate-900'"
                    >
                        {{ brand }}
                    </span>
                </Link>

                <nav class="hidden items-center gap-1 lg:flex">
                    <a
                        v-for="link in navLinks"
                        :key="link.href"
                        :href="link.href"
                        class="rounded-lg px-3 py-2 text-sm font-medium transition"
                        :class="transparentNav && !scrolled ? 'text-slate-300 hover:bg-white/10 hover:text-white' : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900'"
                    >
                        {{ link.label }}
                    </a>
                </nav>

                <div class="hidden items-center gap-2 sm:flex">
                    <Link
                        href="/login"
                        class="rounded-lg px-3 py-2 text-sm font-medium transition"
                        :class="transparentNav && !scrolled ? 'text-slate-200 hover:text-white' : 'text-slate-600 hover:text-slate-900'"
                    >
                        Sign in
                    </Link>
                    <Link
                        href="/register"
                        class="rounded-xl px-4 py-2 text-sm font-semibold shadow-sm transition"
                        :class="transparentNav && !scrolled ? 'bg-white text-slate-900 hover:bg-slate-100' : 'bg-blue-600 text-white hover:bg-blue-700'"
                    >
                        Start free trial
                    </Link>
                </div>

                <button
                    type="button"
                    class="rounded-lg p-2 lg:hidden"
                    :class="transparentNav && !scrolled ? 'text-white' : 'text-slate-700'"
                    @click="mobileOpen = !mobileOpen"
                >
                    <svg v-if="!mobileOpen" class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                    </svg>
                    <svg v-else class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <div
                v-if="mobileOpen"
                class="border-t px-4 py-4 lg:hidden"
                :class="transparentNav && !scrolled ? 'border-white/10 bg-slate-950' : 'border-slate-200 bg-white'"
            >
                <nav class="flex flex-col gap-1">
                    <a
                        v-for="link in navLinks"
                        :key="link.href"
                        :href="link.href"
                        class="rounded-lg px-3 py-2.5 text-sm font-medium"
                        :class="transparentNav && !scrolled ? 'text-slate-200' : 'text-slate-700'"
                        @click="mobileOpen = false"
                    >
                        {{ link.label }}
                    </a>
                    <Link href="/login" class="rounded-lg px-3 py-2.5 text-sm font-medium text-slate-600" @click="mobileOpen = false">
                        Sign in
                    </Link>
                    <Link href="/register" class="mt-2 rounded-xl bg-blue-600 px-3 py-2.5 text-center text-sm font-semibold text-white" @click="mobileOpen = false">
                        Start free trial
                    </Link>
                </nav>
            </div>
        </header>

        <main class="flex-1">
            <slot />
        </main>

        <footer v-if="showFooter" class="border-t border-slate-200 bg-slate-950 text-slate-300">
            <div class="mx-auto max-w-7xl px-4 py-14 sm:px-6 lg:px-8">
                <div class="grid gap-10 md:grid-cols-2 lg:grid-cols-4">
                    <div class="lg:col-span-1">
                        <p class="text-lg font-semibold text-white">{{ brand }}</p>
                        <p class="mt-3 text-sm leading-relaxed text-slate-400">
                            Modern customer support software — tickets, chat, knowledge base, and automation in one workspace built for growing teams.
                        </p>
                    </div>
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">Product</p>
                        <ul class="mt-4 space-y-2.5 text-sm">
                            <li><a href="/#features" class="transition hover:text-white">Features</a></li>
                            <li><a href="/#product" class="transition hover:text-white">Platform overview</a></li>
                            <li><a href="/#pricing" class="transition hover:text-white">Pricing</a></li>
                            <li><a href="/#faq" class="transition hover:text-white">FAQ</a></li>
                        </ul>
                    </div>
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">Get started</p>
                        <ul class="mt-4 space-y-2.5 text-sm">
                            <li><Link href="/register" class="transition hover:text-white">Start free trial</Link></li>
                            <li><Link href="/login" class="transition hover:text-white">Sign in to workspace</Link></li>
                            <li><Link href="/admin/login" class="transition hover:text-white">Platform admin</Link></li>
                            <li><a href="/#how-it-works" class="transition hover:text-white">Setup guide</a></li>
                        </ul>
                    </div>
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">Capabilities</p>
                        <ul class="mt-4 space-y-2.5 text-sm text-slate-400">
                            <li>Shared inbox & tickets</li>
                            <li>Live chat widget</li>
                            <li>Knowledge base & portal</li>
                            <li>SLA & automation</li>
                            <li>AI assist & integrations</li>
                        </ul>
                    </div>
                </div>
                <div class="mt-12 flex flex-col gap-4 border-t border-white/10 pt-8 sm:flex-row sm:items-center sm:justify-between">
                    <p class="text-xs text-slate-500">© {{ new Date().getFullYear() }} {{ brand }}. All rights reserved.</p>
                    <p class="text-xs text-slate-500">Self-hosted ready · Multi-tenant workspaces · Your data, your subdomain</p>
                </div>
            </div>
        </footer>
    </div>
</template>
