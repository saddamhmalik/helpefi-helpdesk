<script setup>
import { router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import { formInputClass } from '../composables/useFormControls.js';

const props = defineProps({
    ticketId: { type: Number, required: true },
    issues: { type: Array, default: () => [] },
});

const showLink = ref(false);

const form = useForm({
    provider: 'jira',
    reference: '',
});

const providerLabel = (provider) => {
    if (provider === 'jira') {
        return 'Jira';
    }

    if (provider === 'linear') {
        return 'Linear';
    }

    return provider;
};

const submit = () => {
    form.post(`/tickets/${props.ticketId}/external-issues`, {
        preserveScroll: true,
        onSuccess: () => {
            form.reset();
            form.provider = 'jira';
            showLink.value = false;
        },
    });
};

const createIssue = (provider) => {
    router.post(`/tickets/${props.ticketId}/external-issues`, {
        provider,
    }, {
        preserveScroll: true,
    });
};

const unlinkIssue = (issueId) => {
    router.delete(`/tickets/${props.ticketId}/external-issues/${issueId}`, {
        preserveScroll: true,
    });
};
</script>

<template>
    <section class="px-4 py-3">
        <div class="flex items-center justify-between gap-2">
            <div>
                <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-400">External issues</p>
                <p class="mt-0.5 text-xs text-slate-500">Create or link Jira and Linear issues for this ticket.</p>
            </div>
            <button
                type="button"
                class="rounded-md border border-slate-200 px-2.5 py-1 text-xs font-medium text-slate-700 transition hover:bg-slate-50"
                @click="showLink = !showLink"
            >
                {{ showLink ? 'Cancel' : 'Link' }}
            </button>
        </div>

        <div class="mt-3 flex flex-wrap gap-2">
            <button
                type="button"
                class="rounded-md border border-slate-200 px-2.5 py-1 text-xs font-medium text-slate-700 transition hover:bg-slate-50"
                @click="createIssue('jira')"
            >
                Create Jira issue
            </button>
            <button
                type="button"
                class="rounded-md border border-slate-200 px-2.5 py-1 text-xs font-medium text-slate-700 transition hover:bg-slate-50"
                @click="createIssue('linear')"
            >
                Create Linear issue
            </button>
        </div>

        <form v-if="showLink" class="mt-3 space-y-2 rounded-lg border border-slate-200 bg-slate-50/70 p-3" @submit.prevent="submit">
            <select v-model="form.provider" :class="formInputClass">
                <option value="jira">Jira</option>
                <option value="linear">Linear</option>
            </select>
            <input
                v-model="form.reference"
                type="text"
                required
                placeholder="Issue key or ID (e.g. PROJ-123)"
                :class="formInputClass"
            />
            <button
                type="submit"
                class="rounded-md bg-slate-900 px-3 py-1.5 text-xs font-medium text-white hover:bg-slate-800 disabled:opacity-50"
                :disabled="form.processing"
            >
                Link issue
            </button>
        </form>

        <ul v-if="issues.length" class="mt-3 space-y-2">
            <li
                v-for="issue in issues"
                :key="issue.id"
                class="rounded-lg border border-slate-200 bg-white px-3 py-2"
            >
                <div class="flex items-start justify-between gap-2">
                    <div class="min-w-0">
                        <a :href="issue.external_url" target="_blank" rel="noopener" class="text-sm font-medium text-blue-600 hover:text-blue-700">
                            {{ providerLabel(issue.provider) }} · {{ issue.external_key }}
                        </a>
                        <p v-if="issue.status" class="text-xs text-slate-500">Status: {{ issue.status }}</p>
                    </div>
                    <button
                        type="button"
                        class="shrink-0 rounded px-1.5 py-0.5 text-xs text-slate-400 hover:bg-slate-100 hover:text-red-600"
                        @click="unlinkIssue(issue.id)"
                    >
                        Unlink
                    </button>
                </div>
            </li>
        </ul>

        <p v-else class="mt-3 text-xs text-slate-500">No linked issues yet.</p>
    </section>
</template>
