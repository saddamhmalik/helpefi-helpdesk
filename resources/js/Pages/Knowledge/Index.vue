<script setup>
import { Head, Link, usePage } from '@inertiajs/vue3';
import AgentLayout from '../../Layouts/AgentLayout.vue';
import PageHeader from '../../Components/PageHeader.vue';
import DataTable from '../../Components/DataTable.vue';
import DataTableMobileCard from '../../Components/ui/DataTableMobileCard.vue';
import PaginationLinks from '../../Components/PaginationLinks.vue';
import AppBadge from '../../Components/ui/AppBadge.vue';
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
                        <AppBadge :variant="article.is_published ? 'success' : 'default'">
                            {{ article.is_published ? $t('knowledge.published') : $t('knowledge.draft') }}
                        </AppBadge>
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
            <template #mobile>
                <DataTableMobileCard v-for="article in articles.data" :key="`mobile-${article.id}`" tag="div">
                    <Link :href="`/knowledge/${article.id}`" class="block">
                        <div class="flex items-center gap-2">
                            <span class="text-xs font-semibold uppercase agent-text-subtle">{{ article.locale || 'en' }}</span>
                            <AppBadge :variant="article.is_published ? 'success' : 'default'">
                                {{ article.is_published ? $t('knowledge.published') : $t('knowledge.draft') }}
                            </AppBadge>
                        </div>
                        <p class="mt-2 text-sm font-medium agent-text">{{ article.title }}</p>
                        <p v-if="article.excerpt" class="mt-1 line-clamp-2 text-xs agent-text-muted">{{ article.excerpt }}</p>
                        <p class="mt-2 text-xs agent-text-subtle">{{ article.collection?.name || '—' }} · {{ formatDate(article.updated_at) }}</p>
                    </Link>
                </DataTableMobileCard>
                <div v-if="!articles.data?.length" class="p-6 text-center text-sm agent-text-subtle">No articles yet.</div>
            </template>
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
