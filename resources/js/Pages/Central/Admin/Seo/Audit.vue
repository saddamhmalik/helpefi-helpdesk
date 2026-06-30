<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, watch } from 'vue';
import AdminLayout from '../../../../Layouts/AdminLayout.vue';
import PageHeader from '../../../../Components/PageHeader.vue';
import PlatformStatCard from '../../../../Components/Platform/PlatformStatCard.vue';

const props = defineProps({
    report: { type: Object, default: null },
    auditStatus: { type: String, default: 'pending' },
});

let pollTimer = null;

const issueLabels = {
    missing_h1: 'Missing H1',
    duplicate_h1: 'Duplicate H1',
    missing_alt: 'Missing alt',
    missing_title: 'Missing title',
    duplicate_title: 'Duplicate title',
    missing_description: 'Missing description',
    broken_links: 'Broken links',
    redirect_chains: 'Redirect chains',
    canonical_errors: 'Canonical errors',
    large_images: 'Large images',
    missing_schema: 'Missing schema',
};

const severityTone = {
    error: 'red',
    warning: 'amber',
};

const summary = computed(() => props.report?.summary ?? {});
const issues = computed(() => props.report?.issues ?? []);
const pages = computed(() => props.report?.pages ?? []);
const isRunning = computed(() => props.auditStatus === 'running');
const hasReport = computed(() => props.auditStatus === 'ready' && props.report !== null);

const groupedIssues = computed(() => {
    const groups = {};

    for (const issue of issues.value) {
        if (!groups[issue.type]) {
            groups[issue.type] = [];
        }

        groups[issue.type].push(issue);
    }

    return groups;
});

const scoreTone = computed(() => {
    const score = summary.value.health_score ?? 0;

    if (score >= 90) {
        return 'emerald';
    }

    if (score >= 70) {
        return 'amber';
    }

    return 'red';
});

