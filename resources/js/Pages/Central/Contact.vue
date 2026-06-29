<script setup>
import { Link, useForm, usePage } from '@inertiajs/vue3';
import { computed, onMounted, ref, watch } from 'vue';
import CentralLayout from '../../Layouts/CentralLayout.vue';
import ContactTurnstile from '../../Components/Central/ContactTurnstile.vue';
import CentralBreadcrumbs from '../../Components/Central/CentralBreadcrumbs.vue';
import { useClipboard } from '../../composables/useClipboard.js';

import { formatMarketingTemplate } from '../../composables/useMarketingEnglish.js';

const props = defineProps({
    brand: { type: String, default: 'helpefi' },
    trialDays: { type: Number, default: 14 },
    contactEmail: { type: String, default: '' },
    content: { type: Object, default: () => ({}) },
    socialLinks: { type: Array, default: () => [] },
    turnstileSiteKey: { type: String, default: null },
});

const pageCopy = (key, extra = {}) => formatMarketingTemplate(props.content[key] ?? key, {
    days: props.trialDays,
    contactEmail: props.contactEmail || 'hello@helpefi.com',
    email: extra.email ?? props.contactEmail,
    ...extra,
});
const page = usePage();
const { copied, copy: copyToClipboard } = useClipboard();

const platformName = computed(() => props.brand);
const submitted = computed(() => Boolean(page.props.flash?.contactSubmitted));
const replyEmail = computed(() => page.props.flash?.contactReplyEmail || form.email || props.contactEmail);

const topics = [
    { key: 'sales', labelKey: 'topic_sales' },
    { key: 'support', labelKey: 'topic_support' },
    { key: 'partnership', labelKey: 'topic_partnership' },
    { key: 'enterprise', labelKey: 'topic_enterprise' },
    { key: 'other', labelKey: 'topic_other' },
];

const form = useForm({
    name: '',
    email: '',
    company: '',
    topic: 'sales',
    message: '',
    marketing_consent: false,
    website: '',
    cf_turnstile_response: '',
});

const turnstileRef = ref(null);
const turnstileToken = ref('');
const showSuccess = ref(false);

const turnstileRequired = computed(() => Boolean(props.turnstileSiteKey));

watch(turnstileToken, (token) => {
    form.cf_turnstile_response = token;
});

const canSubmit = computed(() => {
    if (form.processing) {
        return false;
    }

    if (turnstileRequired.value && ! form.cf_turnstile_response) {
        return false;
    }

    return true;
});

onMounted(() => {
    const params = new URLSearchParams(window.location.search);
    const topic = params.get('topic');

    if (topic && topics.some((item) => item.key === topic)) {
        form.topic = topic;
    }

    if (submitted.value) {
        showSuccess.value = true;
    }
});

watch(submitted, (value) => {
    if (value) {
        showSuccess.value = true;
        form.reset('name', 'email', 'company', 'message', 'website');
        form.topic = 'sales';
    }
});

const selectTopic = (topic) => {
    form.topic = topic;
};

const copyEmail = async () => {
    if (!props.contactEmail) {
        return;
    }

    await copyToClipboard(props.contactEmail);
};

const submit = () => {
    form.post('/contact', {
        preserveScroll: true,
        onSuccess: () => {
            showSuccess.value = true;
            turnstileRef.value?.reset?.();
        },
    });
};

const fieldError = (field) => form.errors[field] ?? null;
const rateLimitError = computed(() => form.errors.rate_limit ?? page.props.errors?.rate_limit ?? null);
const turnstileError = computed(() => form.errors.cf_turnstile_response ?? null);

const sidebarLinks = computed(() => [
    { href: '/register', label: pageCopy('link_trial') },
    { href: '/pricing', label: pageCopy('link_pricing') },
    { href: '/login', label: pageCopy('link_login') },
]);
</script>

