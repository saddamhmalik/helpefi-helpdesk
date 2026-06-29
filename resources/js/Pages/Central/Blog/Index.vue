<script setup>
import { Link, router, usePage } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import CentralLayout from '../../../Layouts/CentralLayout.vue';
import { formatMarketingTemplate } from '../../../composables/useMarketingEnglish.js';
import MarketingImage from '../../../Components/MarketingImage.vue';
import CentralBreadcrumbs from '../../../Components/Central/CentralBreadcrumbs.vue';

const props = defineProps({
    brand: { type: String, default: 'helpefi' },
    trialDays: { type: Number, default: 14 },
    posts: { type: Array, default: () => [] },
    pagination: { type: Object, default: () => ({ current_page: 1, last_page: 1 }) },
    filters: { type: Object, default: () => ({ q: null, category: null, tags: [] }) },
    availableCategories: { type: Array, default: () => [] },
    availableTags: { type: Array, default: () => [] },
    socialLinks: { type: Array, default: () => [] },
});

const page = usePage();
const blog = computed(() => page.props.marketingChrome?.blog ?? {});
const blogText = (key, params = {}) => formatMarketingTemplate(blog.value[key] ?? key, { days: props.trialDays, ...params });
const platformName = computed(() => props.brand);

const searchQuery = ref(props.filters?.q ?? '');
const selectedCategory = ref(props.filters?.category ?? '');
const selectedTags = ref(Array.isArray(props.filters?.tags) ? props.filters.tags : []);

watch(
    () => props.filters,
    (next) => {
        searchQuery.value = next?.q ?? '';
        selectedCategory.value = next?.category ?? '';
        selectedTags.value = Array.isArray(next?.tags) ? next.tags : [];
    },
    { immediate: true, deep: true },
);

const applyFilters = ({ q, category, tags, pageNumber } = {}) => {
    const payload = {};

    const qValue = q !== undefined ? q : searchQuery.value;
    const categoryValue = category !== undefined ? category : selectedCategory.value;
    const tagsValue = tags !== undefined ? tags : selectedTags.value;

    if (typeof qValue === 'string' && qValue.trim() !== '') payload.q = qValue.trim();
    if (typeof categoryValue === 'string' && categoryValue.trim() !== '') payload.category = categoryValue.trim();
    if (Array.isArray(tagsValue) && tagsValue.length > 0) payload.tags = tagsValue.join(',');
    if (typeof pageNumber === 'number' && pageNumber > 1) payload.page = pageNumber;

    router.get('/blog', payload, { preserveState: true, replace: true, preserveScroll: true });
};

const buildPageHref = (pageNumber) => {
    const params = new URLSearchParams();

    if (searchQuery.value && String(searchQuery.value).trim() !== '') {
        params.set('q', String(searchQuery.value).trim());
    }

    if (selectedCategory.value && String(selectedCategory.value).trim() !== '') {
        params.set('category', String(selectedCategory.value).trim());
    }

    if (selectedTags.value && selectedTags.value.length > 0) {
        params.set('tags', selectedTags.value.join(','));
    }

    if (pageNumber && pageNumber > 1) {
        params.set('page', String(pageNumber));
    }

    const query = params.toString();
    return query ? `/blog?${query}` : '/blog';
};

const pagination = computed(() => ({
    current: Number(props.pagination?.current_page ?? 1),
    last: Number(props.pagination?.last_page ?? 1),
}));

const pageButtons = computed(() => {
    const current = pagination.value.current;
    const last = pagination.value.last;
    if (last <= 1) return [];

    const maxButtons = 7;
    const buttons = new Set();
    buttons.add(1);
    buttons.add(last);

    for (let p = current - 2; p <= current + 2; p += 1) {
        if (p >= 1 && p <= last) buttons.add(p);
    }

    const sorted = Array.from(buttons).sort((a, b) => a - b);
    if (sorted.length <= maxButtons) return sorted;

    const center = current;
    const start = Math.max(2, Math.min(last - (maxButtons - 2), center - 2));
    const end = Math.min(last - 1, start + (maxButtons - 3));
    const range = [];
    for (let p = start; p <= end; p += 1) range.push(p);
    return [1, ...range, last];
});

const toggleTag = (slug) => {
    const exists = selectedTags.value.includes(slug);
    selectedTags.value = exists
        ? selectedTags.value.filter((t) => t !== slug)
        : [...selectedTags.value, slug];
    applyFilters({ tags: selectedTags.value, pageNumber: 1 });
};
</script>

