<script setup>
import { Head, Link } from '@inertiajs/vue3';
import KnowledgeBody from '../../Components/KnowledgeBody.vue';
import PortalLayout from '../../Layouts/PortalLayout.vue';
import { usePortalRoutes } from '../../composables/usePortalRoutes.js';
import { useI18n } from 'vue-i18n';

defineProps({
    article: Object,
    translations: Array,
    locale: String,
    locales: Array,
});

const { t } = useI18n();

const { portalPath } = usePortalRoutes();
</script>

<template>
    <Head :title="article.title" />
    <PortalLayout>
        <div class="mx-auto max-w-3xl">
            <div class="mb-8">
                <Link
                    v-if="article.collection"
                    :href="portalPath(`/collections/${article.collection.slug}`)"
                    class="inline-flex items-center gap-1 text-sm font-medium text-blue-600 hover:text-blue-700 dark:hover:text-blue-300 dark:text-blue-300"
                >
                    ← {{ article.collection.name }}
                </Link>
                <Link v-else :href="portalPath()" class="inline-flex items-center gap-1 text-sm font-medium text-blue-600 hover:text-blue-700 dark:hover:text-blue-300 dark:text-blue-300">← Help Center</Link>

                <div v-if="translations?.length > 1" class="mt-4 flex flex-wrap gap-2">
                    <Link
                        v-for="translation in translations"
                        :key="translation.id"
                        :href="portalPath(`/articles/${translation.slug}?lang=${translation.locale}`)"
                        class="rounded-full px-3 py-1 text-xs font-semibold uppercase transition"
                        :class="translation.locale === locale ? 'bg-slate-900 text-white' : 'bg-slate-100 dark:bg-slate-900 text-slate-600 dark:text-slate-400 hover:bg-slate-200'"
                    >
                        {{ translation.locale }}
                    </Link>
                </div>

                <h1 class="mt-4 text-3xl font-bold tracking-tight text-slate-900 dark:text-slate-100 sm:text-4xl">{{ article.title }}</h1>
                <p v-if="article.excerpt" class="mt-3 text-lg leading-relaxed text-slate-600 dark:text-slate-400">{{ article.excerpt }}</p>
            </div>

            <article class="rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 px-6 py-8 shadow-sm sm:px-10 sm:py-10">
                <KnowledgeBody :content="article.body" />
            </article>

            <div class="mt-8 rounded-2xl border border-slate-200 dark:border-slate-800 bg-gradient-to-b from-white to-slate-50 px-6 py-8 text-center shadow-sm">
                <p class="text-base font-medium text-slate-900 dark:text-slate-100">{{ $t('portal.still_need_help') }}</p>
                <p class="mt-1 text-sm text-slate-600 dark:text-slate-400">{{ $t('portal.our_team_is_ready_to_assist_you') }}</p>
                <div class="mt-4 flex flex-wrap justify-center gap-3">
                    <Link :href="portalPath('/submit')" class="rounded-lg bg-blue-600 px-5 py-2.5 text-sm font-medium text-white hover:bg-blue-700">{{ $t('portal.submit_a_request') }}</Link>
                    <Link :href="portalPath('/track')" class="rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 px-5 py-2.5 text-sm font-medium text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:bg-slate-950 dark:hover:bg-slate-800">{{ $t('portal.track_a_request') }}</Link>
                </div>
            </div>
        </div>
    </PortalLayout>
</template>
