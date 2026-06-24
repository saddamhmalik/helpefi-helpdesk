<script setup>
import { Link } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { csrfHeaders } from '../support/csrf.js';

const props = defineProps({
    enabled: { type: Boolean, default: true },
    brand: { type: String, default: '' },
});

const { t, tm } = useI18n();

const open = ref(false);
const query = ref('');
const loading = ref(false);
const error = ref('');
const messages = ref([]);
const leadEmail = ref('');
const leadName = ref('');
const leadConsent = ref(false);
const leadLoading = ref(false);
const leadError = ref('');
const leadCaptured = ref(false);
const leadDismissed = ref(false);

const platformName = computed(() => props.brand || t('app.name'));

const userMessageCount = computed(() => messages.value.filter((message) => message.role === 'user').length);

const showLeadCapture = computed(() => (
    props.enabled
    && userMessageCount.value >= 2
    && !leadCaptured.value
    && !leadDismissed.value
));

const samplePrompts = computed(() => {
    const items = tm('central.home.ai_demo.sample_prompts');

    return Array.isArray(items) ? items.slice(0, 3) : [];
});

const chatTranscript = computed(() => messages.value.map((message) => ({
    role: message.role,
    text: message.text,
})));

const ask = async (text) => {
    const question = (text ?? query.value).trim();

    if (!question || loading.value || !props.enabled) {
        return;
    }

    query.value = '';
    loading.value = true;
    error.value = '';
    messages.value.push({ role: 'user', text: question });

    try {
        const response = await fetch('/api/marketing/ai-demo', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                ...csrfHeaders(),
            },
            body: JSON.stringify({ query: question }),
        });

        const data = await response.json();

        if (!response.ok) {
            error.value = data.message || data.errors?.query?.[0] || t('central.home.ai_demo.error_generic');
            return;
        }

        messages.value.push({
            role: 'assistant',
            text: data.answer,
            articles: data.articles ?? [],
        });
    } catch {
        error.value = t('central.home.ai_demo.error_network');
    } finally {
        loading.value = false;
    }
};

const submitLead = async () => {
    if (leadLoading.value || !leadConsent.value) {
        return;
    }

    leadLoading.value = true;
    leadError.value = '';

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
                email: leadEmail.value,
                name: leadName.value || undefined,
                source: 'chatbot',
                intent: 'chat',
                marketing_consent: 1,
                page_url: window.location.href,
                chat_transcript: chatTranscript.value.slice(-8),
            }),
        });

        const data = await response.json();

        if (!response.ok) {
            leadError.value = data.errors?.email?.[0]
                || data.errors?.marketing_consent?.[0]
                || data.errors?.rate_limit?.[0]
                || t('central.marketing_leads.capture_error');

            return;
        }

        leadCaptured.value = true;
    } catch {
        leadError.value = t('central.marketing_leads.capture_error');
    } finally {
        leadLoading.value = false;
    }
};

const toggle = () => {
    open.value = !open.value;
    error.value = '';
};
</script>

