<script setup>
import { Head, Link, usePage } from '@inertiajs/vue3';
import AgentLayout from '../../Layouts/AgentLayout.vue';
import PageHeader from '../../Components/PageHeader.vue';
import DataTable from '../../Components/DataTable.vue';
import PaginationLinks from '../../Components/PaginationLinks.vue';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { useDateTime } from '../../composables/useDateTime.js';

defineProps({
    articles: Object,
    collections: Array,
});

const { formatDateTime, formatDate } = useDateTime();

const { t } = useI18n();
const portalUrl = computed(() => usePage().props.helpCenter?.homeUrl ?? '/portal');
</script>

<template>
    <Head :title="$t('knowledge.knowledge_base')" />
    <AgentLayout>
        <PageHeader :description="$t('knowledge.help_articles_for_agents_and_customers')">
            <template #actions>
                <Link href="/knowledge/collections" class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">{{ $t('knowledge.collections') }}</Link>
                <Link href="/knowledge/settings" class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">{{ $t('knowledge.locales') }}</Link>
                <a :href="portalUrl" target="_blank" class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">{{ $t('knowledge.view_portal') }}</a>
                <Link href="/knowledge/create" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">{{ $t('knowledge.new_article') }}</Link>
            </template>
        </PageHeader>

        <DataTable>
            <thead class="bg-slate-50">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">{{ $t('knowledge.locale') }}</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">{{ $t('common.title') }}</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">{{ $t('knowledge.collection') }}</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">{{ $t('knowledge.category') }}</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">{{ $t('knowledge.status') }}</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">{{ $t('knowledge.updated') }}</th>
                    <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-slate-500">{{ $t('common.actions') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                <tr v-for="article in articles.data" :key="article.id" class="hover:bg-slate-50">
                    <td class="px-4 py-3 text-xs font-semibold uppercase text-slate-500">{{ article.locale || 'en' }}</td>
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
                            {{ article.is_published ? $t('knowledge.published') : $t('knowledge.draft') }}
                        </span>
                    </td>
                    <td class="px-4 py-3 text-sm text-slate-500">{{ formatDate(article.updated_at) }}</td>
                    <td class="px-4 py-3 text-right text-sm">
                        <Link :href="`/knowledge/${article.id}`" class="font-medium text-slate-700 hover:text-slate-900">{{ $t('knowledge.view') }}</Link>
                        <span class="mx-2 text-slate-300">·</span>
                        <Link :href="`/knowledge/${article.id}/edit`" class="font-medium text-blue-600 hover:text-blue-700">{{ $t('knowledge.edit') }}</Link>
                    </td>
                </tr>
                <tr v-if="!articles.data?.length">
                    <td colspan="7" class="px-4 py-12 text-center text-sm text-slate-500">No articles yet.</td>
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
