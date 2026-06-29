<script setup>
import { computed, ref } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import AdminLayout from '../../../../Layouts/AdminLayout.vue';
import { adminInputClass } from '../../../../composables/usePlatformAdmin.js';

const props = defineProps({
    post: { type: Object, default: null },
    slugOptions: { type: Array, default: () => [] },
});

const isEditing = computed(() => props.post !== null);

const form = useForm({
    title: props.post?.title ?? '',
    slug: props.post?.slug ?? '',
    excerpt: props.post?.excerpt ?? '',
    body: props.post?.body ?? '',
    status: props.post?.status ?? 'draft',
    published_at: props.post?.published_at ?? '',
    seo_title: props.post?.seo_title ?? '',
    seo_description: props.post?.seo_description ?? '',
    og_image_url: props.post?.og_image_url ?? '',
    related_slugs: props.post?.related_slugs ?? [],
    category_slugs: props.post?.category_slugs ?? [],
    tag_slugs: props.post?.tag_slugs ?? [],
});

const categoriesInput = ref((Array.isArray(form.category_slugs) ? form.category_slugs : []).join(', '));
const tagsInput = ref((Array.isArray(form.tag_slugs) ? form.tag_slugs : []).join(', '));

const parseSlugList = (value) => {
    const raw = String(value ?? '');
    const parts = raw
        .split(',')
        .map((p) => p.trim())
        .filter(Boolean)
        .map((p) => p.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/-+/g, '-').replace(/^-|-$/g, ''));

    return Array.from(new Set(parts)).filter(Boolean);
};

const slugify = () => {
    if (isEditing.value || form.slug) {
        return;
    }

    form.slug = form.title
        .toLowerCase()
        .trim()
        .replace(/[^a-z0-9\s-]/g, '')
        .replace(/\s+/g, '-')
        .replace(/-+/g, '-');
};

const toggleRelated = (slug) => {
    if (form.related_slugs.includes(slug)) {
        form.related_slugs = form.related_slugs.filter((value) => value !== slug);
        return;
    }

    form.related_slugs = [...form.related_slugs, slug];
};

const submit = () => {
    form.category_slugs = parseSlugList(categoriesInput.value);
    form.tag_slugs = parseSlugList(tagsInput.value);

    if (isEditing.value) {
        form.put(`/admin/blog/${props.post.id}`);
        return;
    }

    form.post('/admin/blog');
};
</script>

