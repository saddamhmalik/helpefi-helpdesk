<script setup>
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import AgentLayout from '../../Layouts/AgentLayout.vue';
import KnowledgeBody from '../../Components/KnowledgeBody.vue';
import { useI18n } from 'vue-i18n';
import { useDateTime } from '../../composables/useDateTime.js';

const props = defineProps({
    article: Object,
    versions: Array,
    translations: Array,
});

const { formatDateTime } = useDateTime();

const { t } = useI18n();

const restoreVersion = (versionId) => {
    router.post(`/knowledge/${props.article.id}/versions/${versionId}/restore`);
};

const portalArticleUrl = computed(() => {
    const helpCenter = usePage().props.helpCenter;
    if (!helpCenter?.brandSlug) {
        return null;
    }

    const locale = props.article.locale || 'en';

    return `/portal/${helpCenter.brandSlug}/articles/${props.article.slug}?lang=${locale}`;
});
</script>

<template>
    <Head :title="article.title" />
    <AgentLayout>
        <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
            <Link href="/knowledge" class="text-sm text-blue-600 hover:text-blue-700">{{ $t('knowledge.back_to_knowledge_base') }}</Link>
            <div class="flex flex-wrap items-center gap-3">
                <Link
                    :href="`/knowledge/${article.id}/edit`"
                    class="rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-sm font-medium text-slate-700 transition hover:bg-slate-50"
                >{{ $t('knowledge.edit') }}</Link>
                <a
                    v-if="article.is_published && portalArticleUrl"
                    :href="portalArticleUrl"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="text-sm text-blue-600 hover:text-blue-700"
                >{{ $t('knowledge.view_on_portal') }}</a>
            </div>
        </div>

        <div class="grid gap-6 lg:grid-cols-3">
            <div class="lg:col-span-2">
                <article class="rounded-xl border border-slate-200 bg-white p-8 shadow-sm">
                    <div class="mb-6 flex flex-wrap items-center gap-2">
                        <span class="rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-semibold uppercase text-slate-600">{{ article.locale || 'en' }}</span>
                        <span
                            class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium"
                            :class="article.is_published ? 'bg-emerald-50 text-emerald-700 ring-1 ring-inset ring-emerald-600/15' : 'bg-slate-100 text-slate-600'"
                        >
                            {{ article.is_published ? $t('knowledge.published') : $t('knowledge.draft') }}
                        </span>
                    </div>

                    <h1 class="text-3xl font-bold tracking-tight text-slate-900">{{ article.title }}</h1>

                    <dl class="mt-4 grid gap-3 text-sm sm:grid-cols-2">
                        <div v-if="article.collection?.name">
                            <dt class="font-medium text-slate-500">{{ $t('knowledge.collection') }}</dt>
                            <dd class="mt-0.5 text-slate-900">{{ article.collection.name }}</dd>
                        </div>
                        <div v-if="article.category?.name">
                            <dt class="font-medium text-slate-500">{{ $t('knowledge.category') }}</dt>
                            <dd class="mt-0.5 text-slate-900">{{ article.category.name }}</dd>
                        </div>
                    </dl>

                    <p v-if="article.excerpt" class="mt-6 text-lg leading-relaxed text-slate-600">{{ article.excerpt }}</p>

                    <div class="mt-8 border-t border-slate-100 pt-8">
                        <KnowledgeBody :content="article.body" />
                    </div>
                </article>
            </div>

            <div class="space-y-6">
                <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
                    <div class="flex items-center justify-between gap-2">
                        <h2 class="text-lg font-semibold text-slate-900">{{ $t('knowledge.translations') }}</h2>
                        <Link :href="`/knowledge/${article.id}/edit`" class="text-sm font-medium text-blue-600 hover:text-blue-700">{{ $t('knowledge.edit') }}</Link>
                    </div>
                    <div class="mt-4 space-y-2">
                        <Link
                            v-for="translation in translations"
                            :key="translation.id"
                            :href="`/knowledge/${translation.id}`"
                            class="flex items-center justify-between rounded-lg border px-3 py-2 text-sm transition"
                            :class="translation.id === article.id ? 'border-blue-200 bg-blue-50 text-blue-700' : 'border-slate-100 hover:bg-slate-50'"
                        >
                            <span class="font-medium uppercase">{{ translation.locale }}</span>
                            <span class="truncate text-slate-600">{{ translation.title }}</span>
                        </Link>
                    </div>
                </div>

                <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
                    <h2 class="text-lg font-semibold text-slate-900">{{ $t('knowledge.version_history') }}</h2>
                    <div class="mt-4 space-y-3">
                        <div v-for="version in versions" :key="version.id" class="rounded-lg border border-slate-100 p-3 text-sm">
                            <div class="flex items-center justify-between gap-2">
                                <span class="font-medium text-slate-900">v{{ version.version_number }}</span>
                                <button type="button" class="text-blue-600 hover:text-blue-700" @click="restoreVersion(version.id)">{{ $t('knowledge.restore') }}</button>
                            </div>
                            <p class="mt-1 truncate text-slate-600">{{ version.title }}</p>
                            <p class="mt-1 text-xs text-slate-500">{{ version.user?.name || 'Unknown' }} · {{ formatDateTime(version.created_at) }}</p>
                        </div>
                        <p v-if="!versions?.length" class="text-sm text-slate-500">{{ $t('knowledge.no_previous_versions_yet') }}</p>
                    </div>
                </div>
            </div>
        </div>
    </AgentLayout>
</template>
