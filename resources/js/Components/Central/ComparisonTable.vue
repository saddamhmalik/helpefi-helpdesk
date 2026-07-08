<script setup>
import { computed } from 'vue';

const props = defineProps({
    rows: { type: Array, required: true },
    platformName: { type: String, required: true },
    competitorName: { type: String, required: true },
    logoUs: { type: String, default: '' },
    logoThem: { type: String, default: '' },
    eyebrow: { type: String, default: '' },
    disclaimer: { type: String, default: '' },
});

const isCheck = (value) => value === true;
const isDash = (value) => value === false;

const cellValue = (value) => {
    if (isCheck(value)) return '✓';
    if (isDash(value)) return '—';
    return value;
};
</script>

<template>
    <section class="bg-slate-950 py-16 sm:py-24">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="text-center">
                <p v-if="eyebrow" class="text-sm font-semibold uppercase tracking-wider text-blue-400">
                    {{ eyebrow }}
                </p>
                <h2 class="mt-3 text-3xl font-bold tracking-tight text-white sm:text-4xl">
                    {{ platformName }} vs {{ competitorName }}
                </h2>
                <p v-if="disclaimer" class="mx-auto mt-4 max-w-2xl text-sm text-slate-400">
                    {{ disclaimer }}
                </p>
            </div>

            <div class="mt-10 flex flex-wrap items-center justify-center gap-6">
                <img
                    v-if="logoUs"
                    :src="logoUs"
                    :alt="`${platformName} logo`"
                    width="160"
                    height="42"
                    class="h-9 w-auto"
                    loading="lazy"
                >
                <img
                    v-if="logoThem"
                    :src="logoThem"
                    :alt="`${competitorName} logo`"
                    width="160"
                    height="42"
                    class="h-9 w-auto opacity-90"
                    loading="lazy"
                >
            </div>

            <div class="mt-12 space-y-3 lg:hidden" role="group" aria-label="Comparison features on mobile">
                <article
                    v-for="row in rows"
                    :key="row.feature"
                    class="rounded-2xl border border-white/10 bg-white/5 p-4"
                >
                    <p class="text-sm font-medium text-slate-200">{{ row.feature }}</p>
                    <div class="mt-3 grid grid-cols-2 gap-3 text-center text-xs">
                        <div class="rounded-xl bg-blue-500/10 px-3 py-2 ring-1 ring-blue-500/20">
                            <p class="font-semibold text-blue-300">{{ platformName }}</p>
                            <p class="mt-1 text-sm leading-snug text-slate-300">{{ cellValue(row.us) }}</p>
                        </div>
                        <div class="rounded-xl bg-white/5 px-3 py-2 ring-1 ring-white/10">
                            <p class="font-semibold text-slate-400">{{ competitorName }}</p>
                            <p class="mt-1 text-sm leading-snug text-slate-400">{{ cellValue(row.them) }}</p>
                        </div>
                    </div>
                </article>
            </div>

            <div class="mt-12 hidden overflow-hidden rounded-2xl border border-white/10 lg:block">
                <table class="w-full text-sm" aria-label="Feature comparison between {{ platformName }} and {{ competitorName }}">
                    <caption class="sr-only">Comparison of {{ platformName }} vs {{ competitorName }} features</caption>
                    <thead>
                        <tr class="border-b border-white/10 bg-white/5">
                            <th class="px-6 py-4 text-left font-medium text-slate-400">Capability</th>
                            <th class="px-6 py-4 text-center font-semibold text-blue-400">{{ platformName }}</th>
                            <th class="px-6 py-4 text-center font-medium text-slate-400">{{ competitorName }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="row in rows"
                            :key="row.feature"
                            class="border-b border-white/5 transition hover:bg-white/5"
                        >
                            <td class="px-6 py-4 font-medium text-slate-200">{{ row.feature }}</td>
                            <td class="px-6 py-4 text-center text-slate-300">
                                <span v-if="isCheck(row.us)" class="text-lg text-emerald-400">✓</span>
                                <span v-else-if="isDash(row.us)" class="text-slate-600">—</span>
                                <span v-else>{{ row.us }}</span>
                            </td>
                            <td class="px-6 py-4 text-center text-slate-400">
                                <span v-if="isCheck(row.them)" class="text-lg text-emerald-400">✓</span>
                                <span v-else-if="isDash(row.them)" class="text-slate-600">—</span>
                                <span v-else>{{ row.them }}</span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </section>
</template>