<template>
    <CentralLayout :brand="brand" :trial-days="trialDays" :social-links="socialLinks">
        <section class="bg-slate-950 py-16 text-white sm:py-24">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <CentralBreadcrumbs />
                <h1 class="text-3xl font-extrabold tracking-tight sm:text-5xl">{{ blogText('index_title') }}</h1>
                <p class="mt-6 max-w-2xl text-lg text-slate-300">{{ blogText('index_subtitle') }}</p>
            </div>
        </section>

        <section class="bg-white py-16 dark:bg-slate-900 sm:py-20">
            <div class="mx-auto max-w-4xl px-4 sm:px-6 lg:px-8">
                <section class="mb-10 rounded-2xl border border-slate-200 bg-slate-50 p-6 dark:border-slate-800 dark:bg-slate-950">
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-slate-700 dark:text-slate-300">Search</label>
                            <input
                                v-model="searchQuery"
                                type="search"
                                class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 shadow-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100"
                                placeholder="Search articles…"
                                @change="applyFilters({ q: searchQuery, pageNumber: 1 })"
                            />
                        </div>
                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-slate-700 dark:text-slate-300">Category</label>
                            <select
                                v-model="selectedCategory"
                                class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 shadow-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100"
                                @change="applyFilters({ category: selectedCategory, pageNumber: 1 })"
                            >
                                <option value="">All categories</option>
                                <option v-for="cat in availableCategories" :key="cat.slug" :value="cat.slug">{{ cat.name }}</option>
                            </select>
                        </div>
                    </div>

                    <div v-if="availableTags.length" class="mt-5">
                        <div class="mb-2 text-sm font-medium text-slate-700 dark:text-slate-300">Tags</div>
                        <div class="flex flex-wrap gap-2">
                            <button
                                v-for="tag in availableTags"
                                :key="tag.slug"
                                type="button"
                                @click="toggleTag(tag.slug)"
                                :class="selectedTags.includes(tag.slug) ? 'rounded-full bg-blue-600 px-4 py-1.5 text-xs font-semibold text-white hover:bg-blue-700' : 'rounded-full border border-slate-200 bg-white px-4 py-1.5 text-xs font-semibold text-slate-700 hover:border-blue-300 hover:text-blue-700 dark:border-slate-800 dark:bg-slate-900 dark:text-slate-200 dark:hover:border-blue-700 dark:hover:text-blue-200'"
                            >
                                {{ tag.name }}
                            </button>
                        </div>
                    </div>
                </section>

                <div v-if="posts.length" class="space-y-8">
                    <article v-for="post in posts" :key="post.slug" class="rounded-2xl border border-slate-200 p-6 transition hover:border-blue-300 dark:border-slate-800 dark:hover:border-blue-700">
                        <div class="flex flex-wrap items-center gap-3 text-xs text-slate-500 dark:text-slate-400">
                            <time v-if="post.published_at" :datetime="post.published_at">{{ post.published_at }}</time>
                            <span v-if="post.reading_minutes">{{ post.reading_minutes }} min read</span>
                        </div>
                        <div v-if="post.featured_image" class="mt-4 overflow-hidden rounded-xl border border-slate-200/60 dark:border-slate-800">
                            <MarketingImage
                                :src="post.featured_image"
                                :alt="post.title"
                                :widths="[320, 480, 640, 768, 1024]"
                                sizes="(max-width: 768px) 100vw, 768px"
                                class="block h-44 w-full"
                            />
                        </div>
                        <h2 class="mt-3 text-2xl font-bold text-slate-900 dark:text-slate-100">
                            <Link :href="post.path" class="transition hover:text-blue-600">{{ post.title }}</Link>
                        </h2>
                        <p class="mt-3 text-sm leading-relaxed text-slate-600 dark:text-slate-400">{{ post.excerpt }}</p>

                        <div v-if="post.categories?.length" class="mt-4 flex flex-wrap gap-2">
                            <span v-for="cat in post.categories.slice(0, 3)" :key="cat.slug" class="rounded-full bg-blue-50 px-3 py-1 text-xs font-medium text-blue-700 dark:bg-blue-950/40 dark:text-blue-200">
                                {{ cat.name }}
                            </span>
                        </div>

                        <div v-if="post.tags?.length" class="mt-2 flex flex-wrap gap-2">
                            <span v-for="tag in post.tags.slice(0, 3)" :key="tag.slug" class="rounded-full bg-slate-100 px-3 py-1 text-xs font-medium text-slate-700 dark:bg-slate-800 dark:text-slate-200">
                                {{ tag.name }}
                            </span>
                        </div>

                        <Link :href="post.path" class="mt-4 inline-flex text-sm font-semibold text-blue-600 hover:text-blue-700">Read article</Link>
                    </article>
                </div>
                <p v-else class="text-slate-600 dark:text-slate-400">{{ blogText('empty_state') }}</p>

                <div v-if="pagination.last > 1" class="mt-10 flex flex-wrap items-center justify-center gap-2">
                    <Link
                        v-if="pagination.current > 1"
                        :href="buildPageHref(pagination.current - 1)"
                        class="rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-sm font-semibold text-slate-700 hover:bg-slate-50 dark:border-slate-800 dark:bg-slate-900 dark:text-slate-200 dark:hover:bg-slate-800"
                    >
                        Previous
                    </Link>
                    <Link
                        v-for="p in pageButtons"
                        :key="p"
                        :href="buildPageHref(p)"
                        class="rounded-lg border px-3 py-1.5 text-sm font-semibold transition"
                        :class="p === pagination.current ? 'border-blue-600 bg-blue-600 text-white' : 'border-slate-200 bg-white text-slate-700 hover:bg-slate-50 dark:border-slate-800 dark:bg-slate-900 dark:text-slate-200 dark:hover:bg-slate-800'"
                    >
                        {{ p }}
                    </Link>
                    <Link
                        v-if="pagination.current < pagination.last"
                        :href="buildPageHref(pagination.current + 1)"
                        class="rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-sm font-semibold text-slate-700 hover:bg-slate-50 dark:border-slate-800 dark:bg-slate-900 dark:text-slate-200 dark:hover:bg-slate-800"
                    >
                        Next
                    </Link>
                </div>
            </div>
        </section>
    </CentralLayout>
</template>
