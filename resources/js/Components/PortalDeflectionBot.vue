<script setup>
import { Link } from '@inertiajs/vue3';
import { onMounted, ref } from 'vue';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();

const open = ref(false);
const enabled = ref(false);
const loading = ref(false);
const query = ref('');
const answer = ref('');
const articles = ref([]);
const sessionId = ref(null);
const mode = ref('ask');
const escalateForm = ref({
    name: '',
    email: '',
    subject: '',
    description: '',
});
const statusMessage = ref('');

onMounted(async () => {
    try {
        const response = await fetch('/api/v1/deflection/config?channel=portal', {
            headers: { Accept: 'application/json' },
        });
        const data = await response.json();
        enabled.value = !!data.enabled;
    } catch {
        enabled.value = false;
    }
});

const ask = async () => {
    if (!query.value.trim()) {
        return;
    }

    loading.value = true;
    statusMessage.value = '';

    try {
        const response = await fetch('/api/v1/deflection/ask', {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                query: query.value,
                channel: 'portal',
                session_id: sessionId.value,
            }),
        });
        const data = await response.json();

        if (!response.ok) {
            throw new Error(data.message || t('components.deflection_no_answer'));
        }

        sessionId.value = data.session_id;
        answer.value = data.answer;
        articles.value = data.articles || [];
        mode.value = 'answer';
    } catch (error) {
        statusMessage.value = error.message || t('components.deflection_error');
    } finally {
        loading.value = false;
    }
};

const feedback = async (helpful) => {
    if (!sessionId.value) {
        return;
    }

    await fetch('/api/v1/deflection/feedback', {
        method: 'POST',
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            session_id: sessionId.value,
            channel: 'portal',
            helpful,
            article_id: articles.value[0]?.id ?? null,
        }),
    });

    if (helpful) {
        statusMessage.value = t('components.glad_that_helped');
        mode.value = 'done';
    } else {
        mode.value = 'escalate';
        escalateForm.value.subject = query.value.slice(0, 120);
        escalateForm.value.description = query.value;
    }
};

const escalate = async () => {
    loading.value = true;
    statusMessage.value = '';

    try {
        const response = await fetch('/api/v1/deflection/escalate', {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                session_id: sessionId.value,
                channel: 'portal',
                ...escalateForm.value,
            }),
        });
        const data = await response.json();

        if (!response.ok) {
            throw new Error(data.message || t('components.deflection_submit_error'));
        }

        statusMessage.value = t('components.deflection_ticket_created', { message: data.message, number: data.ticket_number });
        mode.value = 'done';
    } catch (error) {
        statusMessage.value = error.message || t('components.deflection_error');
    } finally {
        loading.value = false;
    }
};

const reset = () => {
    mode.value = 'ask';
    query.value = '';
    answer.value = '';
    articles.value = [];
    sessionId.value = null;
    statusMessage.value = '';
    escalateForm.value = { name: '', email: '', subject: '', description: '' };
};
</script>

<template>
    <div v-if="enabled">
        <button
            type="button"
            class="fixed bottom-5 right-5 z-50 flex h-14 w-14 items-center justify-center rounded-full bg-blue-600 text-2xl text-white shadow-lg hover:bg-blue-700"
            :aria-label="$t('components.open_help_bot')"
            @click="open = !open"
        >
            ?
        </button>

        <div
            v-if="open"
            class="fixed bottom-24 right-5 z-50 flex w-[360px] max-w-[calc(100vw-2rem)] flex-col overflow-hidden rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 shadow-2xl"
        >
            <div class="bg-blue-600 px-4 py-3 text-sm font-semibold text-white">{{ $t('components.ask_our_help_bot') }}</div>

            <div class="max-h-[420px] flex-1 overflow-y-auto p-4">
                <div v-if="mode === 'ask'" class="space-y-3">
                    <p class="text-sm text-slate-600 dark:text-slate-400">{{ $t('components.deflection_search_hint') }}</p>
                    <textarea
                        v-model="query"
                        rows="3"
                        class="agent-input w-full rounded-lg px-3 py-2 text-sm"
                        :placeholder="$t('components.deflection_placeholder')"
                    />
                    <button
                        type="button"
                        class="w-full rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700"
                        :disabled="loading"
                        @click="ask"
                    >
                        {{ loading ? $t('components.searching_ellipsis') : $t('components.ask') }}
                    </button>
                </div>

                <div v-else-if="mode === 'answer'" class="space-y-4">
                    <p class="whitespace-pre-wrap text-sm text-slate-800 dark:text-slate-200">{{ answer }}</p>

                    <div v-if="articles.length" class="space-y-2">
                        <p class="text-xs font-medium uppercase tracking-wide text-slate-500 dark:text-slate-400">{{ $t('components.related_articles') }}</p>
                        <Link
                            v-for="article in articles"
                            :key="article.id"
                            :href="article.url"
                            class="block rounded-lg border border-slate-200 dark:border-slate-800 px-3 py-2 text-sm text-blue-600 hover:bg-slate-50 dark:bg-slate-950 dark:hover:bg-slate-800 dark:text-blue-400"
                        >
                            {{ article.title }}
                        </Link>
                    </div>

                    <div class="flex gap-2">
                        <button type="button" class="flex-1 rounded-lg bg-emerald-600 px-3 py-2 text-sm text-white" @click="feedback(true)">{{ $t('components.helpful') }}</button>
                        <button type="button" class="flex-1 rounded-lg border border-slate-300 dark:border-slate-700 px-3 py-2 text-sm" @click="feedback(false)">{{ $t('components.need_more_help') }}</button>
                    </div>
                </div>

                <form v-else-if="mode === 'escalate'" class="space-y-3" @submit.prevent="escalate">
                    <p class="text-sm text-slate-600 dark:text-slate-400">{{ $t('components.submit_a_support_request_and_we_will_follow_up_by_email') }}</p>
                    <input v-model="escalateForm.name" type="text" required :placeholder="$t('components.your_name')" class="agent-input w-full rounded-lg px-3 py-2 text-sm" />
                    <input v-model="escalateForm.email" type="email" required :placeholder="$t('components.email')" class="agent-input w-full rounded-lg px-3 py-2 text-sm" />
                    <input v-model="escalateForm.subject" type="text" required :placeholder="$t('components.subject')" class="agent-input w-full rounded-lg px-3 py-2 text-sm" />
                    <textarea v-model="escalateForm.description" rows="3" required class="agent-input w-full rounded-lg px-3 py-2 text-sm" />
                    <button type="submit" class="w-full rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white" :disabled="loading">{{ $t('components.submit_request') }}</button>
                </form>

                <div v-else class="space-y-3 text-sm text-slate-700 dark:text-slate-300">
                    <p>{{ statusMessage || $t('components.thanks_help_bot') }}</p>
                    <button type="button" class="rounded-lg border border-slate-300 dark:border-slate-700 px-3 py-2 text-sm" @click="reset">{{ $t('components.ask_another_question') }}</button>
                </div>

                <p v-if="statusMessage && mode !== 'done'" class="mt-3 text-sm text-slate-500 dark:text-slate-400">{{ statusMessage }}</p>
            </div>
        </div>
    </div>
</template>
