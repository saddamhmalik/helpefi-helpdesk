<script setup>
import { Head, Link } from '@inertiajs/vue3';
import PortalLayout from '../../Layouts/PortalLayout.vue';
import { usePortalRoutes } from '../../composables/usePortalRoutes.js';
import { useI18n } from 'vue-i18n';

const { portalPath } = usePortalRoutes();

defineProps({
    collections: Array,
    featured: Array,
});

const { t } = useI18n();
</script>

<template>
    <Head :title="$t('portal.help_center')" />
    <PortalLayout>
        <div class="mb-10 text-center">
            <h1 class="text-3xl font-bold text-slate-900 dark:text-slate-100">{{ $t('portal.how_can_we_help') }}</h1>
            <p class="mt-2 text-slate-600 dark:text-slate-400">{{ $t('portal.browse_articles_request_services_or_submit_a_support_ticket') }}</p>
            <div class="mx-auto mt-6 flex max-w-xl flex-wrap justify-center gap-2">
                <Link :href="portalPath('/services')" class="rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 px-4 py-2 text-sm font-medium text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:bg-slate-950 dark:hover:bg-slate-800">{{ $t('portal.browse_services') }}</Link>
                <Link :href="portalPath('/submit')" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">{{ $t('portal.submit_request') }}</Link>
            </div>
            <form :action="portalPath('/search')" method="get" class="mx-auto mt-4 flex max-w-xl gap-2">
                <input name="q" type="search" :placeholder="$t('portal.search_articles')" class="flex-1 rounded-lg border border-slate-300 dark:border-slate-700 px-4 py-2" />
                <button type="submit" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">{{ $t('portal.search') }}</button>
            </form>
        </div>

        <section v-if="collections?.length" class="mb-10">
            <h2 class="mb-4 text-xl font-semibold text-slate-900 dark:text-slate-100">{{ $t('portal.collections') }}</h2>
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <Link
                    v-for="collection in collections"
                    :key="collection.id"
                    :href="portalPath(`/collections/${collection.slug}`)"
                    class="rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 p-5 shadow-sm transition hover:border-blue-300"
                >
                    <h3 class="font-semibold text-slate-900 dark:text-slate-100">{{ collection.name }}</h3>
                    <p v-if="collection.description" class="mt-1 text-sm text-slate-600 dark:text-slate-400">{{ collection.description }}</p>
                    <p class="mt-3 text-xs text-slate-500 dark:text-slate-400">{{ collection.articles_count }} articles</p>
                </Link>
            </div>
        </section>

        <section v-if="featured?.length">
            <h2 class="mb-4 text-xl font-semibold text-slate-900 dark:text-slate-100">{{ $t('portal.popular_articles') }}</h2>
            <div class="space-y-3">
                <Link
                    v-for="article in featured"
                    :key="article.id"
                    :href="portalPath(`/articles/${article.slug}`)"
                    class="block rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 p-5 shadow-sm hover:border-blue-300"
                >
                    <h3 class="font-medium text-blue-600">{{ article.title }}</h3>
                    <p v-if="article.excerpt" class="mt-1 text-sm text-slate-600 dark:text-slate-400">{{ article.excerpt }}</p>
                </Link>
            </div>
        </section>
    </PortalLayout>
</template>
