<script setup>
import { Head, Link } from '@inertiajs/vue3';
import PortalLayout from '../../Layouts/PortalLayout.vue';
import { usePortalRoutes } from '../../composables/usePortalRoutes.js';
import { useI18n } from 'vue-i18n';

const { portalPath } = usePortalRoutes();

defineProps({
    query: String,
    articles: Object,
});

const { t } = useI18n();
</script>

<template>
    <Head :title="$t('portal.search')" />
    <PortalLayout>
        <div class="mb-6">
            <Link :href="portalPath()" class="text-sm text-blue-600 hover:text-blue-700 dark:hover:text-blue-300 dark:text-blue-300">← Help Center</Link>
            <h1 class="mt-2 text-2xl font-bold text-slate-900 dark:text-slate-100">{{ $t('portal.search') }}</h1>
        </div>

        <form :action="portalPath('/search')" method="get" class="mb-6 flex gap-2">
            <input name="q" type="search" :value="query || ''" :placeholder="$t('portal.search_articles')" class="flex-1 rounded-lg border border-slate-300 dark:border-slate-700 px-4 py-2" />
            <button type="submit" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">{{ $t('portal.search') }}</button>
        </form>

        <div class="space-y-3">
            <Link
                v-for="article in articles.data"
                :key="article.id"
                :href="portalPath(`/articles/${article.slug}`)"
                class="block rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 p-5 shadow-sm hover:border-blue-300"
            >
                <h2 class="font-medium text-blue-600">{{ article.title }}</h2>
                <p v-if="article.excerpt" class="mt-1 text-sm text-slate-600 dark:text-slate-400">{{ article.excerpt }}</p>
            </Link>
            <p v-if="query && !articles.data?.length" class="text-sm text-slate-500 dark:text-slate-400">No results for "{{ query }}".</p>
        </div>
    </PortalLayout>
</template>
