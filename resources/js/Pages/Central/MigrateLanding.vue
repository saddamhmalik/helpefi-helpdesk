<script setup>
import { Link } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import CentralLayout from '../../Layouts/CentralLayout.vue';
import { formatMarketingTemplate } from '../../composables/useMarketingEnglish.js';

const props = defineProps({
    brand: { type: String, default: 'Helpefi' },
    trialDays: { type: Number, default: 14 },
    source: { type: String, required: true },
    migrateMeta: { type: Object, default: () => ({}) },
    content: { type: Object, required: true },
    marketingChrome: { type: Object, default: () => ({}) },
    socialLinks: { type: Array, default: () => [] },
});

const platformName = computed(() => props.brand);
const sourceName = computed(() => props.content.source_name ?? props.source);
const steps = computed(() => props.content.steps ?? []);
const checklist = computed(() => props.content.checklist ?? []);
const faqs = computed(() => props.content.faq ?? []);

const chrome = (key, params = {}) => formatMarketingTemplate(props.marketingChrome[key] ?? key, {
    brand: platformName.value,
    name: sourceName.value,
    ...params,
});

const comparePath = computed(() => {
    const slug = props.migrateMeta.compare_slug;

    return slug ? `/vs/${slug}` : '/#compare';
});

const accent = computed(() => {
    const map = {
        teal: { glow: 'bg-teal-600/25', badge: 'border-teal-400/30 bg-teal-500/10 text-teal-300', highlight: 'from-teal-400 via-emerald-300 to-green-400' },
        blue: { glow: 'bg-blue-600/25', badge: 'border-blue-400/30 bg-blue-500/10 text-blue-300', highlight: 'from-blue-400 via-indigo-300 to-violet-400' },
        orange: { glow: 'bg-orange-600/20', badge: 'border-orange-400/30 bg-orange-500/10 text-orange-300', highlight: 'from-orange-400 via-amber-300 to-yellow-400' },
        amber: { glow: 'bg-amber-600/20', badge: 'border-amber-400/30 bg-amber-500/10 text-amber-300', highlight: 'from-amber-400 via-orange-300 to-yellow-400' },
        indigo: { glow: 'bg-indigo-600/25', badge: 'border-indigo-400/30 bg-indigo-500/10 text-indigo-300', highlight: 'from-indigo-400 via-violet-300 to-purple-400' },
    };

    return map[props.migrateMeta.accent ?? 'blue'] ?? map.blue;
});

const openFaq = ref(null);

const toggleFaq = (index) => {
    openFaq.value = openFaq.value === index ? null : index;
};
</script>

<template>
    <CentralLayout :brand="platformName" :trial-days="trialDays" :social-links="socialLinks">
        <section class="relative overflow-hidden bg-slate-950 text-white">
            <div class="pointer-events-none absolute inset-0">
                <div class="absolute -left-32 top-0 h-[28rem] w-[28rem] rounded-full blur-3xl" :class="accent.glow" />
            </div>
            <div class="relative mx-auto max-w-7xl px-4 py-16 sm:px-6 sm:py-24 lg:px-8">
                <nav class="mb-8 text-sm text-slate-400" aria-label="Breadcrumb">
                    <ol class="flex flex-wrap items-center gap-2">
                        <li><Link href="/" class="transition hover:text-white">{{ platformName }}</Link></li>
                        <li aria-hidden="true">/</li>
                        <li class="text-slate-300">{{ chrome('migrate_nav') }}</li>
                        <li aria-hidden="true">/</li>
                        <li class="text-slate-300">{{ sourceName }}</li>
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
                        <Link href="/register" class="inline-flex items-center justify-center rounded-2xl bg-gradient-to-r from-blue-600 to-indigo-600 px-8 py-4 text-sm font-bold text-white shadow-2xl shadow-blue-600/40 transition hover:from-blue-500 hover:to-indigo-500">
                            Start {{ trialDays }}-day free trial
                        </Link>
                        <Link :href="comparePath" class="inline-flex items-center justify-center rounded-2xl border border-white/20 px-8 py-4 text-sm font-semibold text-white transition hover:bg-white/10">
                            {{ chrome('migrate_compare_link') }}
                        </Link>
                    </div>
                </div>
            </div>
        </section>

        <section class="bg-white py-16 dark:bg-slate-900 sm:py-20">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <h2 class="text-3xl font-bold tracking-tight text-slate-900 dark:text-slate-100">{{ chrome('migrate_steps_title') }}</h2>
                <p class="mt-4 max-w-2xl text-slate-600 dark:text-slate-400">{{ chrome('migrate_steps_subtitle') }}</p>
                <ol class="mt-10 space-y-6">
                    <li v-for="(step, index) in steps" :key="index" class="flex gap-4 rounded-2xl border border-slate-200 bg-slate-50 p-6 dark:border-slate-800 dark:bg-slate-950">
                        <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-blue-600 text-sm font-bold text-white">{{ index + 1 }}</span>
                        <div>
                            <p class="text-lg font-semibold text-slate-900 dark:text-slate-100">{{ step.title }}</p>
                            <p class="mt-2 text-sm leading-relaxed text-slate-600 dark:text-slate-400">{{ step.body }}</p>
                        </div>
                    </li>
                </ol>
            </div>
        </section>

        <section class="border-y border-slate-200 bg-slate-50 py-16 dark:border-slate-800 dark:bg-slate-950 sm:py-20">
            <div class="mx-auto max-w-3xl px-4 sm:px-6 lg:px-8">
                <h2 class="text-2xl font-bold text-slate-900 dark:text-slate-100">{{ chrome('migrate_checklist_title') }}</h2>
                <ul class="mt-6 space-y-3">
                    <li v-for="(item, index) in checklist" :key="index" class="flex gap-3 text-sm text-slate-700 dark:text-slate-300">
                        <span class="mt-0.5 text-emerald-500">✓</span>
                        <span>{{ item }}</span>
                    </li>
                </ul>
            </div>
        </section>

        <section v-if="faqs.length" class="bg-white py-16 dark:bg-slate-900 sm:py-20">
            <div class="mx-auto max-w-3xl px-4 sm:px-6 lg:px-8">
                <h2 class="text-2xl font-bold text-slate-900 dark:text-slate-100">{{ chrome('faq') }}</h2>
                <div class="mt-8 divide-y divide-slate-200 dark:divide-slate-800">
                    <div v-for="(faq, index) in faqs" :key="index">
                        <button type="button" class="flex w-full items-center justify-between py-4 text-left text-sm font-semibold text-slate-900 dark:text-slate-100" @click="toggleFaq(index)">
                            {{ faq.q }}
                            <span class="text-slate-400">{{ openFaq === index ? '−' : '+' }}</span>
                        </button>
                        <p v-if="openFaq === index" class="pb-4 text-sm leading-relaxed text-slate-600 dark:text-slate-400">{{ faq.a }}</p>
                    </div>
                </div>
            </div>
        </section>

        <section class="bg-slate-950 py-16 text-center sm:py-24">
            <div class="mx-auto max-w-3xl px-4">
                <h2 class="text-3xl font-bold text-white">{{ content.cta_title }}</h2>
                <p class="mt-4 text-lg text-slate-400">{{ content.cta_body }}</p>
                <Link href="/register" class="mt-8 inline-flex rounded-2xl bg-white px-10 py-4 text-sm font-bold text-slate-900">Start {{ trialDays }}-day free trial</Link>
            </div>
        </section>
    </CentralLayout>
</template>
