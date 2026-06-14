<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import AgentLayout from '../../Layouts/AgentLayout.vue';
import PageHeader from '../../Components/PageHeader.vue';
import AppToggle from '../../Components/AppToggle.vue';
import { useI18n } from 'vue-i18n';

defineProps({
    collection: { type: Object, default: null },
    sections: { type: Array, default: () => [] },
    canManageVisibility: { type: Boolean, default: false },
});

const { t } = useI18n();

const toggleVisibility = (article, isPublic) => {
    router.put(`/knowledge/${article.id}`, { is_public: isPublic }, {
        preserveScroll: true,
        preserveState: true,
    });
};
</script>

<template>
    <Head :title="t('handbook.page_title')" />
    <AgentLayout>
        <div class="mx-auto w-full max-w-4xl">
            <PageHeader
                :title="collection?.name || t('handbook.page_title')"
                :description="collection?.description || t('handbook.page_description')"
            >
                <template #actions>
                    <Link href="/knowledge" class="agent-btn-secondary">{{ t('handbook.manage_knowledge_base') }}</Link>
                </template>
            </PageHeader>

            <p class="mb-6 rounded-lg border border-sky-200 bg-sky-50 px-4 py-3 text-sm text-sky-900 dark:border-sky-900/60 dark:bg-sky-950/40 dark:text-sky-200">
                {{ t('handbook.agents_only_notice') }}
            </p>

            <div v-if="!sections.length" class="rounded-xl border agent-border agent-panel p-8 text-center">
                <p class="text-sm agent-text-muted">{{ t('handbook.empty_state') }}</p>
            </div>

            <div v-else class="space-y-6 pb-8">
                <section
                    v-for="section in sections"
                    :key="section.slug"
                    class="overflow-hidden rounded-xl border agent-border agent-panel"
                >
                    <div class="flex items-center justify-between gap-3 border-b agent-border bg-slate-50/80 px-4 py-3 sm:px-5 dark:bg-slate-900/40">
                        <h2 class="text-sm font-semibold agent-text">{{ section.name }}</h2>
                        <span class="shrink-0 rounded-full bg-slate-200/80 px-2 py-0.5 text-[11px] font-medium text-slate-600 dark:bg-slate-800 dark:text-slate-300">
                            {{ section.articles.length }}
                        </span>
                    </div>
                    <ol class="divide-y agent-table-divider">
                        <li
                            v-for="(article, index) in section.articles"
                            :key="article.id"
                            class="px-4 py-4 sm:px-5"
                        >
                            <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:gap-4">
                                <Link
                                    :href="`/knowledge/${article.id}`"
                                    class="group flex min-w-0 flex-1 items-start gap-3 sm:gap-4"
                                >
                                    <span class="mt-0.5 flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-blue-100 text-xs font-bold text-blue-700 dark:bg-blue-950/50 dark:text-blue-300">
                                        {{ index + 1 }}
                                    </span>
                                    <span class="min-w-0 flex-1">
                                        <span class="block text-sm font-semibold text-blue-600 group-hover:text-blue-700 dark:text-blue-300 dark:group-hover:text-blue-200">
                                            {{ article.title }}
                                        </span>
                                        <span v-if="article.excerpt" class="mt-1 block text-sm leading-relaxed agent-text-muted">
                                            {{ article.excerpt }}
                                        </span>
                                    </span>
                                </Link>
                                <div class="flex shrink-0 flex-wrap items-center gap-3 sm:ml-auto sm:justify-end">
                                    <span
                                        class="rounded-full px-2.5 py-0.5 text-[11px] font-semibold uppercase tracking-wide"
                                        :class="article.is_public
                                            ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/50 dark:text-emerald-300'
                                            : 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300'"
                                    >
                                        {{ article.is_public ? t('handbook.visibility_public') : t('handbook.visibility_agents_only') }}
                                    </span>
                                    <AppToggle
                                        v-if="canManageVisibility"
                                        :model-value="article.is_public"
                                        :label="t('handbook.public_on_portal')"
                                        @update:model-value="toggleVisibility(article, $event)"
                                    />
                                    <Link
                                        :href="`/knowledge/${article.id}`"
                                        class="hidden items-center gap-1 text-xs font-medium text-slate-500 hover:text-slate-800 dark:text-slate-400 dark:hover:text-slate-200 sm:flex"
                                    >
                                        {{ t('handbook.read_guide') }}
                                        <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                                        </svg>
                                    </Link>
                                </div>
                            </div>
                        </li>
                    </ol>
                </section>
            </div>
        </div>
    </AgentLayout>
</template>