<template>
    <CentralLayout :brand="brand" :trial-days="trialDays" :social-links="socialLinks">
        <section class="relative overflow-hidden bg-slate-950 py-14 text-white sm:py-20">
            <div class="pointer-events-none absolute inset-0 bg-[radial-gradient(circle_at_top_right,rgba(59,130,246,0.18),transparent_45%)]" />
            <div class="relative mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <CentralBreadcrumbs />

                <div class="max-w-2xl">
                    <h1 class="text-3xl font-extrabold tracking-tight sm:text-5xl">{{ pageCopy('hero_title') }}</h1>
                    <p class="mt-5 text-lg leading-relaxed text-slate-300">
                        {{ pageCopy('hero_subtitle') }}
                    </p>
                </div>
            </div>
        </section>

        <section class="bg-slate-50 py-12 dark:bg-slate-950 sm:py-16">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div
                    v-if="showSuccess"
                    class="mb-8 rounded-2xl border border-emerald-200 bg-emerald-50 px-5 py-4 dark:border-emerald-900/60 dark:bg-emerald-950/40"
                    role="status"
                >
                    <p class="text-sm font-semibold text-emerald-900 dark:text-emerald-100">{{ pageCopy('success_title') }}</p>
                    <p class="mt-1 text-sm text-emerald-800 dark:text-emerald-200">
                        {{ pageCopy('success_body', { email: replyEmail }) }}
                    </p>
                </div>

                <div class="grid gap-8 lg:grid-cols-5 lg:gap-10">
                    <div class="lg:col-span-3">
                        <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900 sm:p-8">
                            <h2 class="text-xl font-bold text-slate-900 dark:text-slate-100">{{ pageCopy('form_title') }}</h2>
                            <p class="mt-2 text-sm text-slate-600 dark:text-slate-400">{{ pageCopy('form_subtitle') }}</p>

                            <div class="mt-6 flex flex-wrap gap-2" role="group" :aria-label="pageCopy('topic_group_label')">
                                <button
                                    v-for="topic in topics"
                                    :key="topic.key"
                                    type="button"
                                    class="rounded-full border px-3 py-1.5 text-sm font-medium transition"
                                    :class="form.topic === topic.key
                                        ? 'border-blue-600 bg-blue-600 text-white'
                                        : 'border-slate-200 bg-white text-slate-700 hover:border-blue-300 hover:text-blue-700 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-200 dark:hover:border-blue-500'"
                                    @click="selectTopic(topic.key)"
                                >
                                    {{ pageCopy(topic.labelKey) }}
                                </button>
                            </div>

                            <form class="mt-8 space-y-5" @submit.prevent="submit">
                                <div
                                    v-if="rateLimitError"
                                    class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900 dark:border-amber-900/60 dark:bg-amber-950/40 dark:text-amber-200"
                                    role="alert"
                                >
                                    {{ rateLimitError }}
                                </div>

                                <input v-model="form.website" type="text" tabindex="-1" autocomplete="off" class="hidden" aria-hidden="true">

                                <div class="grid gap-5 sm:grid-cols-2">
                                    <div>
                                        <label for="contact-name" class="mb-1.5 block text-sm font-medium text-slate-700 dark:text-slate-300">
                                            {{ pageCopy('name_label') }}
                                        </label>
                                        <input
                                            id="contact-name"
                                            v-model="form.name"
                                            type="text"
                                            required
                                            autocomplete="name"
                                            class="w-full rounded-xl border px-4 py-2.5 text-sm transition focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20 dark:bg-slate-950"
                                            :class="fieldError('name') ? 'border-red-300 dark:border-red-800' : 'border-slate-200 dark:border-slate-700'"
                                        />
                                        <p v-if="fieldError('name')" class="mt-1 text-xs text-red-600 dark:text-red-400">{{ fieldError('name') }}</p>
                                    </div>

                                    <div>
                                        <label for="contact-email" class="mb-1.5 block text-sm font-medium text-slate-700 dark:text-slate-300">
                                            {{ pageCopy('email_label') }}
                                        </label>
                                        <input
                                            id="contact-email"
                                            v-model="form.email"
                                            type="email"
                                            required
                                            autocomplete="email"
                                            class="w-full rounded-xl border px-4 py-2.5 text-sm transition focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20 dark:bg-slate-950"
                                            :class="fieldError('email') ? 'border-red-300 dark:border-red-800' : 'border-slate-200 dark:border-slate-700'"
                                        />
                                        <p v-if="fieldError('email')" class="mt-1 text-xs text-red-600 dark:text-red-400">{{ fieldError('email') }}</p>
                                    </div>
                                </div>

                                <div>
                                    <label for="contact-company" class="mb-1.5 block text-sm font-medium text-slate-700 dark:text-slate-300">
                                        {{ pageCopy('company_label') }}
                                    </label>
                                    <input
                                        id="contact-company"
                                        v-model="form.company"
                                        type="text"
                                        autocomplete="organization"
                                        class="w-full rounded-xl border border-slate-200 px-4 py-2.5 text-sm transition focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20 dark:border-slate-700 dark:bg-slate-950"
                                    />
                                </div>

                                <div>
                                    <label for="contact-message" class="mb-1.5 block text-sm font-medium text-slate-700 dark:text-slate-300">
                                        {{ pageCopy('message_label') }}
                                    </label>
                                    <textarea
                                        id="contact-message"
                                        v-model="form.message"
                                        required
                                        rows="6"
                                        class="w-full rounded-xl border px-4 py-3 text-sm transition focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20 dark:bg-slate-950"
                                        :class="fieldError('message') ? 'border-red-300 dark:border-red-800' : 'border-slate-200 dark:border-slate-700'"
                                        :placeholder="pageCopy('message_placeholder')"
                                    />
                                    <p v-if="fieldError('message')" class="mt-1 text-xs text-red-600 dark:text-red-400">{{ fieldError('message') }}</p>
                                </div>

                                <label class="flex items-start gap-3 text-sm text-slate-600 dark:text-slate-400">
                                    <input v-model="form.marketing_consent" type="checkbox" class="mt-1 rounded border-slate-300 text-blue-600 focus:ring-blue-500" />
                                    <span>{{ page.props.marketingWidgets?.leads?.consent_short ?? pageCopy('consent_short') }}</span>
                                </label>

                                <div v-if="turnstileSiteKey">
                                    <ContactTurnstile
                                        ref="turnstileRef"
                                        :site-key="turnstileSiteKey"
                                        @token="turnstileToken = $event"
                                    />
                                    <p v-if="turnstileError" class="mt-2 text-xs text-red-600 dark:text-red-400" role="alert">
                                        {{ turnstileError }}
                                    </p>
                                </div>

                                <div class="flex flex-wrap items-center gap-3 pt-2">
                                    <button
                                        type="submit"
                                        class="inline-flex items-center justify-center rounded-xl bg-blue-600 px-6 py-3 text-sm font-semibold text-white transition hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-60"
                                        :disabled="!canSubmit"
                                    >
                                        {{ form.processing ? pageCopy('submitting') : pageCopy('submit') }}
                                    </button>
                                    <a
                                        v-if="contactEmail"
                                        :href="`mailto:${contactEmail}`"
                                        class="text-sm font-medium text-slate-600 underline-offset-2 hover:text-blue-600 hover:underline dark:text-slate-400 dark:hover:text-blue-400"
                                    >
                                        {{ pageCopy('mailto_fallback') }}
                                    </a>
                                </div>
                            </form>
                        </div>
                    </div>

                    <aside class="space-y-5 lg:col-span-2">
                        <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                            <h3 class="text-sm font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                                {{ pageCopy('sidebar_email_title') }}
                            </h3>
                            <div v-if="contactEmail" class="mt-4 flex items-center gap-2">
                                <a :href="`mailto:${contactEmail}`" class="min-w-0 flex-1 truncate text-base font-semibold text-blue-600 hover:text-blue-700 dark:text-blue-400">
                                    {{ contactEmail }}
                                </a>
                                <button
                                    type="button"
                                    class="shrink-0 rounded-lg border border-slate-200 px-3 py-1.5 text-xs font-medium text-slate-700 transition hover:bg-slate-50 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-800"
                                    @click="copyEmail"
                                >
                                    {{ copied ? pageCopy('sidebar_copied') : pageCopy('sidebar_copy') }}
                                </button>
                            </div>
                            <p class="mt-4 text-sm leading-relaxed text-slate-600 dark:text-slate-400">
                                {{ pageCopy('sidebar_response_body') }}
                            </p>
                        </div>

                        <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                            <h3 class="text-sm font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                                {{ pageCopy('sidebar_links_title') }}
                            </h3>
                            <ul class="mt-4 space-y-2">
                                <li v-for="link in sidebarLinks" :key="link.href">
                                    <Link :href="link.href" class="text-sm font-medium text-blue-600 hover:text-blue-700 dark:text-blue-400 dark:hover:text-blue-300">
                                        {{ link.label }} →
                                    </Link>
                                </li>
                            </ul>
                        </div>

                        <div class="rounded-2xl border border-dashed border-slate-300 bg-slate-100/70 p-6 dark:border-slate-700 dark:bg-slate-900/50">
                            <h3 class="text-sm font-semibold text-slate-900 dark:text-slate-100">{{ pageCopy('existing_customer_title') }}</h3>
                            <p class="mt-2 text-sm leading-relaxed text-slate-600 dark:text-slate-400">
                                {{ pageCopy('existing_customer_body') }}
                            </p>
                        </div>
                    </aside>
                </div>
            </div>
        </section>

        <section class="border-t border-slate-200 bg-white py-14 dark:border-slate-800 dark:bg-slate-900">
            <div class="mx-auto max-w-4xl px-4 text-center">
                <h2 class="text-2xl font-bold text-slate-900 dark:text-slate-100">{{ pageCopy('cta_title') }}</h2>
                <p class="mt-3 text-base text-slate-600 dark:text-slate-400">{{ pageCopy('cta_body') }}</p>
                <Link href="/register" class="mt-8 inline-flex rounded-2xl bg-slate-900 px-8 py-3.5 text-sm font-bold text-white transition hover:bg-slate-800 dark:bg-white dark:text-slate-900 dark:hover:bg-slate-100">
                    {{ pageCopy('link_trial') }}
                </Link>
            </div>
        </section>
    </CentralLayout>
</template>
