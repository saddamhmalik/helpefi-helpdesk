<script setup>
import { Head, Link } from '@inertiajs/vue3';
import PortalLayout from '../../Layouts/PortalLayout.vue';

defineProps({
    collection: Object,
    articles: Object,
});
</script>

<template>
    <Head :title="collection.name" />
    <PortalLayout>
        <div class="mb-6">
            <Link href="/portal" class="text-sm text-blue-600 hover:text-blue-700">← Help Center</Link>
            <h1 class="mt-2 text-2xl font-bold text-slate-900">{{ collection.name }}</h1>
            <p v-if="collection.description" class="mt-1 text-slate-600">{{ collection.description }}</p>
        </div>

        <div class="space-y-3">
            <Link
                v-for="article in articles.data"
                :key="article.id"
                :href="`/portal/articles/${article.slug}`"
                class="block rounded-xl border border-slate-200 bg-white p-5 shadow-sm hover:border-blue-300"
            >
                <h2 class="font-medium text-blue-600">{{ article.title }}</h2>
                <p v-if="article.excerpt" class="mt-1 text-sm text-slate-600">{{ article.excerpt }}</p>
            </Link>
            <p v-if="!articles.data?.length" class="text-sm text-slate-500">No articles in this collection yet.</p>
        </div>
    </PortalLayout>
</template>
