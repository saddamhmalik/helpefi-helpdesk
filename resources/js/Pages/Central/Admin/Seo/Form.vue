<script setup>
import { computed } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import AdminLayout from '../../../../Layouts/AdminLayout.vue';
import { adminInputClass } from '../../../../composables/usePlatformAdmin.js';

const props = defineProps({
    entry: { type: Object, required: true },
});

const pageKey = computed(() => props.entry.page_key);

const form = useForm({
    manual_seo_title: props.entry.manual?.seo_title ?? '',
    manual_meta_description: props.entry.manual?.meta_description ?? '',
    manual_keywords: props.entry.manual?.keywords ?? '',
    manual_og_description: props.entry.manual?.og_description ?? '',
    manual_twitter_description: props.entry.manual?.twitter_description ?? '',
});

const generator = useForm({
    content: props.entry.source_content ?? '',
});

const submit = () => {
    form.put(`/admin/seo/${pageKey.value}`);
};

const generate = () => {
    generator.post(`/admin/seo/${pageKey.value}/generate`);
};
</script>

<template>
    <Head title="Edit SEO metadata" />
    <AdminLayout>
        <div class="mx-auto max-w-4xl px-4 py-8 sm:px-6">
            <div class="mb-8">
                <Link href="/admin/seo" class="text-sm text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-300">← Back to SEO metadata</Link>
                <h1 class="mt-2 text-2xl font-semibold text-slate-900 dark:text-slate-100">SEO metadata</h1>
                <p class="mt-1 font-mono text-xs text-slate-500 dark:text-slate-400">{{ pageKey }}</p>
            </div>

            <div class="space-y-6">
                <form class="space-y-6 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900" @submit.prevent="submit">
                    <h2 class="text-sm font-semibold text-slate-900 dark:text-slate-100">Manual overrides</h2>

                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-slate-700 dark:text-slate-300">SEO title</label>
                        <input v-model="form.manual_seo_title" type="text" :class="adminInputClass" />
                        <p v-if="form.errors.manual_seo_title" class="mt-1 text-xs text-red-600">{{ form.errors.manual_seo_title }}</p>
                    </div>

                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-slate-700 dark:text-slate-300">Meta description</label>
                        <textarea v-model="form.manual_meta_description" rows="3" :class="adminInputClass" />
                        <p v-if="form.errors.manual_meta_description" class="mt-1 text-xs text-red-600">{{ form.errors.manual_meta_description }}</p>
                    </div>

                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-slate-700 dark:text-slate-300">Keywords</label>
                        <input v-model="form.manual_keywords" type="text" :class="adminInputClass" placeholder="comma, separated, keywords" />
                        <p v-if="form.errors.manual_keywords" class="mt-1 text-xs text-red-600">{{ form.errors.manual_keywords }}</p>
                    </div>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-slate-700 dark:text-slate-300">Open Graph description</label>
                            <textarea v-model="form.manual_og_description" rows="3" :class="adminInputClass" />
                            <p v-if="form.errors.manual_og_description" class="mt-1 text-xs text-red-600">{{ form.errors.manual_og_description }}</p>
                        </div>
                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-slate-700 dark:text-slate-300">Twitter description</label>
                            <textarea v-model="form.manual_twitter_description" rows="3" :class="adminInputClass" />
                            <p v-if="form.errors.manual_twitter_description" class="mt-1 text-xs text-red-600">{{ form.errors.manual_twitter_description }}</p>
                        </div>
                    </div>

                    <div class="flex items-center gap-3">
                        <button type="submit" class="rounded-xl bg-blue-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-blue-700" :disabled="form.processing">
                            Save
                        </button>
                        <Link href="/admin/seo" class="text-sm text-slate-500 hover:text-slate-700 dark:text-slate-400">Cancel</Link>
                    </div>
                </form>

                <form class="space-y-6 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900" @submit.prevent="generate">
                    <h2 class="text-sm font-semibold text-slate-900 dark:text-slate-100">AI generation</h2>
                    <p class="text-sm text-slate-600 dark:text-slate-400">Paste the page content (or a summary). The generated metadata is stored and used as fallback when manual fields are empty.</p>

                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-slate-700 dark:text-slate-300">Page content</label>
                        <textarea v-model="generator.content" rows="10" :class="adminInputClass" />
                        <p v-if="generator.errors.content" class="mt-1 text-xs text-red-600">{{ generator.errors.content }}</p>
                    </div>

                    <div class="flex items-center gap-3">
                        <button type="submit" class="rounded-xl bg-slate-900 px-5 py-2.5 text-sm font-semibold text-white hover:bg-slate-800 dark:bg-slate-700 dark:hover:bg-slate-600" :disabled="generator.processing">
                            Generate
                        </button>
                    </div>

                    <div v-if="entry.ai?.seo_title || entry.ai?.meta_description" class="rounded-xl border border-slate-200 bg-slate-50 p-4 text-sm text-slate-700 dark:border-slate-800 dark:bg-slate-950 dark:text-slate-200">
                        <p class="font-semibold">Latest AI metadata</p>
                        <p class="mt-2"><span class="font-medium">Title:</span> {{ entry.ai?.seo_title || '—' }}</p>
                        <p class="mt-1"><span class="font-medium">Description:</span> {{ entry.ai?.meta_description || '—' }}</p>
                        <p class="mt-1"><span class="font-medium">Keywords:</span> {{ entry.ai?.keywords || '—' }}</p>
                        <p class="mt-1"><span class="font-medium">OG:</span> {{ entry.ai?.og_description || '—' }}</p>
                        <p class="mt-1"><span class="font-medium">Twitter:</span> {{ entry.ai?.twitter_description || '—' }}</p>
                        <p v-if="entry.ai?.slug_suggestions?.length" class="mt-2"><span class="font-medium">Slug suggestions:</span> {{ entry.ai.slug_suggestions.join(', ') }}</p>
                    </div>
                </form>
            </div>
        </div>
    </AdminLayout>
</template>

