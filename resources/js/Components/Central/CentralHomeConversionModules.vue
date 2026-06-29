<script setup>
import { computed, ref } from 'vue';

const props = defineProps({
    baseDomain: { type: String, default: 'helpefi.com' },
    plans: { type: Array, default: () => [] },
});

const slugInput = ref('');
const slugError = ref('');

const sanitizeSlug = (value) => (
    String(value ?? '')
        .trim()
        .toLowerCase()
        .replace(/^https?:\/\//, '')
        .replace(/[^a-z0-9-]/g, '')
        .replace(/-+/g, '-')
        .replace(/^-+|-+$/g, '')
);

const submitWorkspace = () => {
    slugError.value = '';
    const slug = sanitizeSlug(slugInput.value);
    slugInput.value = slug;

    if (!slug) {
        slugError.value = 'Enter your workspace slug.';
        return;
    }

    window.location.href = `https://${slug}.${props.baseDomain}/login`;
};

const agents = ref(12);
const competitorPerSeat = 100;

const normalizePlanSlug = (value) => String(value ?? '').toLowerCase().trim();

const planCandidates = computed(() => {
    const all = Array.isArray(props.plans) ? props.plans : [];
    const picked = all.filter((plan) => (
        ['starter', 'professional', 'enterprise'].includes(normalizePlanSlug(plan?.slug))
        && !plan?.custom_pricing
    ));

    return picked.length ? picked : all.filter((plan) => !plan?.custom_pricing);
});

const monthlyUsdPrice = (plan) => Number(plan?.price_monthly ?? plan?.price ?? 0) || 0;
const agentsLimit = (plan) => {
    const limit = Number(plan?.limits?.agents);
    return Number.isFinite(limit) && limit > 0 ? limit : Infinity;
};

const tierPlan = computed(() => {
    const count = Math.max(0, Number(agents.value) || 0);
    const sorted = [...planCandidates.value].sort((a, b) => agentsLimit(a) - agentsLimit(b));
    const match = sorted.find((plan) => agentsLimit(plan) >= count);
    return match ?? sorted.at(-1) ?? null;
});

const helpefiPrice = computed(() => {
    const plan = tierPlan.value;
    if (plan) {
        return monthlyUsdPrice(plan);
    }

    const count = Number(agents.value) || 0;
    if (count <= 3) return 29;
    if (count <= 15) return 79;
    return 199;
});

const competitorPrice = computed(() => Math.max(0, (Number(agents.value) || 0) * competitorPerSeat));
const savings = computed(() => Math.max(0, competitorPrice.value - helpefiPrice.value));

const formatUsd = (amount) => {
    const value = Number(amount) || 0;
    return new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD', maximumFractionDigits: 0 }).format(value);
};
</script>

<template>
    <section class="grid gap-4 lg:grid-cols-12 lg:gap-6">
        <div class="rounded-3xl border border-white/10 bg-[#0F172A]/80 p-6 shadow-2xl shadow-blue-900/20 backdrop-blur lg:col-span-4">
            <p class="text-xs font-semibold uppercase tracking-wider text-slate-300">Workspace finder</p>
            <h3 class="mt-2 text-lg font-bold text-white">Jump back into your helpdesk</h3>
            <p class="mt-2 text-sm leading-relaxed text-slate-300">
                Enter your workspace slug and go straight to sign in.
            </p>

            <form class="mt-4 space-y-2" @submit.prevent="submitWorkspace">
                <label class="block text-sm font-medium text-slate-200">Workspace slug</label>
                <div class="flex overflow-hidden rounded-2xl border border-white/10 bg-slate-950/40 focus-within:border-blue-400/60 focus-within:ring-2 focus-within:ring-blue-500/20">
                    <input
                        v-model="slugInput"
                        inputmode="text"
                        autocomplete="off"
                        autocapitalize="none"
                        spellcheck="false"
                        placeholder="acme-support"
                        class="min-w-0 flex-1 bg-transparent px-4 py-3 text-sm text-white placeholder:text-slate-500 focus:outline-none"
                        @keydown.enter.prevent="submitWorkspace"
                    >
                    <div class="flex items-center gap-2 border-l border-white/10 px-4 text-sm text-slate-300">
                        <span class="text-slate-500">.</span>
                        <span class="font-semibold">{{ baseDomain }}</span>
                    </div>
                </div>

                <p v-if="slugError" class="text-xs font-medium text-rose-300">{{ slugError }}</p>

                <button
                    type="submit"
                    class="mt-3 inline-flex w-full items-center justify-center rounded-2xl bg-gradient-to-r from-blue-600 to-indigo-600 px-4 py-3 text-sm font-bold text-white shadow-lg shadow-blue-600/30 transition hover:from-blue-500 hover:to-indigo-500"
                >
                    Go to Workspace
                </button>
            </form>
        </div>

        <div class="relative overflow-hidden rounded-3xl border border-white/10 bg-[#0F172A]/80 p-6 shadow-2xl shadow-blue-900/20 backdrop-blur lg:col-span-8">
            <div class="pointer-events-none absolute inset-0">
                <div class="absolute -right-24 -top-24 h-64 w-64 rounded-full bg-blue-500/10 blur-3xl" />
                <div class="absolute -left-24 -bottom-24 h-64 w-64 rounded-full bg-indigo-500/10 blur-3xl" />
            </div>

            <div class="relative flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                <div class="max-w-xl">
                    <p class="text-xs font-semibold uppercase tracking-wider text-slate-300">Per-seat savings</p>
                    <h3 class="mt-2 text-xl font-bold tracking-tight text-white sm:text-2xl">See your monthly savings instantly</h3>
                    <p class="mt-2 text-sm leading-relaxed text-slate-300">
                        Legacy tools often land around <span class="font-semibold text-slate-100">$100/agent/mo</span>. Helpefi stays on admin-defined flat tiers.
                    </p>
                </div>

                <div class="shrink-0">
                    <div class="rounded-2xl border border-blue-500/20 bg-gradient-to-br from-blue-500/15 to-indigo-500/10 px-5 py-4 text-center shadow-lg shadow-blue-900/20">
                        <p class="text-[11px] font-semibold uppercase tracking-wider text-blue-200/90">Estimated savings</p>
                        <p class="mt-1 text-4xl font-extrabold tracking-tight text-white sm:text-5xl" dir="ltr">
                            {{ formatUsd(savings) }}
                        </p>
                        <p class="mt-1 text-xs font-semibold text-blue-200/90">per month</p>
                    </div>
                </div>
            </div>

            <div class="relative mt-6 rounded-2xl border border-white/10 bg-slate-950/40 p-5 sm:p-6">
                <div class="flex items-center justify-between gap-3">
                    <p class="text-sm font-semibold text-slate-200">Agents</p>
                    <p class="text-sm font-bold text-white" dir="ltr">{{ agents }}</p>
                </div>

                <input
                    v-model="agents"
                    type="range"
                    min="3"
                    max="100"
                    step="1"
                    class="mt-4 w-full accent-blue-500"
                >

                <div class="mt-5 grid gap-3 sm:grid-cols-3">
                    <div class="rounded-2xl border border-white/10 bg-white/5 p-4">
                        <p class="text-xs font-semibold uppercase tracking-wider text-slate-400">Legacy estimate</p>
                        <p class="mt-1 text-xl font-extrabold text-white" dir="ltr">{{ formatUsd(competitorPrice) }}<span class="text-sm font-semibold text-slate-400">/mo</span></p>
                        <p class="mt-1 text-xs text-slate-400">{{ formatUsd(competitorPerSeat) }}/agent</p>
                    </div>
                    <div class="rounded-2xl border border-white/10 bg-white/5 p-4">
                        <p class="text-xs font-semibold uppercase tracking-wider text-slate-400">Helpefi tier</p>
                        <p class="mt-1 text-xl font-extrabold text-white" dir="ltr">{{ formatUsd(helpefiPrice) }}<span class="text-sm font-semibold text-slate-400">/mo</span></p>
                        <p class="mt-1 text-xs text-slate-400">
                            <span v-if="tierPlan?.name">
                                {{ tierPlan.name }}
                                <span v-if="Number.isFinite(agentsLimit(tierPlan)) && agentsLimit(tierPlan) !== Infinity"> (up to {{ agentsLimit(tierPlan) }} agents)</span>
                                <span v-else> (flat)</span>
                            </span>
                            <span v-else>
                                Starter / Professional / Enterprise
                            </span>
                        </p>
                    </div>
                    <div class="rounded-2xl border border-blue-500/20 bg-gradient-to-br from-blue-600/20 to-indigo-500/10 p-4">
                        <p class="text-xs font-semibold uppercase tracking-wider text-blue-200/90">You save</p>
                        <p class="mt-1 text-xl font-extrabold text-white" dir="ltr">{{ formatUsd(savings) }}<span class="text-sm font-semibold text-blue-200/90">/mo</span></p>
                        <p class="mt-1 text-xs text-blue-200/80">Based on agent count and admin tiers</p>
                    </div>
                </div>
            </div>
        </div>
    </section>
</template>

