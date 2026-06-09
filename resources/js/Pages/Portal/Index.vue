<script setup>
import { Head, Link } from '@inertiajs/vue3';
import PortalLayout from '../../Layouts/PortalLayout.vue';

defineProps({
    collections: Array,
    featured: Array,
});
</script>

<template>
    <Head title="Help Center" />
    <PortalLayout>
        <div class="mb-10 text-center">
            <h1 class="text-3xl font-bold text-slate-900">How can we help?</h1>
            <p class="mt-2 text-slate-600">Browse articles, request services, or submit a support ticket.</p>
            <div class="mx-auto mt-6 flex max-w-xl flex-wrap justify-center gap-2">
                <Link href="/portal/services" class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Browse services</Link>
                <Link href="/portal/submit" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">Submit request</Link>
            </div>
            <form action="/portal/search" method="get" class="mx-auto mt-4 flex max-w-xl gap-2">
                <input name="q" type="search" placeholder="Search articles..." class="flex-1 rounded-lg border border-slate-300 px-4 py-2" />
                <button type="submit" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">Search</button>
            </form>
        </div>

        <section v-if="collections?.length" class="mb-10">
            <h2 class="mb-4 text-xl font-semibold text-slate-900">Collections</h2>
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <Link
                    v-for="collection in collections"
                    :key="collection.id"
                    :href="`/portal/collections/${collection.slug}`"
                    class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm transition hover:border-blue-300"
                >
                    <h3 class="font-semibold text-slate-900">{{ collection.name }}</h3>
                    <p v-if="collection.description" class="mt-1 text-sm text-slate-600">{{ collection.description }}</p>
                    <p class="mt-3 text-xs text-slate-500">{{ collection.articles_count }} articles</p>
                </Link>
            </div>
        </section>

        <section v-if="featured?.length">
            <h2 class="mb-4 text-xl font-semibold text-slate-900">Popular articles</h2>
            <div class="space-y-3">
                <Link
                    v-for="article in featured"
                    :key="article.id"
                    :href="`/portal/articles/${article.slug}`"
                    class="block rounded-xl border border-slate-200 bg-white p-5 shadow-sm hover:border-blue-300"
                >
                    <h3 class="font-medium text-blue-600">{{ article.title }}</h3>
                    <p v-if="article.excerpt" class="mt-1 text-sm text-slate-600">{{ article.excerpt }}</p>
                </Link>
            </div>
        </section>
    </PortalLayout>
</template>
