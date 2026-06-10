<script setup>
import { Link } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import AppLogo from '../Components/AppLogo.vue';

defineProps({
    brand: { type: String, default: 'helpefi' },
    trialDays: { type: Number, default: 14 },
    showFooter: { type: Boolean, default: true },
    showPromoBar: { type: Boolean, default: true },
});

const { t } = useI18n();
const mobileOpen = ref(false);

const navLinks = computed(() => [
    { href: '/#product', label: t('layouts.central.product') },
    { href: '/#differentiators', label: t('layouts.central.why_us') },
    { href: '/#features', label: t('layouts.central.features') },
    { href: '/#how-it-works', label: t('layouts.central.how_it_works') },
    { href: '/#pricing', label: t('layouts.central.pricing') },
    { href: '/#faq', label: t('layouts.central.faq') },
]);
</script>

<template>
    <div class="flex min-h-screen flex-col">
        <div
            v-if="showPromoBar && trialDays"
            class="relative z-50 bg-gradient-to-r from-blue-600 via-indigo-600 to-violet-600 px-4 py-2.5 text-center text-sm font-medium text-white"
        >
            <span>
                🎉 {{ trialDays }}-day free trial — full platform access, no credit card.
            </span>
            <Link href="/register" class="ml-2 font-bold underline underline-offset-2 hover:text-blue-100">
                Start now →
            </Link>
        </div>
        <header class="sticky top-0 z-40 border-b border-slate-200/80 bg-white shadow-sm">
            <div class="mx-auto flex h-16 max-w-7xl items-center justify-between px-4 sm:px-6 lg:px-8">
                <Link href="/" class="flex items-center">
                    <AppLogo size="md" />
                </Link>

                <nav class="hidden items-center gap-1 lg:flex">
                    <a
                        v-for="link in navLinks"
                        :key="link.href"
                        :href="link.href"
                        class="rounded-lg px-3 py-2 text-sm font-medium text-slate-600 transition hover:bg-slate-100 hover:text-slate-900"
                    >
                        {{ link.label }}
                    </a>
                </nav>

                <div class="hidden items-center gap-2 sm:flex">
                    <Link
                        href="/login"
                        class="rounded-lg px-3 py-2 text-sm font-medium text-slate-600 transition hover:text-slate-900"
                    >
                        {{ $t('layouts.central.sign_in') }}
                    </Link>
                    <Link
                        href="/register"
                        class="rounded-xl bg-gradient-to-r from-blue-600 to-indigo-600 px-4 py-2 text-sm font-bold text-white shadow-lg shadow-blue-600/30 transition hover:from-blue-500 hover:to-indigo-500"
                    >
                        {{ $t('layouts.central.start_free_trial') }}
                    </Link>
                </div>

                <button
                    type="button"
                    class="rounded-lg p-2 text-slate-700 lg:hidden"
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

            <Transition name="fade">
                <div
                    v-if="mobileOpen"
                    class="border-t border-slate-200 bg-white px-4 py-4 lg:hidden"
                >
                    <nav class="flex flex-col gap-1">
                        <a
                            v-for="link in navLinks"
                            :key="link.href"
                            :href="link.href"
                            class="rounded-lg px-3 py-2.5 text-sm font-medium text-slate-700"
                            @click="mobileOpen = false"
                        >
                            {{ link.label }}
                        </a>
                        <Link href="/login" class="rounded-lg px-3 py-2.5 text-sm font-medium text-slate-600" @click="mobileOpen = false">
                            {{ $t('layouts.central.sign_in') }}
                        </Link>
                        <Link href="/register" class="mt-2 rounded-xl bg-blue-600 px-3 py-2.5 text-center text-sm font-semibold text-white" @click="mobileOpen = false">
                            {{ $t('layouts.central.start_free_trial') }}
                        </Link>
                    </nav>
                </div>
            </Transition>
        </header>

        <main class="flex-1">
            <slot />
        </main>

        <footer v-if="showFooter" class="border-t border-slate-200 bg-slate-950 text-slate-300">
            <div class="mx-auto max-w-7xl px-4 py-14 sm:px-6 lg:px-8">
                <div class="grid gap-10 md:grid-cols-2 lg:grid-cols-4">
                    <div class="lg:col-span-1">
                        <AppLogo size="md" surface="light" />
                        <p class="mt-3 text-sm leading-relaxed text-slate-400">
                            {{ $t('layouts.central.footer_tagline') }}
                        </p>
                    </div>
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">{{ $t('layouts.central.footer_product') }}</p>
                        <ul class="mt-4 space-y-2.5 text-sm">
                            <li><a href="/#differentiators" class="transition hover:text-white">{{ $t('layouts.central.why_us') }}</a></li>
                            <li><a href="/#features" class="transition hover:text-white">{{ $t('layouts.central.features') }}</a></li>
                            <li><a href="/#product" class="transition hover:text-white">{{ $t('layouts.central.footer_platform_overview') }}</a></li>
                            <li><a href="/#pricing" class="transition hover:text-white">{{ $t('layouts.central.pricing') }}</a></li>
                            <li><a href="/#faq" class="transition hover:text-white">{{ $t('layouts.central.faq') }}</a></li>
                        </ul>
                    </div>
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">{{ $t('layouts.central.footer_get_started') }}</p>
                        <ul class="mt-4 space-y-2.5 text-sm">
                            <li><Link href="/register" class="transition hover:text-white">{{ $t('layouts.central.start_free_trial') }}</Link></li>
                            <li><Link href="/login" class="transition hover:text-white">{{ $t('layouts.central.footer_sign_in_workspace') }}</Link></li>
                            <li><a href="/#how-it-works" class="transition hover:text-white">{{ $t('layouts.central.footer_setup_guide') }}</a></li>
                        </ul>
                    </div>
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">{{ $t('layouts.central.footer_capabilities') }}</p>
                        <ul class="mt-4 space-y-2.5 text-sm text-slate-400">
                            <li>{{ $t('layouts.central.footer_capability_inbox') }}</li>
                            <li>{{ $t('layouts.central.footer_capability_channels') }}</li>
                            <li>{{ $t('layouts.central.footer_capability_kb') }}</li>
                            <li>{{ $t('layouts.central.footer_capability_sla') }}</li>
                            <li>{{ $t('layouts.central.footer_capability_service_desk') }}</li>
                            <li>{{ $t('layouts.central.footer_capability_integrations') }}</li>
                        </ul>
                    </div>
                </div>
                <div class="mt-12 border-t border-white/10 pt-8">
                    <p class="text-xs text-slate-500">© {{ new Date().getFullYear() }} {{ brand }}. {{ $t('layouts.central.footer_rights') }}</p>
                </div>
            </div>
        </footer>
    </div>
</template>
