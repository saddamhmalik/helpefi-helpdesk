<script setup>
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import CentralLayout from '../../../Layouts/CentralLayout.vue';
import { formatMarketingTemplate } from '../../../composables/useMarketingEnglish.js';

const props = defineProps({
    brand: { type: String, default: 'helpefi' },
    trialDays: { type: Number, default: 14 },
    posts: { type: Array, default: () => [] },
    socialLinks: { type: Array, default: () => [] },
});

const page = usePage();
const blog = computed(() => page.props.marketingChrome?.blog ?? {});
const blogText = (key, params = {}) => formatMarketingTemplate(blog.value[key] ?? key, { days: props.trialDays, ...params });
const platformName = computed(() => props.brand);
</script>

<template>
    <CentralLayout :brand="brand" :trial-days="trialDays" :social-links="socialLinks">
        <section class="bg-slate-950 py-16 text-white sm:py-24">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <nav class="mb-8 text-sm text-slate-400" aria-label="Breadcrumb">
                    <ol class="flex flex-wrap items-center gap-2">
                        <li><Link href="/" class="transition hover:text-white">{{ platformName }}</Link></li>
                        <li aria-hidden="true">/</li>
                        <li class="text-slate-300">{{ blogText('index_nav_label') }}</li>
                    </ol>
                </nav>
                <h1 class="text-3xl font-extrabold tracking-tight sm:text-5xl">{{ blogText('index_title') }}</h1>
                <p class="mt-6 max-w-2xl text-lg text-slate-300">{{ blogText('index_subtitle') }}</p>
            </div>
        </section>

        <section class="bg-white py-16 dark:bg-slate-900 sm:py-20">
            <div class="mx-auto max-w-4xl px-4 sm:px-6 lg:px-8">
                <div v-if="posts.length" class="space-y-8">
                    <article v-for="post in posts" :key="post.slug" class="rounded-2xl border border-slate-200 p-6 transition hover:border-blue-300 dark:border-slate-800 dark:hover:border-blue-700">
                        <div class="flex flex-wrap items-center gap-3 text-xs text-slate-500 dark:text-slate-400">
                            <time v-if="post.published_at" :datetime="post.published_at">{{ post.published_at }}</time>
                            <span v-if="post.reading_minutes">{{ post.reading_minutes }} min read</span>
                        </div>
                        <h2 class="mt-3 text-2xl font-bold text-slate-900 dark:text-slate-100">
                            <Link :href="post.path" class="transition hover:text-blue-600">{{ post.title }}</Link>
                        </h2>
                        <p class="mt-3 text-sm leading-relaxed text-slate-600 dark:text-slate-400">{{ post.excerpt }}</p>
                        <Link :href="post.path" class="mt-4 inline-flex text-sm font-semibold text-blue-600 hover:text-blue-700">Read article</Link>
                    </article>
                </div>
                <p v-else class="text-slate-600 dark:text-slate-400">{{ blogText('empty_state') }}</p>
            </div>
        </section>
    </CentralLayout>
</template>
