<script setup>
import { Head, Link } from '@inertiajs/vue3';
import AdminLayout from '../../../../Layouts/AdminLayout.vue';
import PageHeader from '../../../../Components/PageHeader.vue';
import { ref } from 'vue';

defineProps({
    entries: { type: Array, default: () => [] },
});

const pageKey = ref('');
</script>

<template>
    <Head title="SEO metadata" />
    <AdminLayout>
        <div class="mx-auto max-w-6xl px-4 py-8 sm:px-6">
            <PageHeader
                title="SEO metadata"
                description="Generate and override SEO titles, descriptions, keywords, and social descriptions for marketing pages."
            >
                <template #actions>
                    <div class="flex items-center gap-2">
                        <Link href="/admin/seo-audit" class="rounded-xl border border-slate-200 px-4 py-2.5 text-sm font-medium text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-800">
                            SEO audit
                        </Link>
                        <input v-model="pageKey" type="text" class="agent-input w-56 rounded-xl px-3.5 py-2.5 text-sm" placeholder="page key (e.g. home)" />
                        <Link
                            :href="pageKey ? `/admin/seo/${pageKey}` : '/admin/seo'"
                            class="rounded-xl bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-blue-700"
                        >
                            Open
                        </Link>
                    </div>
                </template>
            </PageHeader>

            <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <table class="min-w-full divide-y divide-slate-200 text-sm dark:divide-slate-800">
                    <thead class="bg-slate-50 dark:bg-slate-950">
                        <tr>
                            <th class="px-5 py-3.5 text-left font-medium text-slate-600 dark:text-slate-400">Page key</th>
                            <th class="px-5 py-3.5 text-left font-medium text-slate-600 dark:text-slate-400">Title</th>
                            <th class="px-5 py-3.5 text-right font-medium text-slate-600 dark:text-slate-400">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        <tr v-for="entry in entries" :key="entry.page_key" class="hover:bg-slate-50 dark:hover:bg-slate-800/80">
                            <td class="px-5 py-4 font-mono text-xs text-slate-600 dark:text-slate-300">{{ entry.page_key }}</td>
                            <td class="px-5 py-4">
                                <p class="font-medium text-slate-900 dark:text-slate-100">
                                    {{ entry.manual?.seo_title || entry.ai?.seo_title || '—' }}
                                </p>
                                <p class="mt-1 line-clamp-1 text-xs text-slate-500 dark:text-slate-400">
                                    {{ entry.manual?.meta_description || entry.ai?.meta_description || '' }}
                                </p>
                            </td>
                            <td class="px-5 py-4 text-right">
                                <Link :href="`/admin/seo/${entry.page_key}`" class="text-sm font-medium text-blue-600 hover:text-blue-700">
                                    Edit
                                </Link>
                            </td>
                        </tr>
                        <tr v-if="!entries.length">
                            <td colspan="3" class="px-5 py-10 text-center text-sm text-slate-500 dark:text-slate-400">
                                No SEO metadata entries yet.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </AdminLayout>
</template>

