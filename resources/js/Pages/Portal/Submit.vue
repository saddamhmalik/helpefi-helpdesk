<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import { onMounted, ref, watch } from 'vue';
import PortalLayout from '../../Layouts/PortalLayout.vue';
import AppRichTextEditor from '../../Components/AppRichTextEditor.vue';
import { usePortalRoutes } from '../../composables/usePortalRoutes.js';

const props = defineProps({
    customer: Object,
    kbDeflectionEnabled: Boolean,
    ticketFields: Array,
});

const { portalPath, portalApiPath } = usePortalRoutes();

const form = useForm({
    name: '',
    email: '',
    subject: '',
    description: '',
    kb_session_id: null,
    custom_fields: {},
});

const suggestions = ref([]);
const kbSessionId = ref(null);
const loadingSuggestions = ref(false);
const deflected = ref(false);
const showSuggestions = ref(false);
let suggestTimer = null;

onMounted(() => {
    if (props.customer) {
        form.name = props.customer.name;
        form.email = props.customer.email;
    }
});

const stripHtml = (value) => value.replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ').trim();

const fetchSuggestions = async () => {
    if (!props.kbDeflectionEnabled) {
        return;
    }

    const subject = form.subject.trim();
    const description = stripHtml(form.description || '');

    if (subject.length < 3 && description.length < 3) {
        suggestions.value = [];
        showSuggestions.value = false;
        return;
    }

    loadingSuggestions.value = true;

    try {
        const response = await fetch(portalApiPath('/kb-suggest'), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify({
                subject,
                description,
                session_id: kbSessionId.value,
            }),
        });

        if (!response.ok) {
            return;
        }

        const data = await response.json();
        kbSessionId.value = data.session_id;
        form.kb_session_id = data.session_id;
        suggestions.value = data.articles || [];
        showSuggestions.value = suggestions.value.length > 0;
    } finally {
        loadingSuggestions.value = false;
    }
};

const scheduleSuggestions = () => {
    clearTimeout(suggestTimer);
    suggestTimer = setTimeout(fetchSuggestions, 400);
};

watch(() => form.subject, scheduleSuggestions);
watch(() => form.description, scheduleSuggestions);

const queryText = () => `${form.subject.trim()} ${stripHtml(form.description || '')}`.trim();

const postKbEvent = async (path, body = {}) => {
    if (!kbSessionId.value) {
        return;
    }

    await fetch(portalApiPath(`/${path}`), {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
        },
        body: JSON.stringify({
            session_id: kbSessionId.value,
            query: queryText(),
            ...body,
        }),
    });
};

const openArticle = async (article) => {
    await postKbEvent('kb-article-click', { article_id: article.id });
    window.open(article.url, '_blank', 'noopener');
};

const markResolved = async (article = null) => {
    await postKbEvent('kb-deflect', article ? { article_id: article.id } : {});
    deflected.value = true;
    showSuggestions.value = false;
};

const submitAnyway = async () => {
    if (showSuggestions.value && kbSessionId.value) {
        await postKbEvent('kb-continue');
    }

    form.post(portalPath('/submit'));
};
</script>

