<script setup>
import { Link, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import CentralLayout from '../../Layouts/CentralLayout.vue';

const props = defineProps({
    brand: { type: String, default: 'helpefi' },
    trialDays: { type: Number, default: 14 },
    feature: { type: String, required: true },
    featureMeta: { type: Object, default: () => ({}) },
    content: { type: Object, required: true },
    featurePages: { type: Array, default: () => [] },
    socialLinks: { type: Array, default: () => [] },
});

const page = usePage();
const chrome = computed(() => page.props.marketingChrome ?? {});

const platformName = computed(() => props.brand);

const features = computed(() => props.content.features ?? []);

const faqs = computed(() => props.content.faq ?? []);

const relatedFeatures = computed(() => props.featurePages.filter((page) => page.slug !== props.feature).slice(0, 3));

const accent = computed(() => {
    const map = {
        violet: { glow: 'bg-violet-600/25', badge: 'border-violet-400/30 bg-violet-500/10 text-violet-300', highlight: 'from-violet-400 via-purple-300 to-fuchsia-400', card: 'hover:border-violet-500/30', icon: 'text-violet-400' },
        blue: { glow: 'bg-blue-600/25', badge: 'border-blue-400/30 bg-blue-500/10 text-blue-300', highlight: 'from-blue-400 via-indigo-300 to-violet-400', card: 'hover:border-blue-500/30', icon: 'text-blue-400' },
        emerald: { glow: 'bg-emerald-600/25', badge: 'border-emerald-400/30 bg-emerald-500/10 text-emerald-300', highlight: 'from-emerald-400 via-teal-300 to-cyan-400', card: 'hover:border-emerald-500/30', icon: 'text-emerald-400' },
        amber: { glow: 'bg-amber-600/25', badge: 'border-amber-400/30 bg-amber-500/10 text-amber-300', highlight: 'from-amber-400 via-orange-300 to-red-400', card: 'hover:border-amber-500/30', icon: 'text-amber-400' },
        cyan: { glow: 'bg-cyan-600/25', badge: 'border-cyan-400/30 bg-cyan-500/10 text-cyan-300', highlight: 'from-cyan-400 via-sky-300 to-blue-400', card: 'hover:border-cyan-500/30', icon: 'text-cyan-400' },
        indigo: { glow: 'bg-indigo-600/25', badge: 'border-indigo-400/30 bg-indigo-500/10 text-indigo-300', highlight: 'from-indigo-400 via-blue-300 to-violet-400', card: 'hover:border-indigo-500/30', icon: 'text-indigo-400' },
        sky: { glow: 'bg-sky-600/25', badge: 'border-sky-400/30 bg-sky-500/10 text-sky-300', highlight: 'from-sky-400 via-cyan-300 to-teal-400', card: 'hover:border-sky-500/30', icon: 'text-sky-400' },
    };

    return map[props.featureMeta.accent ?? 'violet'] ?? map.violet;
});

const openFaq = ref(null);

const toggleFaq = (index) => {
    openFaq.value = openFaq.value === index ? null : index;
};
</script>

<template>
    <CentralLayout :brand="brand" :trial-days="trialDays" :social-links="socialLinks">
        <section class="relative overflow-hidden bg-slate-950 text-white">
            <div class="pointer-events-none absolute inset-0">
                <div class="absolute -left-32 top-0 h-[28rem] w-[28rem] rounded-full blur-3xl" :class="accent.glow" />
            </div>

            <div class="relative mx-auto max-w-7xl px-4 py-16 sm:px-6 sm:py-24 lg:px-8">
                <nav class="mb-8 text-sm text-slate-400" aria-label="Breadcrumb">
                    <ol class="flex flex-wrap items-center gap-2">
                        <li><Link href="/" class="transition hover:text-white">{{ platformName }}</Link></li>
                        <li aria-hidden="true">/</li>
                        <li><Link href="/features" class="transition hover:text-white">{{ chrome.features ?? 'Features' }}</Link></li>
                        <li aria-hidden="true">/</li>
                        <li class="text-slate-300">{{ content.badge }}</li>
                    </ol>
                </nav>

                <div class="max-w-3xl">
                    <span class="inline-flex rounded-full border px-4 py-1.5 text-xs font-semibold backdrop-blur" :class="accent.badge">{{ content.badge }}</span>
                    <h1 class="mt-6 text-3xl font-extrabold leading-tight tracking-tight sm:text-5xl">
                        {{ content.hero_title }}
                        <span class="mt-2 block bg-gradient-to-r bg-clip-text text-transparent" :class="accent.highlight">{{ content.hero_highlight }}</span>
                    </h1>
                    <p class="mt-6 text-lg leading-relaxed text-slate-300">{{ content.hero_subtitle }}</p>
                    <div class="mt-8 flex flex-col gap-3 sm:flex-row">
                        <Link href="/register" class="inline-flex items-center justify-center gap-2 rounded-2xl bg-gradient-to-r from-blue-600 to-indigo-600 px-8 py-4 text-sm font-bold text-white">Start {{ trialDays }}-day free trial</Link>
                        <Link href="/pricing" class="inline-flex items-center justify-center rounded-2xl border border-white/20 px-8 py-4 text-sm font-semibold text-white transition hover:bg-white/10">View pricing</Link>
                    </div>
                </div>
            </div>
        </section>

        <section class="bg-white py-16 dark:bg-slate-900 sm:py-20">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <h2 class="text-3xl font-bold tracking-tight text-slate-900 dark:text-slate-100">Why teams choose {{ platformName }}</h2>
                <div class="mt-10 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                    <article v-for="(feature, index) in features" :key="index" class="rounded-2xl border border-slate-200 bg-slate-50 p-6 dark:border-slate-800 dark:bg-slate-950">
                        <h3 class="text-lg font-semibold text-slate-900 dark:text-slate-100">{{ feature.title }}</h3>
                        <p class="mt-2 text-sm leading-relaxed text-slate-600 dark:text-slate-400">{{ feature.body }}</p>
                    </article>
                </div>
            </div>
        </section>

        <section v-if="relatedFeatures.length" class="border-t border-slate-200 bg-slate-50 py-12 dark:border-slate-800 dark:bg-slate-950">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <h2 class="text-lg font-semibold text-slate-900 dark:text-slate-100">Related features</h2>
                <div class="mt-4 flex flex-wrap gap-3">
                    <Link
                        v-for="page in relatedFeatures"
                        :key="page.slug"
                        :href="page.path"
                        class="rounded-full border border-slate-200 bg-white px-4 py-2 text-sm font-medium text-slate-700 transition hover:border-blue-300 hover:text-blue-700 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200"
                    >
                        {{ page.nav_label }}
                    </Link>
                </div>
            </div>
        </section>

        <section v-if="faqs.length" class="bg-white py-16 dark:bg-slate-900">
            <div class="mx-auto max-w-3xl px-4 sm:px-6 lg:px-8">
                <h2 class="text-center text-3xl font-bold text-slate-900 dark:text-slate-100">FAQ</h2>
                <div class="mt-10 space-y-3">
                    <div v-for="(item, index) in faqs" :key="item.q" class="overflow-hidden rounded-2xl border border-slate-200 dark:border-slate-800">
                        <button type="button" class="flex w-full items-center justify-between gap-4 px-5 py-4 text-left" @click="toggleFaq(index)">
                            <span class="text-sm font-semibold text-slate-900 dark:text-slate-100">{{ item.q }}</span>
                        </button>
                        <div v-show="openFaq === index" class="border-t border-slate-200 px-5 pb-5 pt-2 dark:border-slate-800">
                            <p class="text-sm text-slate-600 dark:text-slate-400">{{ item.a }}</p>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section class="bg-slate-950 py-16">
            <div class="mx-auto max-w-4xl px-4 text-center">
                <h2 class="text-3xl font-bold text-white">{{ content.cta_title }}</h2>
                <p class="mt-4 text-lg text-slate-400">{{ content.cta_body }}</p>
                <Link href="/register" class="mt-8 inline-flex rounded-2xl bg-white px-10 py-4 text-sm font-bold text-slate-900">Start {{ trialDays }}-day free trial</Link>
            </div>
        </section>
    </CentralLayout>
</template>
