<script setup>
import { Link, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { formatMarketingTemplate } from '../composables/useMarketingEnglish.js';
import { csrfHeaders } from '../support/csrf.js';

const props = defineProps({
    variant: { type: String, default: 'default' },
});

const page = usePage();
const leads = computed(() => page.props.marketingWidgets?.leads ?? {});
const leadText = (key, params = {}) => formatMarketingTemplate(leads.value[key] ?? key, params);

const email = ref('');
const name = ref('');
const company = ref('');
const consent = ref(false);
const loading = ref(false);
const error = ref('');
const submitted = ref(false);

const utmParams = computed(() => {
    const params = new URLSearchParams(window.location.search);

    return {
        utm_source: params.get('utm_source') || undefined,
        utm_medium: params.get('utm_medium') || undefined,
        utm_campaign: params.get('utm_campaign') || undefined,
    };
});

const submit = async () => {
    if (loading.value || submitted.value) {
        return;
    }

    error.value = '';
    loading.value = true;

    try {
        const response = await fetch('/api/marketing/leads', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                ...csrfHeaders(),
            },
            body: JSON.stringify({
                email: email.value,
                name: name.value || undefined,
                company: company.value || undefined,
                source: 'homepage',
                intent: 'demo',
                marketing_consent: consent.value ? 1 : 0,
                page_url: window.location.href,
                ...utmParams.value,
            }),
        });

        const data = await response.json();

        if (! response.ok) {
            error.value = data.message
                || data.errors?.email?.[0]
                || data.errors?.marketing_consent?.[0]
                || data.errors?.rate_limit?.[0]
                || leadText('capture_error');

            return;
        }

        submitted.value = true;
    } catch {
        error.value = leadText('capture_error');
    } finally {
        loading.value = false;
    }
};

const isDark = computed(() => props.variant === 'dark');
</script>

<template>
    <section
        class="relative overflow-hidden py-14 sm:py-16"
        :class="isDark ? 'bg-slate-950 text-white' : 'border-y border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900'"
    >
        <div class="pointer-events-none absolute inset-0" :class="isDark ? 'bg-[radial-gradient(circle_at_top_left,rgba(59,130,246,0.18),transparent_45%)]' : ''" />
        <div class="relative mx-auto max-w-5xl px-4 sm:px-6 lg:px-8">
            <div class="grid gap-8 lg:grid-cols-2 lg:items-center">
                <div>
                    <p class="text-sm font-semibold uppercase tracking-wide" :class="isDark ? 'text-blue-300' : 'text-blue-600'">
                        {{ leadText('home_eyebrow') }}
                    </p>
                    <h2 class="mt-2 text-2xl font-bold tracking-tight sm:text-3xl" :class="isDark ? 'text-white' : 'text-slate-900 dark:text-slate-100'">
                        {{ leadText('home_title') }}
                    </h2>
                    <p class="mt-3 text-sm leading-relaxed sm:text-base" :class="isDark ? 'text-slate-300' : 'text-slate-600 dark:text-slate-400'">
                        {{ leadText('home_subtitle') }}
                    </p>
                    <Link
                        href="/register"
                        class="mt-5 inline-flex text-sm font-semibold"
                        :class="isDark ? 'text-blue-300 hover:text-white' : 'text-blue-600 hover:text-blue-700 dark:text-blue-400'"
                    >
                        {{ leadText('home_trial_link') }} →
                    </Link>
                </div>

                <div
                    class="rounded-2xl border p-5 shadow-sm sm:p-6"
                    :class="isDark ? 'border-white/10 bg-white/5 backdrop-blur' : 'border-slate-200 bg-slate-50 dark:border-slate-800 dark:bg-slate-950/60'"
                >
                    <div v-if="submitted" class="py-4 text-center">
                        <p class="text-lg font-semibold" :class="isDark ? 'text-white' : 'text-slate-900 dark:text-slate-100'">
                            {{ leadText('home_success_title') }}
                        </p>
                        <p class="mt-2 text-sm" :class="isDark ? 'text-slate-300' : 'text-slate-600 dark:text-slate-400'">
                            {{ leadText('home_success_body') }}
                        </p>
                    </div>

                    <form v-else class="space-y-4" @submit.prevent="submit">
                        <div class="grid gap-4 sm:grid-cols-2">
                            <div class="sm:col-span-2">
                                <label class="mb-1.5 block text-sm font-medium" :class="isDark ? 'text-slate-200' : 'text-slate-700 dark:text-slate-300'">
                                    {{ leadText('email_label') }}
                                </label>
                                <input
                                    v-model="email"
                                    type="email"
                                    required
                                    class="w-full rounded-xl border px-3.5 py-2.5 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20"
                                    :class="isDark ? 'border-white/10 bg-slate-950/60 text-white placeholder:text-slate-500' : 'border-slate-200 bg-white text-slate-900 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100'"
                                    :placeholder="leadText('email_placeholder')"
                                />
                            </div>
                            <div>
                                <label class="mb-1.5 block text-sm font-medium" :class="isDark ? 'text-slate-200' : 'text-slate-700 dark:text-slate-300'">
                                    {{ leadText('name_label') }}
                                </label>
                                <input
                                    v-model="name"
                                    type="text"
                                    class="w-full rounded-xl border px-3.5 py-2.5 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20"
                                    :class="isDark ? 'border-white/10 bg-slate-950/60 text-white placeholder:text-slate-500' : 'border-slate-200 bg-white text-slate-900 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100'"
                                />
                            </div>
                            <div>
                                <label class="mb-1.5 block text-sm font-medium" :class="isDark ? 'text-slate-200' : 'text-slate-700 dark:text-slate-300'">
                                    {{ leadText('company_label') }}
                                </label>
                                <input
                                    v-model="company"
                                    type="text"
                                    class="w-full rounded-xl border px-3.5 py-2.5 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20"
                                    :class="isDark ? 'border-white/10 bg-slate-950/60 text-white placeholder:text-slate-500' : 'border-slate-200 bg-white text-slate-900 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100'"
                                />
                            </div>
                        </div>

                        <label class="flex items-start gap-3 text-sm" :class="isDark ? 'text-slate-300' : 'text-slate-600 dark:text-slate-400'">
                            <input v-model="consent" type="checkbox" required class="mt-1 rounded border-slate-300 text-blue-600 focus:ring-blue-500" />
                            <span>{{ leadText('consent_label') }}</span>
                        </label>

                        <p v-if="error" class="rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700 dark:border-red-900/60 dark:bg-red-950/40 dark:text-red-300">
                            {{ error }}
                        </p>

                        <button
                            type="submit"
                            class="inline-flex w-full items-center justify-center rounded-xl bg-blue-600 px-4 py-3 text-sm font-semibold text-white transition hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-60"
                            :disabled="loading || !consent"
                        >
                            {{ loading ? leadText('submitting') : leadText('home_submit') }}
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </section>
</template>
