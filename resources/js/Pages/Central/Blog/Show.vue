<script setup>
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import CentralLayout from '../../../Layouts/CentralLayout.vue';

const props = defineProps({
    brand: { type: String, default: 'helpefi' },
    trialDays: { type: Number, default: 14 },
    post: { type: Object, required: true },
    relatedPosts: { type: Array, default: () => [] },
    featurePages: { type: Array, default: () => [] },
    socialLinks: { type: Array, default: () => [] },
});

const { t } = useI18n();
const platformName = computed(() => t('app.name'));
</script>

<template>
    <CentralLayout :brand="brand" :trial-days="trialDays" :social-links="socialLinks">
        <article class="bg-white dark:bg-slate-900">
            <header class="bg-slate-950 py-16 text-white sm:py-20">
                <div class="mx-auto max-w-3xl px-4 sm:px-6 lg:px-8">
                    <nav class="mb-8 text-sm text-slate-400" aria-label="Breadcrumb">
                        <ol class="flex flex-wrap items-center gap-2">
                            <li><Link href="/" class="transition hover:text-white">{{ platformName }}</Link></li>
                            <li aria-hidden="true">/</li>
                            <li><Link href="/blog" class="transition hover:text-white">{{ $t('central.blog.index_nav_label') }}</Link></li>
                            <li aria-hidden="true">/</li>
                            <li class="text-slate-300">{{ post.title }}</li>
                        </ol>
                    </nav>
                    <div class="flex flex-wrap items-center gap-3 text-xs text-slate-400">
                        <time v-if="post.published_at" :datetime="post.published_at">{{ post.published_at }}</time>
                        <span v-if="post.reading_minutes">{{ post.reading_minutes }} min read</span>
                    </div>
                    <h1 class="mt-4 text-3xl font-extrabold tracking-tight sm:text-4xl">{{ post.title }}</h1>
                    <p class="mt-4 text-lg text-slate-300">{{ post.excerpt }}</p>
                </div>
            </header>

            <div class="mx-auto max-w-3xl px-4 py-12 sm:px-6 lg:px-8">
                <div class="prose prose-slate max-w-none dark:prose-invert">
                    <p v-for="(paragraph, index) in post.body_paragraphs" :key="index" class="mb-6 text-base leading-relaxed text-slate-700 dark:text-slate-300">{{ paragraph }}</p>
                </div>

                <section v-if="featurePages.length" class="mt-12 rounded-2xl border border-slate-200 bg-slate-50 p-6 dark:border-slate-800 dark:bg-slate-950">
                    <h2 class="text-lg font-semibold text-slate-900 dark:text-slate-100">Related product features</h2>
                    <div class="mt-4 flex flex-wrap gap-3">
                        <Link
                            v-for="feature in featurePages.slice(0, 4)"
                            :key="feature.slug"
                            :href="feature.path"
                            class="rounded-full border border-slate-200 bg-white px-4 py-2 text-sm font-medium text-slate-700 transition hover:border-blue-300 hover:text-blue-700 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200"
                        >
                            {{ $t(`central.feature_pages.${feature.slug}.nav_label`) }}
                        </Link>
                    </div>
                </section>

                <section v-if="relatedPosts.length" class="mt-12">
                    <h2 class="text-lg font-semibold text-slate-900 dark:text-slate-100">Related articles</h2>
                    <ul class="mt-4 space-y-3">
                        <li v-for="related in relatedPosts" :key="related.slug">
                            <Link :href="related.path" class="font-medium text-blue-600 hover:text-blue-700">{{ related.title }}</Link>
                        </li>
                    </ul>
                </section>
            </div>
        </article>

        <section class="bg-slate-950 py-16">
            <div class="mx-auto max-w-4xl px-4 text-center">
                <h2 class="text-3xl font-bold text-white">{{ $t('central.blog.cta_title') }}</h2>
                <p class="mt-4 text-lg text-slate-400">{{ $t('central.blog.cta_body', { days: trialDays }) }}</p>
                <Link href="/register" class="mt-8 inline-flex rounded-2xl bg-white px-10 py-4 text-sm font-bold text-slate-900">Start {{ trialDays }}-day free trial</Link>
            </div>
        </section>
    </CentralLayout>
</template>