<template>
    <Head :title="isEditing ? 'Edit blog post' : 'New blog post'" />
    <AdminLayout>
        <div class="mx-auto max-w-4xl px-4 py-8 sm:px-6">
            <div class="mb-8">
                <Link href="/admin/blog" class="text-sm text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-300">← Back to blog</Link>
                <h1 class="mt-2 text-2xl font-semibold text-slate-900 dark:text-slate-100">{{ isEditing ? 'Edit blog post' : 'New blog post' }}</h1>
                <p class="mt-1 text-sm text-slate-600 dark:text-slate-400">Separate paragraphs with a blank line. Published posts appear on the marketing site and sitemap.</p>
            </div>

            <form class="space-y-6 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900" @submit.prevent="submit">
                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="sm:col-span-2">
                        <label class="mb-1.5 block text-sm font-medium text-slate-700 dark:text-slate-300">Title</label>
                        <input v-model="form.title" type="text" required :class="adminInputClass" @blur="slugify" />
                        <p v-if="form.errors.title" class="mt-1 text-xs text-red-600">{{ form.errors.title }}</p>
                    </div>
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-slate-700 dark:text-slate-300">Slug</label>
                        <input v-model="form.slug" type="text" required :class="adminInputClass" />
                        <p class="mt-1 text-xs text-slate-500">URL: /blog/{{ form.slug || 'your-slug' }}</p>
                        <p v-if="form.errors.slug" class="mt-1 text-xs text-red-600">{{ form.errors.slug }}</p>
                    </div>
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-slate-700 dark:text-slate-300">Status</label>
                        <select v-model="form.status" :class="adminInputClass">
                            <option value="draft">Draft</option>
                            <option value="published">Published</option>
                        </select>
                        <p v-if="form.errors.status" class="mt-1 text-xs text-red-600">{{ form.errors.status }}</p>
                    </div>
                </div>

                <div>
                    <label class="mb-1.5 block text-sm font-medium text-slate-700 dark:text-slate-300">Excerpt</label>
                    <textarea v-model="form.excerpt" rows="3" required :class="adminInputClass" />
                    <p v-if="form.errors.excerpt" class="mt-1 text-xs text-red-600">{{ form.errors.excerpt }}</p>
                </div>

                <div>
                    <label class="mb-1.5 block text-sm font-medium text-slate-700 dark:text-slate-300">Body</label>
                    <textarea v-model="form.body" rows="16" required :class="adminInputClass" />
                    <p v-if="form.errors.body" class="mt-1 text-xs text-red-600">{{ form.errors.body }}</p>
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-slate-700 dark:text-slate-300">Publish date</label>
                        <input v-model="form.published_at" type="date" :class="adminInputClass" />
                        <p class="mt-1 text-xs text-slate-500">Leave empty to publish immediately when status is Published.</p>
                        <p v-if="form.errors.published_at" class="mt-1 text-xs text-red-600">{{ form.errors.published_at }}</p>
                    </div>
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-slate-700 dark:text-slate-300">OG image URL</label>
                        <input v-model="form.og_image_url" type="url" :class="adminInputClass" placeholder="https://helpefi.com/og-image.png" />
                        <p v-if="form.errors.og_image_url" class="mt-1 text-xs text-red-600">{{ form.errors.og_image_url }}</p>
                    </div>
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-slate-700 dark:text-slate-300">SEO title</label>
                        <input v-model="form.seo_title" type="text" :class="adminInputClass" />
                        <p v-if="form.errors.seo_title" class="mt-1 text-xs text-red-600">{{ form.errors.seo_title }}</p>
                    </div>
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-slate-700 dark:text-slate-300">SEO description</label>
                        <textarea v-model="form.seo_description" rows="3" :class="adminInputClass" />
                        <p v-if="form.errors.seo_description" class="mt-1 text-xs text-red-600">{{ form.errors.seo_description }}</p>
                    </div>
                </div>

                <div v-if="slugOptions.length">
                    <label class="mb-2 block text-sm font-medium text-slate-700 dark:text-slate-300">Related posts</label>
                    <div class="flex flex-wrap gap-2">
                        <label
                            v-for="option in slugOptions"
                            :key="option.slug"
                            class="inline-flex cursor-pointer items-center gap-2 rounded-full border px-3 py-1.5 text-sm"
                            :class="form.related_slugs.includes(option.slug) ? 'border-blue-300 bg-blue-50 text-blue-700 dark:border-blue-700 dark:bg-blue-950/40 dark:text-blue-200' : 'border-slate-200 text-slate-600 dark:border-slate-700 dark:text-slate-300'"
                        >
                            <input type="checkbox" class="rounded border-slate-300" :checked="form.related_slugs.includes(option.slug)" @change="toggleRelated(option.slug)" />
                            {{ option.title }}
                        </label>
                    </div>
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-slate-700 dark:text-slate-300">Categories (comma-separated slugs)</label>
                        <input v-model="categoriesInput" type="text" :class="adminInputClass" placeholder="support, itsm" />
                        <p v-if="form.errors.category_slugs" class="mt-1 text-xs text-red-600">{{ form.errors.category_slugs }}</p>
                    </div>
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-slate-700 dark:text-slate-300">Tags (comma-separated slugs)</label>
                        <input v-model="tagsInput" type="text" :class="adminInputClass" placeholder="ai-deflection, sla" />
                        <p v-if="form.errors.tag_slugs" class="mt-1 text-xs text-red-600">{{ form.errors.tag_slugs }}</p>
                    </div>
                </div>

                <div class="flex items-center gap-3">
                    <button type="submit" class="rounded-xl bg-blue-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-blue-700" :disabled="form.processing">
                        {{ isEditing ? 'Save changes' : 'Create post' }}
                    </button>
                    <Link href="/admin/blog" class="text-sm text-slate-500 hover:text-slate-700 dark:text-slate-400">Cancel</Link>
                </div>
            </form>
        </div>
    </AdminLayout>
</template>
