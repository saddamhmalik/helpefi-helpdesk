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
                <Link href="/knowledge/collections" class="agent-btn-secondary">{{ $t('knowledge.collections') }}</Link>
                <Link href="/knowledge/settings" class="agent-btn-secondary">{{ $t('knowledge.locales') }}</Link>
                <a :href="portalUrl" target="_blank" class="agent-btn-secondary">{{ $t('knowledge.view_portal') }}</a>
                <Link href="/knowledge/create" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">{{ $t('knowledge.new_article') }}</Link>
            </template>
        </PageHeader>

        <DataTable>
            <thead class="agent-panel-muted">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide agent-text-subtle">{{ $t('knowledge.locale') }}</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide agent-text-subtle">{{ $t('common.title') }}</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide agent-text-subtle">{{ $t('knowledge.collection') }}</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide agent-text-subtle">{{ $t('knowledge.category') }}</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide agent-text-subtle">{{ $t('knowledge.status') }}</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide agent-text-subtle">{{ $t('knowledge.updated') }}</th>
                    <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide agent-text-subtle">{{ $t('common.actions') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y agent-table-divider">
                <tr v-for="article in articles.data" :key="article.id" class="agent-hover-surface">
                    <td class="px-4 py-3 text-xs font-semibold uppercase agent-text-subtle">{{ article.locale || 'en' }}</td>
                    <td class="px-4 py-3">
                        <Link :href="`/knowledge/${article.id}`" class="font-medium text-blue-600 hover:text-blue-700 dark:hover:text-blue-300 dark:text-blue-300">{{ article.title }}</Link>
                        <p v-if="article.excerpt" class="mt-0.5 max-w-lg truncate text-xs agent-text-subtle">{{ article.excerpt }}</p>
                    </td>
                    <td class="px-4 py-3 text-sm agent-text-muted">{{ article.collection?.name || '—' }}</td>
                    <td class="px-4 py-3 text-sm agent-text-muted">{{ article.category?.name || '—' }}</td>
                    <td class="px-4 py-3">
                        <span
                            class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium"
                            :class="article.is_published ? 'bg-emerald-50 dark:bg-emerald-950/40 text-emerald-700 dark:text-emerald-300 ring-1 ring-inset ring-emerald-600/15' : 'bg-slate-100 dark:bg-slate-900 agent-text-muted'"
                        >
                            {{ article.is_published ? $t('knowledge.published') : $t('knowledge.draft') }}
                        </span>
                    </td>
                    <td class="px-4 py-3 text-sm agent-text-subtle">{{ formatDate(article.updated_at) }}</td>
                    <td class="px-4 py-3 text-right text-sm">
                        <Link :href="`/knowledge/${article.id}`" class="font-medium text-slate-700 dark:text-slate-300 hover:text-slate-900 dark:text-slate-100 dark:hover:text-slate-100">{{ $t('knowledge.view') }}</Link>
                        <span class="mx-2 text-slate-300">·</span>
                        <Link :href="`/knowledge/${article.id}/edit`" class="font-medium text-blue-600 hover:text-blue-700 dark:hover:text-blue-300 dark:text-blue-300">{{ $t('knowledge.edit') }}</Link>
                    </td>
                </tr>
                <tr v-if="!articles.data?.length">
                    <td colspan="7" class="px-4 py-12 text-center text-sm agent-text-subtle">No articles yet.</td>
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
