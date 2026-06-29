<script setup>
import { Head, Link } from '@inertiajs/vue3';
import AdminLayout from '../../../../Layouts/AdminLayout.vue';
import PageHeader from '../../../../Components/PageHeader.vue';

defineProps({
    drafts: { type: Array, default: () => [] },
});

const statusClass = (status) => {
    if (status === 'published') return 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-200';
    if (status === 'ready') return 'bg-blue-100 text-blue-800 dark:bg-blue-900/40 dark:text-blue-200';
    if (status === 'archived') return 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300';
    return 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-200';
};
</script>

<template>
    <Head title="AI content" />
    <AdminLayout>
        <div class="mx-auto max-w-6xl px-4 py-8 sm:px-6">
            <PageHeader
                title="AI content generation"
                description="Generate landing pages, comparisons, industry pages, FAQs, SEO metadata, schema, and blog outlines. Edit every draft before publishing."
            >
                <template #actions>
                    <Link href="/admin/content/create" class="rounded-xl bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-blue-700">
                        New draft
                    </Link>
                </template>
            </PageHeader>

            <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <table class="min-w-full divide-y divide-slate-200 text-sm dark:divide-slate-800">
                    <thead class="bg-slate-50 dark:bg-slate-950">
                        <tr>
                            <th class="px-5 py-3.5 text-left font-medium text-slate-600 dark:text-slate-400">Title</th>
                            <th class="px-5 py-3.5 text-left font-medium text-slate-600 dark:text-slate-400">Type</th>
                            <th class="px-5 py-3.5 text-left font-medium text-slate-600 dark:text-slate-400">Status</th>
                            <th class="px-5 py-3.5 text-left font-medium text-slate-600 dark:text-slate-400">Warnings</th>
                            <th class="px-5 py-3.5 text-right font-medium text-slate-600 dark:text-slate-400">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        <tr v-for="draft in drafts" :key="draft.id" class="hover:bg-slate-50 dark:hover:bg-slate-800/80">
                            <td class="px-5 py-4">
                                <p class="font-medium text-slate-900 dark:text-slate-100">{{ draft.title }}</p>
                                <p v-if="draft.slug" class="mt-1 font-mono text-xs text-slate-500 dark:text-slate-400">{{ draft.slug }}</p>
                            </td>
                            <td class="px-5 py-4 text-slate-600 dark:text-slate-300">{{ draft.content_type_label }}</td>
                            <td class="px-5 py-4">
                                <span class="rounded-full px-2.5 py-1 text-xs font-medium" :class="statusClass(draft.status)">
                                    {{ draft.status }}
                                </span>
                            </td>
                            <td class="px-5 py-4">
                                <span v-if="draft.duplicate_warnings?.length" class="text-amber-600 dark:text-amber-400">
                                    {{ draft.duplicate_warnings.length }} overlap{{ draft.duplicate_warnings.length === 1 ? '' : 's' }}
                                </span>
                                <span v-else class="text-slate-400">—</span>
                            </td>
                            <td class="px-5 py-4 text-right">
                                <Link :href="`/admin/content/${draft.id}/edit`" class="text-sm font-medium text-blue-600 hover:text-blue-700">
                                    {{ draft.status === 'published' ? 'View' : 'Edit' }}
                                </Link>
                            </td>
                        </tr>
                        <tr v-if="!drafts.length">
                            <td colspan="5" class="px-5 py-10 text-center text-sm text-slate-500 dark:text-slate-400">
                                No content drafts yet. Generate your first page.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </AdminLayout>
</template>