<template>
    <div v-if="enabled" class="pointer-events-none fixed bottom-5 right-5 z-50 flex flex-col items-end gap-2">
        <Transition
            enter-active-class="transition duration-200 ease-out"
            enter-from-class="translate-y-3 opacity-0 scale-95"
            enter-to-class="translate-y-0 opacity-100 scale-100"
            leave-active-class="transition duration-150 ease-in"
            leave-from-class="translate-y-0 opacity-100 scale-100"
            leave-to-class="translate-y-3 opacity-0 scale-95"
        >
            <div
                v-if="open"
                class="pointer-events-auto flex w-[360px] max-w-[calc(100vw-2rem)] flex-col overflow-hidden rounded-2xl border border-violet-200/80 bg-white shadow-2xl shadow-violet-900/15 ring-1 ring-violet-100 dark:border-violet-900/60 dark:bg-slate-900 dark:ring-violet-900/40"
            >
                <div class="flex items-start justify-between gap-3 border-b border-violet-100 bg-gradient-to-r from-violet-600 to-indigo-600 px-4 py-3 text-white dark:border-violet-900/60">
                    <div class="min-w-0">
                        <p class="text-sm font-semibold">{{ $t('central.home.marketing_bot.title') }}</p>
                        <p class="text-[11px] text-violet-100">{{ $t('central.home.marketing_bot.subtitle', { brand: platformName }) }}</p>
                    </div>
                    <button
                        type="button"
                        class="shrink-0 rounded-lg p-1 text-violet-100 transition hover:bg-white/15 hover:text-white"
                        :aria-label="$t('central.home.marketing_bot.close')"
                        @click="open = false"
                    >
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <div class="max-h-[min(420px,60dvh)] flex-1 space-y-3 overflow-y-auto bg-slate-50 p-4 dark:bg-slate-950/80">
                    <div
                        v-if="!messages.length && !loading"
                        class="rounded-xl border border-dashed border-violet-200 bg-white px-4 py-5 text-center dark:border-violet-900/60 dark:bg-slate-900"
                    >
                        <p class="text-sm font-medium text-slate-700 dark:text-slate-300">{{ $t('central.home.ai_demo.empty_title') }}</p>
                        <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ $t('central.home.marketing_bot.empty_body') }}</p>
                        <div class="mt-4 flex flex-wrap justify-center gap-2">
                            <button
                                v-for="prompt in samplePrompts"
                                :key="prompt"
                                type="button"
                                class="rounded-full border border-violet-200 bg-violet-50 px-3 py-1.5 text-xs font-medium text-violet-700 transition hover:border-violet-300 hover:bg-violet-100 dark:border-violet-900/60 dark:bg-violet-950/40 dark:text-violet-300 dark:hover:bg-violet-950/60"
                                @click="ask(prompt)"
                            >
                                {{ prompt }}
                            </button>
                        </div>
                    </div>

                    <template v-for="(message, index) in messages" :key="index">
                        <div v-if="message.role === 'user'" class="flex justify-end">
                            <div class="max-w-[85%] rounded-2xl rounded-br-md bg-violet-600 px-3.5 py-2.5 text-sm text-white">
                                {{ message.text }}
                            </div>
                        </div>
                        <div v-else class="space-y-2">
                            <div class="flex justify-start">
                                <div class="max-w-[90%] whitespace-pre-wrap rounded-2xl rounded-bl-md border border-violet-100 bg-white px-3.5 py-2.5 text-sm leading-relaxed text-slate-800 shadow-sm dark:border-slate-800 dark:bg-slate-900 dark:text-slate-200">
                                    {{ message.text }}
                                </div>
                            </div>
                            <div
                                v-if="message.articles?.length"
                                class="rounded-xl border border-slate-200 bg-white p-3 dark:border-slate-800 dark:bg-slate-900"
                            >
                                <p class="text-[10px] font-semibold uppercase tracking-wider text-slate-400">{{ $t('central.home.ai_demo.sources') }}</p>
                                <ul class="mt-2 space-y-1">
                                    <li
                                        v-for="article in message.articles"
                                        :key="article.title"
                                        class="text-xs text-slate-600 dark:text-slate-400"
                                    >
                                        <span class="font-medium text-violet-700 dark:text-violet-300">{{ article.title }}</span>
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </template>

                    <div v-if="loading" class="flex items-center gap-2 text-sm text-slate-500 dark:text-slate-400">
                        <svg class="h-4 w-4 animate-spin text-violet-600" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
                        </svg>
                        {{ $t('central.home.ai_demo.loading') }}
                    </div>

                    <p v-if="error" class="rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700 dark:border-red-900/60 dark:bg-red-950/40 dark:text-red-300">
                        {{ error }}
                    </p>
                </div>

                <div
                    v-if="showLeadCapture"
                    class="border-t border-violet-100 bg-violet-50/80 p-3 dark:border-violet-900/60 dark:bg-violet-950/30"
                >
                    <p class="text-xs font-semibold text-violet-900 dark:text-violet-100">{{ $t('central.marketing_leads.chat_title') }}</p>
                    <p class="mt-1 text-[11px] text-violet-800 dark:text-violet-200">{{ $t('central.marketing_leads.chat_subtitle') }}</p>
                    <form class="mt-3 space-y-2" @submit.prevent="submitLead">
                        <input
                            v-model="leadEmail"
                            type="email"
                            required
                            :placeholder="$t('central.marketing_leads.email_placeholder')"
                            class="w-full rounded-lg border border-violet-200 bg-white px-3 py-2 text-sm text-slate-900 focus:border-violet-400 focus:outline-none focus:ring-2 focus:ring-violet-400/20 dark:border-violet-900/60 dark:bg-slate-900 dark:text-slate-100"
                        />
                        <input
                            v-model="leadName"
                            type="text"
                            :placeholder="$t('central.marketing_leads.name_label')"
                            class="w-full rounded-lg border border-violet-200 bg-white px-3 py-2 text-sm text-slate-900 focus:border-violet-400 focus:outline-none focus:ring-2 focus:ring-violet-400/20 dark:border-violet-900/60 dark:bg-slate-900 dark:text-slate-100"
                        />
                        <label class="flex items-start gap-2 text-[11px] text-violet-900 dark:text-violet-200">
                            <input v-model="leadConsent" type="checkbox" required class="mt-0.5 rounded border-violet-300 text-violet-600 focus:ring-violet-500" />
                            <span>{{ $t('central.marketing_leads.consent_short') }}</span>
                        </label>
                        <p v-if="leadError" class="text-[11px] text-red-600 dark:text-red-400">{{ leadError }}</p>
                        <div class="flex gap-2">
                            <button
                                type="submit"
                                class="flex-1 rounded-lg bg-violet-600 px-3 py-2 text-xs font-semibold text-white transition hover:bg-violet-700 disabled:opacity-60"
                                :disabled="leadLoading || !leadConsent"
                            >
                                {{ leadLoading ? $t('central.marketing_leads.submitting') : $t('central.marketing_leads.chat_submit') }}
                            </button>
                            <button
                                type="button"
                                class="rounded-lg px-3 py-2 text-xs font-medium text-violet-800 transition hover:bg-violet-100 dark:text-violet-200 dark:hover:bg-violet-900/40"
                                @click="leadDismissed = true"
                            >
                                {{ $t('central.marketing_leads.chat_skip') }}
                            </button>
                        </div>
                    </form>
                </div>

                <div
                    v-else-if="leadCaptured"
                    class="border-t border-emerald-100 bg-emerald-50 px-4 py-3 text-xs text-emerald-800 dark:border-emerald-900/60 dark:bg-emerald-950/30 dark:text-emerald-200"
                >
                    {{ $t('central.marketing_leads.chat_success') }}
                </div>

                <div class="border-t border-violet-100 bg-white p-3 dark:border-slate-800 dark:bg-slate-900">
                    <form class="flex gap-2" @submit.prevent="ask()">
                        <input
                            v-model="query"
                            type="text"
                            maxlength="500"
                            :disabled="loading"
                            :placeholder="$t('central.home.ai_demo.placeholder')"
                            class="min-w-0 flex-1 rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm text-slate-900 placeholder:text-slate-400 focus:border-violet-400 focus:outline-none focus:ring-2 focus:ring-violet-400/20 disabled:opacity-60 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-100"
                        />
                        <button
                            type="submit"
                            :disabled="loading || !query.trim()"
                            class="shrink-0 rounded-xl bg-gradient-to-r from-violet-600 to-indigo-600 px-4 py-2.5 text-sm font-semibold text-white shadow-md shadow-violet-600/25 transition hover:from-violet-500 hover:to-indigo-500 disabled:cursor-not-allowed disabled:opacity-50"
                        >
                            {{ $t('central.home.ai_demo.ask') }}
                        </button>
                    </form>
                    <p class="mt-2 text-center text-[11px] text-slate-500 dark:text-slate-400">
                        {{ $t('central.home.marketing_bot.trial_hint') }}
                        <Link href="/register" class="font-medium text-violet-600 hover:text-violet-500 dark:text-violet-400">
                            {{ $t('layouts.central.start_free_trial') }}
                        </Link>
                    </p>
                </div>
            </div>
        </Transition>

        <button
            type="button"
            class="pointer-events-auto group flex flex-col items-end gap-2"
            :aria-expanded="open"
            :aria-label="$t('central.home.marketing_bot.launcher_label')"
            @click="toggle"
        >
            <span
                class="rounded-full bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 shadow-lg ring-1 ring-slate-200 transition group-hover:scale-105 dark:bg-slate-800 dark:text-slate-200 dark:ring-slate-700"
                :class="open ? 'opacity-0' : 'animate-[bounce_2s_ease-in-out_infinite]'"
            >
                {{ $t('central.home.marketing_bot.launcher_label') }}
            </span>
            <span
                class="flex h-14 w-14 items-center justify-center rounded-full bg-gradient-to-br from-violet-600 to-indigo-600 text-white shadow-lg shadow-violet-600/35 transition hover:from-violet-500 hover:to-indigo-500 hover:shadow-violet-600/45"
                :class="open ? '' : 'animate-[bounce_2s_ease-in-out_infinite]'"
            >
                <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        stroke-width="2"
                        d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"
                    />
                </svg>
            </span>
        </button>
    </div>
</template>
