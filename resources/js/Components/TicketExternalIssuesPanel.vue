<script setup>
import { router, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { formInputClass, formSelectClass } from '../composables/useFormControls.js';

const { t } = useI18n();

const props = defineProps({
    ticketId: { type: Number, required: true },
    issues: { type: Array, default: () => [] },
    issueProviders: { type: Array, default: () => [] },
});

const showLink = ref(false);
const syncing = ref(false);

const form = useForm({
    provider: 'jira',
    reference: '',
});

const hasJira = computed(() => props.issueProviders.includes('jira'));
const hasLinear = computed(() => props.issueProviders.includes('linear'));
const hasAnyProvider = computed(() => props.issueProviders.length > 0);

const hasMultipleProviders = computed(() => hasJira.value && hasLinear.value);

const openLinkForm = () => {
    showLink.value = !showLink.value;

    if (showLink.value) {
        form.provider = props.issueProviders[0] ?? 'jira';
    }
};

const ghostButtonClass = 'inline-flex items-center justify-center rounded-lg border border-slate-200 bg-white px-2.5 py-1.5 text-xs font-medium text-slate-700 transition hover:border-slate-300 hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-50 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 dark:hover:border-slate-600 dark:hover:bg-slate-800';
const actionButtonClass = 'inline-flex items-center justify-center gap-1.5 rounded-lg border px-2.5 py-1.5 text-xs font-medium transition disabled:cursor-not-allowed disabled:opacity-50';

const providerLabel = (provider) => {
    if (provider === 'jira') {
        return t('components.jira');
    }

    if (provider === 'linear') {
        return t('components.linear');
    }

    return provider;
};

const issueStatusClass = (status) => {
    const normalized = String(status ?? '').toLowerCase();

    if (['done', 'completed', 'closed', 'resolved', 'cancelled', 'canceled'].includes(normalized)) {
        return 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/50 dark:text-emerald-300';
    }

    if (['in progress', 'started', 'doing', 'active'].includes(normalized)) {
        return 'bg-blue-100 text-blue-800 dark:bg-blue-950/50 dark:text-blue-300';
    }

    if (['backlog', 'todo', 'to do', 'triage', 'open'].includes(normalized)) {
        return 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300';
    }

    return 'bg-violet-100 text-violet-800 dark:bg-violet-950/50 dark:text-violet-300';
};

const submit = () => {
    form.post(`/tickets/${props.ticketId}/external-issues`, {
        preserveScroll: true,
        onSuccess: () => {
            form.reset();
            form.provider = props.issueProviders[0] ?? 'jira';
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

const syncIssues = () => {
    syncing.value = true;

    router.post(`/tickets/${props.ticketId}/external-issues/sync`, {}, {
        preserveScroll: true,
        onFinish: () => {
            syncing.value = false;
        },
    });
};

const unlinkIssue = (issueId) => {
    router.delete(`/tickets/${props.ticketId}/external-issues/${issueId}`, {
        preserveScroll: true,
    });
};
</script>

<template>
    <section v-if="hasAnyProvider" class="px-4 py-3">
        <div>
            <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-400 dark:text-slate-500">
                {{ $t('components.external_issues') }}
            </p>
            <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">
                {{ $t('components.external_issues_description') }}
            </p>
        </div>

        <div class="mt-3 flex flex-wrap items-center gap-2">
                <button
                    v-if="hasJira"
                    type="button"
                    :class="[actionButtonClass, 'border-[#0052CC]/20 bg-[#0052CC]/5 text-[#0052CC] hover:border-[#0052CC]/30 hover:bg-[#0052CC]/10 dark:border-[#4C9AFF]/20 dark:bg-[#4C9AFF]/10 dark:text-[#4C9AFF] dark:hover:bg-[#4C9AFF]/15']"
                    @click="createIssue('jira')"
                >
                    <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                        <path d="M11.571 11.513H0a5.218 5.218 0 0 0 5.232 5.215h2.13v2.057A5.215 5.215 0 0 0 12.575 24V12.518a1.005 1.005 0 0 0-1.004-1.005zm5.723-5.756H5.736a5.215 5.215 0 0 0 5.215 5.214h2.129v2.057a5.217 5.217 0 0 0 5.215 5.214V6.758a1.001 1.001 0 0 0-1.001-1.001zM23.013 0H11.455a5.215 5.215 0 0 0 5.215 5.215v2.058a5.218 5.218 0 0 0 5.216 5.214V1.005A1.001 1.001 0 0 0 23.013 0Z" />
                    </svg>
                    {{ $t('components.create_jira_issue') }}
                </button>

                <button
                    v-if="hasLinear"
                    type="button"
                    :class="[actionButtonClass, 'border-violet-500/20 bg-violet-500/5 text-violet-700 hover:border-violet-500/30 hover:bg-violet-500/10 dark:border-violet-400/20 dark:bg-violet-400/10 dark:text-violet-300 dark:hover:bg-violet-400/15']"
                    @click="createIssue('linear')"
                >
                    <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                        <path d="M3.015 5.015h15.531l-2.016 2.016H5.031L3.015 5.015zm0 6.984 2.016-2.016h11.499l2.016 2.016H3.015zm0 6.984 2.016-2.016h7.467l2.016 2.016H3.015z" />
                    </svg>
                    {{ $t('components.create_linear_issue') }}
                </button>

                <button
                    type="button"
                    :class="ghostButtonClass"
                    @click="openLinkForm"
                >
                    {{ showLink ? $t('components.cancel') : $t('components.link') }}
                </button>

                <button
                    v-if="issues.length"
                    type="button"
                    :class="ghostButtonClass"
                    :disabled="syncing"
                    @click="syncIssues"
                >
                    <svg
                        class="mr-1 h-3.5 w-3.5"
                        :class="{ 'animate-spin': syncing }"
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="2"
                        aria-hidden="true"
                    >
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                    </svg>
                    {{ syncing ? $t('components.refreshing') : $t('components.refresh_status') }}
                </button>
            </div>

            <form
                v-if="showLink"
                class="mt-3 space-y-2 rounded-xl border border-slate-200 bg-slate-50/80 p-3 dark:border-slate-800 dark:bg-slate-950/70"
                @submit.prevent="submit"
            >
                <select
                    v-if="hasMultipleProviders"
                    v-model="form.provider"
                    :class="formSelectClass"
                >
                    <option value="jira">{{ $t('components.jira') }}</option>
                    <option value="linear">{{ $t('components.linear') }}</option>
                </select>
                <div
                    v-else
                    class="flex items-center rounded-xl border border-slate-200 bg-white px-3.5 py-2.5 text-sm font-medium text-slate-700 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200"
                >
                    {{ providerLabel(form.provider) }}
                </div>
                <input
                    v-model="form.reference"
                    type="text"
                    required
                    :placeholder="$t('components.issue_key_or_id_e_g_proj-123')"
                    :class="formInputClass"
                />
                <button
                    type="submit"
                    class="w-full rounded-lg bg-blue-600 px-3 py-2 text-xs font-semibold text-white transition hover:bg-blue-700 disabled:opacity-50"
                    :disabled="form.processing"
                >
                    {{ $t('components.link_issue') }}
                </button>
            </form>

        <ul v-if="issues.length" class="mt-3 space-y-2">
            <li
                v-for="issue in issues"
                :key="issue.id"
                class="rounded-xl border border-slate-200 bg-white p-3 dark:border-slate-800 dark:bg-slate-900/80"
            >
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-2">
                            <a
                                :href="issue.external_url"
                                target="_blank"
                                rel="noopener"
                                class="text-sm font-semibold text-blue-600 transition hover:text-blue-700 dark:text-blue-300 dark:hover:text-blue-200"
                            >
                                {{ providerLabel(issue.provider) }} · {{ issue.external_key }}
                            </a>
                            <span
                                v-if="issue.status"
                                class="rounded-full px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide"
                                :class="issueStatusClass(issue.status)"
                            >
                                {{ issue.status }}
                            </span>
                        </div>
                    </div>
                    <button
                        type="button"
                        class="shrink-0 rounded-md px-2 py-1 text-[11px] font-medium text-slate-400 transition hover:bg-red-50 hover:text-red-600 dark:hover:bg-red-950/30 dark:hover:text-red-400"
                        @click="unlinkIssue(issue.id)"
                    >
                        {{ $t('components.unlink') }}
                    </button>
                </div>
            </li>
        </ul>

        <p v-else class="mt-3 text-xs text-slate-500 dark:text-slate-400">
            {{ $t('components.no_linked_issues_yet') }}
        </p>
    </section>
</template>
