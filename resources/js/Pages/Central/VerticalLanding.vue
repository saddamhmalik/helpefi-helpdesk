<script setup>
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import CentralLayout from '../../Layouts/CentralLayout.vue';
import CentralBreadcrumbs from '../../Components/Central/CentralBreadcrumbs.vue';
import CtaSection from '../../Components/Central/CtaSection.vue';
import RelatedArticles from '../../Components/Central/RelatedArticles.vue';
import FaqAccordion from '../../Components/Central/FaqAccordion.vue';

const props = defineProps({
    brand: { type: String, default: 'helpefi' },
    trialDays: { type: Number, default: 14 },
    vertical: { type: String, required: true },
    verticalMeta: { type: Object, default: () => ({}) },
    content: { type: Object, required: true },
    socialLinks: { type: Array, default: () => [] },
});

const platformName = computed(() => props.brand);
const pains = computed(() => props.content.pains ?? []);
const features = computed(() => props.content.features ?? []);
const faqs = computed(() => props.content.faq ?? []);
const relatedLinks = computed(() => props.content.related_links ?? []);

const accent = computed(() => {
    const map = {
        emerald: {
            glow: 'bg-emerald-600/25',
            badge: 'border-emerald-400/30 bg-emerald-500/10 text-emerald-300',
            highlight: 'from-emerald-400 via-teal-300 to-cyan-400',
            card: 'hover:border-emerald-500/30',
            icon: 'text-emerald-400',
        },
        rose: {
            glow: 'bg-rose-600/25',
            badge: 'border-rose-400/30 bg-rose-500/10 text-rose-300',
            highlight: 'from-rose-400 via-red-300 to-orange-400',
            card: 'hover:border-rose-500/30',
            icon: 'text-rose-400',
        },
        violet: {
            glow: 'bg-violet-600/25',
            badge: 'border-violet-400/30 bg-violet-500/10 text-violet-300',
            highlight: 'from-violet-400 via-purple-300 to-fuchsia-400',
            card: 'hover:border-violet-500/30',
            icon: 'text-violet-400',
        },
    };

    return map[props.verticalMeta.accent ?? 'violet'] ?? map.violet;
});

const trustBadges = [
    'No credit card required',
    'Full platform trial',
    'Cancel anytime',
];
</script>

<template>
    <CentralLayout :brand="platformName" :trial-days="trialDays" :social-links="socialLinks">
        <section class="relative overflow-hidden bg-slate-950 text-white">
            <div class="pointer-events-none absolute inset-0">
                <div class="absolute -left-32 top-0 h-[28rem] w-[28rem] rounded-full blur-3xl" :class="accent.glow" />
                <div class="absolute inset-0 bg-[linear-gradient(to_right,#ffffff06_1px,transparent_1px),linear-gradient(to_bottom,#ffffff06_1px,transparent_1px)] bg-[size:3.5rem_3.5rem]" />
            </div>

            <div class="relative mx-auto max-w-7xl px-4 py-16 sm:px-6 sm:py-24 lg:px-8">
                <CentralBreadcrumbs />

                <div class="max-w-3xl">
                    <span class="inline-flex rounded-full border px-4 py-1.5 text-xs font-semibold backdrop-blur" :class="accent.badge">
                        {{ content.badge }}
                    </span>
                    <h1 class="mt-6 text-3xl font-extrabold leading-tight tracking-tight sm:text-5xl">
                        {{ content.hero_title }}
                        <span class="mt-2 block bg-gradient-to-r bg-clip-text text-transparent" :class="accent.highlight">
                            {{ content.hero_highlight }}
                        </span>
                    </h1>
                    <p class="mt-6 text-lg leading-relaxed text-slate-300">
                        {{ content.hero_subtitle }}
                    </p>
                    <div class="mt-8 flex flex-col gap-3 sm:flex-row sm:items-center">
                        <Link
                            href="/register"
                            class="inline-flex items-center justify-center gap-2 rounded-2xl bg-gradient-to-r from-blue-600 to-indigo-600 px-8 py-4 text-sm font-bold text-white shadow-2xl shadow-blue-600/40 transition hover:from-blue-500 hover:to-indigo-500"
                        >
                            Start {{ trialDays }}-day free trial
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M13 7l5 5m0 0l-5 5m5-5H6" /></svg>
                        </Link>
                        <Link
                            href="/#pricing"
                            class="inline-flex items-center justify-center rounded-2xl border border-white/20 px-8 py-4 text-sm font-semibold text-white transition hover:bg-white/10"
                        >
                            View pricing
                        </Link>
                    </div>
                    <div class="mt-8 flex flex-wrap gap-x-5 gap-y-2" aria-label="Trust indicators">
                        <span v-for="badge in trustBadges" :key="badge" class="inline-flex items-center gap-1.5 text-xs text-slate-400">
                            <svg class="h-3.5 w-3.5 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" /></svg>
                            {{ badge }}
                        </span>
                    </div>
                </div>
            </div>
        </section>

        <section class="bg-white py-16 dark:bg-slate-900 sm:py-20">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div class="max-w-2xl">
                    <p class="text-sm font-semibold uppercase tracking-wider text-blue-600">Pain points</p>
                    <h2 class="mt-3 text-3xl font-bold tracking-tight text-slate-900 dark:text-slate-100">Sound familiar?</h2>
                </div>
                <div class="mt-10 grid gap-6 md:grid-cols-3">
                    <article
                        v-for="(pain, index) in pains"
                        :key="index"
                        class="rounded-2xl border border-slate-200 bg-slate-50 p-6 transition dark:border-slate-800 dark:bg-slate-950"
                        :class="accent.card"
                    >
                        <h3 class="text-lg font-semibold text-slate-900 dark:text-slate-100">{{ pain.title }}</h3>
                        <p class="mt-3 text-sm leading-relaxed text-slate-600 dark:text-slate-400">{{ pain.body }}</p>
                    </article>
                </div>
            </div>
        </section>

        <section class="bg-slate-50 py-16 dark:bg-slate-950 sm:py-20">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div class="max-w-2xl">
                    <p class="text-sm font-semibold uppercase tracking-wider text-blue-600">Why teams choose {{ platformName }}</p>
                    <h2 class="mt-3 text-3xl font-bold tracking-tight text-slate-900 dark:text-slate-100">Built for your workflow</h2>
                </div>
                <div class="mt-10 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                    <article
                        v-for="(feature, index) in features"
                        :key="index"
                        class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm transition dark:border-slate-800 dark:bg-slate-900"
                        :class="accent.card"
                    >
                        <svg class="h-6 w-6" :class="accent.icon" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
                        <h3 class="mt-4 text-lg font-semibold text-slate-900 dark:text-slate-100">{{ feature.title }}</h3>
                        <p class="mt-2 text-sm leading-relaxed text-slate-600 dark:text-slate-400">{{ feature.body }}</p>
                    </article>
                </div>
            </div>
        </section>

        <RelatedArticles
            :items="relatedLinks"
            title="Explore related resources"
            variant="links"
        />

        <FaqAccordion
            :items="faqs"
            eyebrow="FAQ"
            title="Common questions"
            section-class="bg-white py-16 dark:bg-slate-900 sm:py-20"
        />

        <CtaSection
            :title="content.cta_title"
            :body="content.cta_body"
            :primary-label="`Start ${trialDays}-day free trial`"
            primary-href="/register"
            secondary-label="Explore full platform"
            secondary-href="/"
        />
    </CentralLayout>
</template>
