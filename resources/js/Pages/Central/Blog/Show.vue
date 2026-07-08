<script setup>
import { Link, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import CentralLayout from '../../../Layouts/CentralLayout.vue';
import { formatMarketingTemplate } from '../../../composables/useMarketingEnglish.js';
import MarketingImage from '../../../Components/MarketingImage.vue';
import MarkdownContent from '../../../Components/MarkdownContent.vue';
import CentralBreadcrumbs from '../../../Components/Central/CentralBreadcrumbs.vue';
import CtaSection from '../../../Components/Central/CtaSection.vue';
import RelatedArticles from '../../../Components/Central/RelatedArticles.vue';

const props = defineProps({
    brand: { type: String, default: 'helpefi' },
    trialDays: { type: Number, default: 14 },
    post: { type: Object, required: true },
    relatedPosts: { type: Array, default: () => [] },
    recommendedPosts: { type: Array, default: () => [] },
    internalLinks: { type: Array, default: () => [] },
    featurePages: { type: Array, default: () => [] },
    socialLinks: { type: Array, default: () => [] },
});

const page = usePage();
const blog = computed(() => page.props.marketingChrome?.blog ?? {});
const blogText = (key, params = {}) => formatMarketingTemplate(blog.value[key] ?? key, { days: props.trialDays, ...params });
const platformName = computed(() => props.brand);

const toc = ref([]);
const setToc = (items) => {
    toc.value = Array.isArray(items) ? items : [];
};
</script>

<template>
    <CentralLayout :brand="brand" :trial-days="trialDays" :social-links="socialLinks">
        <article class="bg-white dark:bg-slate-900">
            <header class="bg-slate-950 py-16 text-white sm:py-20">
                <div class="mx-auto max-w-3xl px-4 sm:px-6 lg:px-8">
                    <CentralBreadcrumbs />
                    <div class="flex flex-wrap items-center gap-3 text-xs text-slate-400">
                        <time v-if="post.published_at" :datetime="post.published_at">{{ post.published_at }}</time>
                        <span v-if="post.reading_minutes">{{ post.reading_minutes }} min read</span>
                        <span v-if="post.author?.name">By {{ post.author.name }}</span>
                    </div>
                    <h1 class="mt-4 text-3xl font-extrabold tracking-tight sm:text-4xl">{{ post.title }}</h1>
                    <p class="mt-4 text-lg text-slate-300">{{ post.excerpt }}</p>

                    <div v-if="post.featured_image" class="mt-8 overflow-hidden rounded-2xl border border-white/10">
                        <MarketingImage :src="post.featured_image" :alt="post.title" :widths="[640, 960, 1280]" sizes="(max-width: 768px) 100vw, 720px" priority width="960" height="540" />
                    </div>

                    <div v-if="(post.categories?.length ?? 0) + (post.tags?.length ?? 0) > 0" class="mt-6 flex flex-wrap items-center gap-2">
                        <span v-if="post.categories?.length" class="text-xs font-semibold text-slate-200">Categories:</span>
                        <Link
                            v-for="cat in post.categories"
                            :key="cat.slug"
                            :href="`/blog?category=${encodeURIComponent(cat.slug)}`"
                            class="rounded-full bg-white/10 px-3 py-1 text-xs font-medium text-slate-100 transition hover:bg-white/15"
                        >
                            {{ cat.name }}
                        </Link>
                        <span v-if="post.tags?.length" class="text-xs font-semibold text-slate-200 ml-2">Tags:</span>
                        <Link
                            v-for="tag in post.tags"
                            :key="tag.slug"
                            :href="`/blog?tags=${encodeURIComponent(tag.slug)}`"
                            class="rounded-full bg-white/10 px-3 py-1 text-xs font-medium text-slate-100 transition hover:bg-white/15"
                        >
                            {{ tag.name }}
                        </Link>
                    </div>
                </div>
            </header>

            <div class="mx-auto max-w-6xl px-4 py-12 sm:px-6 lg:px-8">
                <div class="grid gap-10 lg:grid-cols-[1fr_280px]">
                    <div>
                        <MarkdownContent :content="post.body" :toc-min-level="2" :toc-max-level="3" @toc="setToc" />
                    </div>

                    <aside v-if="toc.length" class="lg:sticky lg:top-24">
                        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                            <h2 class="text-sm font-semibold text-slate-900 dark:text-slate-100">On this page</h2>
                            <ul class="mt-3 space-y-2 text-sm">
                                <li v-for="item in toc" :key="item.id">
                                    <a
                                        :href="`#${item.id}`"
                                        :class="[
                                            item.level === 3 ? 'ml-3' : '',
                                            'block rounded-lg px-2 py-1 text-slate-700 transition hover:bg-slate-100 dark:text-slate-200 dark:hover:bg-slate-800'
                                        ]"
                                    >
                                        {{ item.text }}
                                    </a>
                                </li>
                            </ul>
                        </div>
                    </aside>
                </div>

                <RelatedArticles
                    :items="internalLinks"
                    :title="blogText('continue_exploring')"
                    variant="links"
                    accent="blue"
                />

                <RelatedArticles
                    v-if="featurePages.length"
                    :items="featurePages.slice(0, 4)"
                    title="Related product features"
                    variant="links"
                />

                <RelatedArticles
                    v-if="relatedPosts.length"
                    :items="relatedPosts"
                    title="Related articles"
                    variant="posts"
                />

                <RelatedArticles
                    v-if="recommendedPosts.length"
                    :items="recommendedPosts"
                    title="Recommended articles"
                    variant="posts"
                />
            </div>
        </article>

        <CtaSection
            :title="blogText('cta_title')"
            :body="blogText('cta_body')"
            :primary-label="`Start ${trialDays}-day free trial`"
            primary-href="/register"
        />
    </CentralLayout>
</template>
