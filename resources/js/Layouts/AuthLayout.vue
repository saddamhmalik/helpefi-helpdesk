<script setup>
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import AppLogo from '../Components/AppLogo.vue';
import HelpCenterSearch from '../Components/HelpCenterSearch.vue';
import ThemeToggle from '../Components/ThemeToggle.vue';

defineProps({
    asideTitle: {
        type: String,
        default: '',
    },
    asideDescription: {
        type: String,
        default: '',
    },
});

const { t } = useI18n();
const helpCenter = computed(() => usePage().props.helpCenter);
</script>

<template>
    <a href="#main-content" class="agent-skip-link">{{ $t('components.skip_to_main_content') }}</a>
    <div class="relative min-h-screen overflow-hidden bg-gradient-to-br from-slate-50 via-white to-blue-50/50 dark:from-slate-950 dark:via-slate-900 dark:to-slate-950">
        <div class="pointer-events-none absolute inset-0" aria-hidden="true">
            <div class="absolute -left-24 top-0 h-72 w-72 rounded-full bg-blue-200/30 blur-3xl dark:bg-blue-900/20" />
            <div class="absolute -right-16 top-32 h-96 w-96 rounded-full bg-indigo-200/25 blur-3xl dark:bg-indigo-900/15" />
            <div class="absolute bottom-0 left-1/3 h-64 w-64 rounded-full bg-violet-200/20 blur-3xl dark:bg-violet-900/10" />
        </div>

        <header class="relative border-b border-slate-200/70 bg-white/80 backdrop-blur-xl dark:border-slate-800/70 dark:bg-slate-900/80">
            <div class="mx-auto flex max-w-6xl flex-wrap items-center justify-between gap-4 px-6 py-4">
                <Link href="/login" class="flex items-center transition-opacity hover:opacity-90">
                    <AppLogo size="md" />
                </Link>

                <div v-if="helpCenter" class="flex flex-1 flex-wrap items-center justify-end gap-3 sm:gap-4">
                    <HelpCenterSearch class="hidden min-w-[16rem] flex-1 sm:block sm:max-w-xs lg:max-w-sm" />
                    <Link
                        :href="helpCenter.homeUrl"
                        class="shrink-0 text-sm font-medium agent-text-muted transition hover:text-slate-900 dark:text-slate-100 dark:hover:text-slate-100"
                    >
                        {{ helpCenter.title }}
                    </Link>
                    <ThemeToggle persist="local" />
                </div>
                <div v-else class="flex items-center">
                    <ThemeToggle persist="local" />
                </div>
            </div>
        </header>

        <main id="main-content" class="relative flex min-h-[calc(100vh-4.25rem)] items-center justify-center px-4 py-10 sm:px-6">
            <div class="mx-auto grid w-full max-w-4xl overflow-hidden rounded-2xl border agent-border agent-panel shadow-xl shadow-slate-300/30 dark:shadow-black/40 lg:grid-cols-5">
                <aside class="relative hidden overflow-hidden bg-gradient-to-br from-slate-950 via-slate-900 to-blue-950 p-8 text-white lg:col-span-2 lg:flex lg:flex-col lg:justify-between">
                    <div class="pointer-events-none absolute inset-0 opacity-40" aria-hidden="true">
                        <div class="absolute -right-8 -top-8 h-40 w-40 rounded-full bg-blue-500/30 blur-2xl" />
                        <div class="absolute bottom-8 left-4 h-32 w-32 rounded-full bg-indigo-500/20 blur-2xl" />
                    </div>
                    <div class="relative">
                        <slot name="aside">
                            <h2 class="text-xl font-semibold tracking-tight">{{ asideTitle }}</h2>
                            <p class="mt-3 text-sm leading-relaxed text-slate-400 dark:text-slate-500">{{ asideDescription }}</p>
                        </slot>
                    </div>
                    <div class="relative">
                        <slot name="aside-footer" />
                    </div>
                </aside>

                <div class="p-8 sm:p-10 lg:col-span-3">
                    <slot />
                </div>
            </div>
        </main>
    </div>
</template>