const formatDate = (value) => {
    if (!value) {
        return '—';
    }

    return new Intl.DateTimeFormat(undefined, {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(new Date(value));
};

const runAudit = () => {
    router.post('/admin/seo-audit');
};

const stopPolling = () => {
    if (pollTimer !== null) {
        clearInterval(pollTimer);
        pollTimer = null;
    }
};

const startPolling = () => {
    stopPolling();

    if (!isRunning.value) {
        return;
    }

    pollTimer = setInterval(() => {
        router.reload({ only: ['report', 'auditStatus'], preserveScroll: true });
    }, 5000);
};

watch(() => props.auditStatus, () => startPolling(), { immediate: true });

onBeforeUnmount(() => stopPolling());
</script>

<template>
    <Head title="SEO audit" />
    <AdminLayout>
        <div class="mx-auto max-w-7xl px-4 py-8 sm:px-6">
            <PageHeader
                title="Marketing SEO audit"
                :description="hasReport
                    ? `Scanned ${report.pages_scanned} public pages on ${report.site_url}. Last run: ${formatDate(report.generated_at)}.`
                    : isRunning
                        ? 'Scanning public marketing pages. Results will appear automatically.'
                        : 'Run an audit to scan public marketing pages for SEO issues.'"
            >
                <template #actions>
                    <div class="flex items-center gap-2">
                        <Link href="/admin/seo" class="rounded-xl border border-slate-200 px-4 py-2.5 text-sm font-medium text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-800">
                            SEO metadata
                        </Link>
                        <button
                            type="button"
                            class="rounded-xl bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-60"
                            :disabled="isRunning"
                            @click="runAudit"
                        >
                            {{ isRunning ? 'Audit running…' : hasReport ? 'Run audit' : 'Run first audit' }}
                        </button>
                    </div>
                </template>
            </PageHeader>

            <div
                v-if="isRunning"
                class="mb-8 rounded-2xl border border-blue-200 bg-blue-50 px-5 py-4 text-sm text-blue-900 dark:border-blue-900/60 dark:bg-blue-950/40 dark:text-blue-200"
            >
                SEO audit is running in the background. This page refreshes every few seconds until the report is ready.
            </div>

            <template v-if="hasReport">
            <section class="mb-8 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <PlatformStatCard label="Health score" :value="`${summary.health_score ?? 0}%`" :tone="scoreTone" />
                <PlatformStatCard label="Total issues" :value="summary.total_issues ?? 0" :tone="(summary.total_issues ?? 0) > 0 ? 'amber' : 'emerald'" />
                <PlatformStatCard label="Pages scanned" :value="report.pages_scanned ?? 0" />
                <PlatformStatCard label="Errors" :value="issues.filter((issue) => issue.severity === 'error').length" tone="red" />
            </section>

            <section class="mb-8">
                <h2 class="mb-3 text-sm font-semibold text-slate-900 dark:text-slate-100">Issue summary</h2>
                <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                    <div
                        v-for="(label, key) in issueLabels"
                        :key="key"
                        class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900"
                    >
                        <p class="text-xs font-medium uppercase tracking-wide text-slate-500 dark:text-slate-400">{{ label }}</p>
                        <p class="mt-2 text-2xl font-semibold tabular-nums" :class="(summary[key] ?? 0) > 0 ? 'text-amber-700 dark:text-amber-300' : 'text-emerald-700 dark:text-emerald-300'">
                            {{ summary[key] ?? 0 }}
                        </p>
                    </div>
                </div>
            </section>

            <section v-if="issues.length" class="mb-8 space-y-6">
                <h2 class="text-sm font-semibold text-slate-900 dark:text-slate-100">Issues by category</h2>

                <div
                    v-for="(label, type) in issueLabels"
                    :key="type"
                    v-show="groupedIssues[type]?.length"
                    class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900"
                >
                    <div class="border-b border-slate-200 px-5 py-4 dark:border-slate-800">
                        <h3 class="font-medium text-slate-900 dark:text-slate-100">{{ label }}</h3>
                        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ groupedIssues[type]?.length ?? 0 }} issue(s)</p>
                    </div>
                    <ul class="divide-y divide-slate-100 dark:divide-slate-800">
                        <li v-for="(issue, index) in groupedIssues[type]" :key="`${type}-${index}`" class="px-5 py-4">
                            <div class="flex flex-wrap items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <p class="font-medium text-slate-900 dark:text-slate-100">{{ issue.message }}</p>
                                    <a
                                        :href="issue.url"
                                        target="_blank"
                                        class="mt-1 inline-block truncate text-sm text-blue-600 hover:text-blue-700 dark:text-blue-300"
                                    >
                                        {{ issue.url }}
                                    </a>
                                    <pre
                                        v-if="issue.details && Object.keys(issue.details).length"
                                        class="mt-3 overflow-x-auto rounded-xl bg-slate-50 p-3 text-xs text-slate-600 dark:bg-slate-950 dark:text-slate-300"
                                    >{{ JSON.stringify(issue.details, null, 2) }}</pre>
                                </div>
                                <span
                                    class="shrink-0 rounded-full px-2.5 py-1 text-xs font-medium capitalize"
                                    :class="{
                                        'bg-red-100 text-red-700 dark:text-red-300': severityTone[issue.severity] === 'red',
                                        'bg-amber-100 text-amber-800 dark:text-amber-300': severityTone[issue.severity] === 'amber',
                                    }"
                                >
                                    {{ issue.severity }}
                                </span>
                            </div>
                        </li>
                    </ul>
                </div>
            </section>

            <section>
                <h2 class="mb-3 text-sm font-semibold text-slate-900 dark:text-slate-100">Page inventory</h2>
                <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
                    <table class="min-w-full divide-y divide-slate-200 text-sm dark:divide-slate-800">
                        <thead class="bg-slate-50 dark:bg-slate-950">
                            <tr>
                                <th class="px-5 py-3.5 text-left font-medium text-slate-600 dark:text-slate-400">Page</th>
                                <th class="px-5 py-3.5 text-left font-medium text-slate-600 dark:text-slate-400">Title</th>
                                <th class="px-5 py-3.5 text-left font-medium text-slate-600 dark:text-slate-400">H1</th>
                                <th class="px-5 py-3.5 text-left font-medium text-slate-600 dark:text-slate-400">Schema</th>
                                <th class="px-5 py-3.5 text-right font-medium text-slate-600 dark:text-slate-400">Issues</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                            <tr v-for="page in pages" :key="page.url" class="hover:bg-slate-50 dark:hover:bg-slate-800/80">
                                <td class="px-5 py-4">
                                    <a :href="page.url" target="_blank" class="font-mono text-xs text-blue-600 hover:text-blue-700 dark:text-blue-300">{{ page.path }}</a>
                                </td>
                                <td class="max-w-xs px-5 py-4">
                                    <p class="line-clamp-2 text-slate-900 dark:text-slate-100">{{ page.title || '—' }}</p>
                                </td>
                                <td class="px-5 py-4 tabular-nums text-slate-700 dark:text-slate-300">{{ page.h1_count }}</td>
                                <td class="px-5 py-4">
                                    <span
                                        class="rounded-full px-2 py-0.5 text-xs font-medium"
                                        :class="page.schema ? 'bg-emerald-100 text-emerald-700 dark:text-emerald-300' : 'bg-amber-100 text-amber-800 dark:text-amber-300'"
                                    >
                                        {{ page.schema ? 'Yes' : 'No' }}
                                    </span>
                                </td>
                                <td class="px-5 py-4 text-right tabular-nums text-slate-700 dark:text-slate-300">{{ page.issues?.length ?? 0 }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>
            </template>
        </div>
    </AdminLayout>
</template>
