<script setup>
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import CentralLayout from '../../Layouts/CentralLayout.vue';
import { formatMarketingTemplate } from '../../composables/useMarketingEnglish.js';
import CentralBreadcrumbs from '../../Components/Central/CentralBreadcrumbs.vue';

const props = defineProps({
    brand: { type: String, default: 'helpefi' },
    trialDays: { type: Number, default: 14 },
    competitorSlug: { type: String, required: true },
    competitor: { type: Object, required: true },
    helpefi: { type: Object, required: true },
    matrix: { type: Object, required: true },
    socialLinks: { type: Array, default: () => [] },
});

const page = usePage();
const chrome = computed(() => page.props.marketingChrome ?? {});
const chromeText = (key, params = {}) => formatMarketingTemplate(chrome.value[key] ?? key, { brand: props.brand, ...params });

const competitorName = computed(() => props.competitor?.name ?? props.competitorSlug);
const weaknesses = computed(() => props.competitor?.weaknesses ?? []);
const advantages = computed(() => props.helpefi?.advantages ?? []);
const rows = computed(() => props.matrix?.rows ?? []);
</script>

<template>
    <CentralLayout :brand="brand" :trial-days="trialDays" :social-links="socialLinks">
        <section class="relative overflow-hidden bg-[#0F172A] text-white">
            <div class="pointer-events-none absolute inset-0">
                <div class="absolute -left-40 -top-28 h-[34rem] w-[34rem] rounded-full bg-indigo-500/20 blur-3xl" />
                <div class="absolute -right-40 -top-24 h-[30rem] w-[30rem] rounded-full bg-blue-500/15 blur-3xl" />
                <div class="absolute inset-0 bg-[linear-gradient(to_right,#ffffff08_1px,transparent_1px),linear-gradient(to_bottom,#ffffff08_1px,transparent_1px)] bg-[size:3.5rem_3.5rem]" />
            </div>

            <div class="relative mx-auto max-w-7xl px-4 py-14 sm:px-6 sm:py-20 lg:px-8">
                <CentralBreadcrumbs />

                <div class="grid gap-10 lg:grid-cols-12 lg:items-start">
                    <div class="lg:col-span-7">
                        <p class="inline-flex items-center gap-2 rounded-full border border-white/10 bg-white/5 px-4 py-1.5 text-xs font-semibold text-slate-200">
                            {{ brand }} vs {{ competitorName }}
                        </p>
                        <h1 class="mt-6 text-3xl font-extrabold tracking-tight sm:text-5xl">
                            Switch without the surprises.
                            <span class="mt-2 block bg-gradient-to-r from-blue-300 via-indigo-200 to-violet-300 bg-clip-text text-transparent">
                                Built for real isolation and AI-first support.
                            </span>
                        </h1>
                        <p class="mt-6 text-lg leading-relaxed text-slate-300">
                            A practical, structural comparison of what matters when you’re choosing a helpdesk: isolation, AI, pricing clarity, and speed to launch.
                        </p>

                        <div class="mt-8 flex flex-col gap-3 sm:flex-row sm:items-center">
                            <Link
                                href="/register"
                                class="inline-flex items-center justify-center rounded-2xl bg-gradient-to-r from-blue-600 to-indigo-600 px-8 py-4 text-sm font-bold text-white shadow-2xl shadow-blue-600/35 transition hover:from-blue-500 hover:to-indigo-500"
                            >
                                Live in under 2 minutes
                            </Link>
                            <Link
                                href="/pricing"
                                class="inline-flex items-center justify-center rounded-2xl border border-white/15 bg-white/0 px-8 py-4 text-sm font-semibold text-white transition hover:bg-white/10"
                            >
                                See pricing
                            </Link>
                        </div>

                        <p class="mt-4 text-sm text-slate-400">
                            Live in under 2 minutes, no credit card required.
                        </p>
                    </div>

                    <aside class="lg:col-span-5">
                        <div class="rounded-2xl border border-white/10 bg-white/5 p-6 backdrop-blur">
                            <p class="text-xs font-semibold uppercase tracking-wider text-slate-300">
                                Why teams move off {{ competitorName }}
                            </p>
                            <ul class="mt-4 space-y-3 text-sm text-slate-200">
                                <li v-for="(item, index) in weaknesses" :key="index" class="flex gap-3">
                                    <span class="mt-1 h-2 w-2 shrink-0 rounded-full bg-amber-300/80" aria-hidden="true" />
                                    <span class="leading-relaxed text-slate-200">{{ item }}</span>
                                </li>
                            </ul>

                            <div class="mt-6 border-t border-white/10 pt-6">
                                <p class="text-xs font-semibold uppercase tracking-wider text-slate-300">
                                    What you get with {{ brand }}
                                </p>
                                <div class="mt-4 space-y-3">
                                    <div v-for="(adv, index) in advantages" :key="index" class="rounded-xl border border-white/10 bg-slate-950/30 p-4">
                                        <p class="text-sm font-semibold text-white">{{ adv.title }}</p>
                                        <p class="mt-2 text-sm leading-relaxed text-slate-300">{{ adv.body }}</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </aside>
                </div>
            </div>
        </section>

        <section class="bg-[#0B1223] py-14 text-white sm:py-20">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                    <div class="max-w-2xl">
                        <p class="text-sm font-semibold uppercase tracking-wider text-blue-300/90">
                            Structural comparison matrix
                        </p>
                        <h2 class="mt-3 text-3xl font-bold tracking-tight sm:text-4xl">
                            {{ matrix.headline }}
                        </h2>
                        <p class="mt-3 text-sm leading-relaxed text-slate-300">
                            Focused on the big levers: isolation, AI, pricing clarity, and speed to launch.
                        </p>
                    </div>
                    <div class="flex gap-3">
                        <Link
                            href="/register"
                            class="inline-flex items-center justify-center rounded-xl bg-white px-5 py-3 text-sm font-bold text-slate-900 transition hover:bg-slate-100"
                        >
                            Start free
                        </Link>
                        <Link
                            href="/"
                            class="inline-flex items-center justify-center rounded-xl border border-white/15 px-5 py-3 text-sm font-semibold text-white transition hover:bg-white/10"
                        >
                            Explore product
                        </Link>
                    </div>
                </div>

                <div class="mt-10 overflow-x-auto rounded-2xl border border-white/10 bg-white/5">
                    <table class="min-w-[44rem] w-full border-collapse text-left text-sm">
                        <thead>
                            <tr class="border-b border-white/10 bg-white/5">
                                <th scope="col" class="px-6 py-4 font-semibold text-slate-200">
                                    Capability
                                </th>
                                <th scope="col" class="px-6 py-4 text-center font-semibold text-blue-300">
                                    {{ brand }}
                                </th>
                                <th scope="col" class="px-6 py-4 text-center font-semibold text-slate-200">
                                    {{ competitorName }}
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="row in rows" :key="row.capability" class="border-b border-white/5 last:border-b-0 hover:bg-white/5">
                                <th scope="row" class="px-6 py-4 font-medium text-slate-200">
                                    {{ row.capability }}
                                </th>
                                <td class="px-6 py-4 text-center text-slate-100">
                                    {{ row.helpefi }}
                                </td>
                                <td class="px-6 py-4 text-center text-slate-300">
                                    {{ row.competitor }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>

        <section class="bg-[#0F172A] py-16 text-white sm:py-24">
            <div class="mx-auto max-w-5xl px-4 sm:px-6 lg:px-8">
                <div class="relative overflow-hidden rounded-3xl border border-white/10 bg-gradient-to-br from-white/10 to-white/5 p-8 sm:p-12">
                    <div class="pointer-events-none absolute inset-0">
                        <div class="absolute -right-28 -top-20 h-72 w-72 rounded-full bg-indigo-500/20 blur-3xl" />
                        <div class="absolute -left-28 -bottom-24 h-72 w-72 rounded-full bg-blue-500/15 blur-3xl" />
                    </div>
                    <div class="relative grid gap-8 lg:grid-cols-12 lg:items-center">
                        <div class="lg:col-span-8">
                            <h2 class="text-3xl font-bold tracking-tight sm:text-4xl">
                                Live in under 2 minutes, no credit card required
                            </h2>
                            <p class="mt-4 text-lg leading-relaxed text-slate-200/90">
                                Spin up a workspace, invite your team, and start resolving tickets with isolation-first infrastructure and native AI help.
                            </p>
                        </div>
                        <div class="lg:col-span-4 lg:text-right">
                            <Link
                                href="/register"
                                class="inline-flex w-full items-center justify-center rounded-2xl bg-white px-8 py-4 text-sm font-bold text-slate-900 shadow-xl transition hover:bg-slate-100 lg:w-auto"
                            >
                                Create your workspace
                            </Link>
                            <p class="mt-3 text-xs text-slate-200/70 lg:text-right">
                                No credit card. Launch fast. Scale cleanly.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </CentralLayout>
</template>

