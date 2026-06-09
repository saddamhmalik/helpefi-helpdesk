<script setup>
import { Head, Link } from '@inertiajs/vue3';
import AgentLayout from '../../Layouts/AgentLayout.vue';
import PageHeader from '../../Components/PageHeader.vue';
import DataTable from '../../Components/DataTable.vue';
import PaginationLinks from '../../Components/PaginationLinks.vue';

defineProps({
    articles: Object,
    collections: Array,
});
</script>

<template>
    <Head title="Knowledge base" />
    <AgentLayout>
        <PageHeader description="Help articles for agents and customers.">
            <template #actions>
                <Link href="/knowledge/collections" class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Collections</Link>
                <a href="/portal" target="_blank" class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">View portal</a>
                <Link href="/knowledge/create" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">New article</Link>
            </template>
        </PageHeader>

        <DataTable>
            <thead class="bg-slate-50">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Title</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Collection</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Category</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Status</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Updated</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                <tr v-for="article in articles.data" :key="article.id" class="hover:bg-slate-50">
                    <td class="px-4 py-3">
                        <Link :href="`/knowledge/${article.id}`" class="font-medium text-blue-600 hover:text-blue-700">{{ article.title }}</Link>
                        <p v-if="article.excerpt" class="mt-0.5 max-w-lg truncate text-xs text-slate-500">{{ article.excerpt }}</p>
                    </td>
                    <td class="px-4 py-3 text-sm text-slate-600">{{ article.collection?.name || '—' }}</td>
                    <td class="px-4 py-3 text-sm text-slate-600">{{ article.category?.name || '—' }}</td>
                    <td class="px-4 py-3">
                        <span
                            class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium"
                            :class="article.is_published ? 'bg-emerald-50 text-emerald-700 ring-1 ring-inset ring-emerald-600/15' : 'bg-slate-100 text-slate-600'"
                        >
                            {{ article.is_published ? 'Published' : 'Draft' }}
                        </span>
                    </td>
                    <td class="px-4 py-3 text-sm text-slate-500">{{ new Date(article.updated_at).toLocaleDateString() }}</td>
                </tr>
                <tr v-if="!articles.data?.length">
                    <td colspan="5" class="px-4 py-12 text-center text-sm text-slate-500">No articles yet.</td>
                </tr>
            </tbody>
            <template #footer>
                <PaginationLinks
                    :links="articles.links"
                    :from="articles.from"
                    :to="articles.to"
                    :total="articles.total"
                />
            </template>
        </DataTable>
    </AgentLayout>
</template>