<template>
    <Head title="Submit request" />
    <PortalLayout>
        <div class="mx-auto max-w-xl">
            <Link :href="portalPath()" class="text-sm text-blue-600 hover:text-blue-700">← Help Center</Link>
            <h1 class="mt-2 text-2xl font-bold text-slate-900">Submit a request</h1>
            <p class="mt-1 text-sm text-slate-600">
                {{ customer ? 'Submitting as ' + customer.email : 'We\'ll create a ticket you can track by email or after signing in.' }}
            </p>

            <div
                v-if="deflected"
                class="mt-6 rounded-xl border border-emerald-200 bg-emerald-50 p-6 text-emerald-900"
            >
                <h2 class="text-lg font-semibold">Glad we could help!</h2>
                <p class="mt-2 text-sm">Your issue appears resolved. No ticket was created.</p>
                <Link :href="portalPath()" class="mt-4 inline-block text-sm font-medium text-emerald-700 hover:text-emerald-800">
                    Back to Help Center
                </Link>
            </div>

            <form
                v-else
                class="mt-6 space-y-4 rounded-xl border border-slate-200 bg-white p-6 shadow-sm"
                @submit.prevent="submitAnyway"
            >
                <template v-if="!customer">
                    <div>
                        <label class="mb-1 block text-sm font-medium text-slate-700">Name</label>
                        <input v-model="form.name" type="text" required class="w-full rounded-lg border border-slate-300 px-3 py-2" />
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-slate-700">Email</label>
                        <input v-model="form.email" type="email" required class="w-full rounded-lg border border-slate-300 px-3 py-2" />
                    </div>
                </template>
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">Subject</label>
                    <input v-model="form.subject" type="text" required class="w-full rounded-lg border border-slate-300 px-3 py-2" />
                </div>
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-slate-700">Description</label>
                    <AppRichTextEditor
                        v-model="form.description"
                        form
                        placeholder="Describe your issue in as much detail as you can"
                    />
                </div>

                <div v-for="field in ticketFields" :key="field.name" class="space-y-1">
                    <label class="block text-sm font-medium text-slate-700">
                        {{ field.label }}<span v-if="field.required" class="text-red-500"> *</span>
                    </label>
                    <input
                        v-if="field.type !== 'textarea' && field.type !== 'select'"
                        v-model="form.custom_fields[field.name]"
                        :type="field.type === 'number' ? 'number' : field.type === 'email' ? 'email' : 'text'"
                        :required="field.required"
                        class="w-full rounded-lg border border-slate-300 px-3 py-2"
                    />
                    <textarea
                        v-else-if="field.type === 'textarea'"
                        v-model="form.custom_fields[field.name]"
                        :required="field.required"
                        rows="3"
                        class="w-full rounded-lg border border-slate-300 px-3 py-2"
                    />
                    <select
                        v-else
                        v-model="form.custom_fields[field.name]"
                        :required="field.required"
                        class="w-full rounded-lg border border-slate-300 px-3 py-2"
                    >
                        <option value="">Select…</option>
                        <option v-for="option in field.options" :key="option" :value="option">{{ option }}</option>
                    </select>
                </div>

                <div
                    v-if="kbDeflectionEnabled && (loadingSuggestions || showSuggestions)"
                    class="rounded-lg border border-blue-100 bg-blue-50 p-4"
                >
                    <p class="text-sm font-medium text-blue-900">These articles might help</p>
                    <p v-if="loadingSuggestions" class="mt-2 text-sm text-blue-700">Searching knowledge base…</p>
                    <ul v-else class="mt-3 space-y-2">
                        <li v-for="article in suggestions" :key="article.id">
                            <button
                                type="button"
                                class="w-full rounded-lg border border-blue-100 bg-white px-3 py-2 text-left hover:border-blue-200"
                                @click="openArticle(article)"
                            >
                                <span class="block text-sm font-medium text-slate-900">{{ article.title }}</span>
                                <span v-if="article.excerpt" class="mt-0.5 block text-xs text-slate-600 line-clamp-2">{{ article.excerpt }}</span>
                            </button>
                        </li>
                    </ul>
                    <div v-if="showSuggestions && !loadingSuggestions" class="mt-4 flex flex-wrap gap-2">
                        <button
                            type="button"
                            class="rounded-lg bg-emerald-600 px-3 py-2 text-sm font-medium text-white hover:bg-emerald-700"
                            @click="markResolved()"
                        >
                            This solved my issue
                        </button>
                        <button
                            type="submit"
                            class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50"
                            :disabled="form.processing"
                        >
                            Still need help — submit request
                        </button>
                    </div>
                </div>

                <button
                    v-if="!showSuggestions"
                    type="submit"
                    class="w-full rounded-lg bg-blue-600 px-4 py-2 font-medium text-white hover:bg-blue-700"
                    :disabled="form.processing"
                >
                    Submit
                </button>
            </form>
        </div>
    </PortalLayout>
</template>
