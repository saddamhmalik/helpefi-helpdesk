<script setup>
import { Head, Link } from '@inertiajs/vue3';
import KnowledgeBody from '../../Components/KnowledgeBody.vue';
import PortalLayout from '../../Layouts/PortalLayout.vue';

defineProps({
    article: Object,
});
</script>

<template>
    <Head :title="article.title" />
    <PortalLayout>
        <div class="mx-auto max-w-3xl">
            <div class="mb-8">
                <Link
                    v-if="article.collection"
                    :href="`/portal/collections/${article.collection.slug}`"
                    class="inline-flex items-center gap-1 text-sm font-medium text-blue-600 hover:text-blue-700"
                >
                    ← {{ article.collection.name }}
                </Link>
                <Link v-else href="/portal" class="inline-flex items-center gap-1 text-sm font-medium text-blue-600 hover:text-blue-700">← Help Center</Link>

                <h1 class="mt-4 text-3xl font-bold tracking-tight text-slate-900 sm:text-4xl">{{ article.title }}</h1>
                <p v-if="article.excerpt" class="mt-3 text-lg leading-relaxed text-slate-600">{{ article.excerpt }}</p>
            </div>

            <article class="rounded-2xl border border-slate-200 bg-white px-6 py-8 shadow-sm sm:px-10 sm:py-10">
                <KnowledgeBody :content="article.body" />
            </article>

            <div class="mt-8 rounded-2xl border border-slate-200 bg-gradient-to-b from-white to-slate-50 px-6 py-8 text-center shadow-sm">
                <p class="text-base font-medium text-slate-900">Still need help?</p>
                <p class="mt-1 text-sm text-slate-600">Our team is ready to assist you.</p>
                <div class="mt-4 flex flex-wrap justify-center gap-3">
                    <Link href="/portal/submit" class="rounded-lg bg-blue-600 px-5 py-2.5 text-sm font-medium text-white hover:bg-blue-700">Submit a request</Link>
                    <Link href="/portal/track" class="rounded-lg border border-slate-300 bg-white px-5 py-2.5 text-sm font-medium text-slate-700 hover:bg-slate-50">Track a request</Link>
                </div>
            </div>
        </div>
    </PortalLayout>
</template>
