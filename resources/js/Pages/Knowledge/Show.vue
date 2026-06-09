<script setup>
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import AgentLayout from '../../Layouts/AgentLayout.vue';
import FormRichTextField from '../../Components/FormRichTextField.vue';
import FormField from '../../Components/FormField.vue';
import { formInputClass, formSelectClass } from '../../composables/useFormControls.js';

const props = defineProps({
    article: Object,
    categories: Array,
    collections: Array,
    versions: Array,
});

const form = useForm({
    title: props.article.title,
    excerpt: props.article.excerpt,
    body: props.article.body,
    knowledge_category_id: props.article.knowledge_category_id,
    knowledge_collection_id: props.article.knowledge_collection_id,
    is_published: props.article.is_published,
});

const submit = () => form.put(`/knowledge/${props.article.id}`);

const restoreVersion = (versionId) => {
    router.post(`/knowledge/${props.article.id}/versions/${versionId}/restore`);
};
</script>

<template>
    <Head :title="article.title" />
    <AgentLayout>
        <div class="mb-4 flex items-center justify-between">
            <Link href="/knowledge" class="text-sm text-blue-600 hover:text-blue-700">← Back to knowledge base</Link>
            <a v-if="article.is_published" :href="`/portal/articles/${article.slug}`" target="_blank" class="text-sm text-blue-600 hover:text-blue-700">View on portal</a>
        </div>

        <div class="grid gap-6 lg:grid-cols-3">
            <div class="lg:col-span-2">
                <div class="rounded-xl border border-slate-200 bg-white p-8 shadow-sm">
                    <form class="space-y-4" @submit.prevent="submit">
                        <div>
                            <label class="mb-1 block text-sm font-medium text-slate-700">Title</label>
                            <input v-model="form.title" type="text" :class="formInputClass" required />
                        </div>
                        <div>
                            <label class="mb-1 block text-sm font-medium text-slate-700">Collection</label>
                            <select v-model="form.knowledge_collection_id" :class="formSelectClass">
                                <option value="">No collection</option>
                                <option v-for="collection in collections" :key="collection.id" :value="collection.id">{{ collection.name }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="mb-1 block text-sm font-medium text-slate-700">Category</label>
                            <select v-model="form.knowledge_category_id" :class="formSelectClass">
                                <option value="">Uncategorized</option>
                                <option v-for="category in categories" :key="category.id" :value="category.id">{{ category.name }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="mb-1 block text-sm font-medium text-slate-700">Excerpt</label>
                            <input v-model="form.excerpt" type="text" :class="formInputClass" />
                        </div>
                        <FormRichTextField
                            v-model="form.body"
                            label="Body"
                            required
                            :error="form.errors.body"
                        />
                        <label class="flex items-center gap-2 text-sm text-slate-600">
                            <input v-model="form.is_published" type="checkbox" class="rounded border-slate-300" />
                            Published
                        </label>
                        <button type="submit" class="rounded-lg bg-blue-600 px-4 py-2 font-medium text-white hover:bg-blue-700" :disabled="form.processing">Update article</button>
                    </form>
                </div>
            </div>

            <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
                <h2 class="text-lg font-semibold text-slate-900">Version history</h2>
                <div class="mt-4 space-y-3">
                    <div v-for="version in versions" :key="version.id" class="rounded-lg border border-slate-100 p-3 text-sm">
                        <div class="flex items-center justify-between gap-2">
                            <span class="font-medium text-slate-900">v{{ version.version_number }}</span>
                            <button type="button" class="text-blue-600 hover:text-blue-700" @click="restoreVersion(version.id)">Restore</button>
                        </div>
                        <p class="mt-1 truncate text-slate-600">{{ version.title }}</p>
                        <p class="mt-1 text-xs text-slate-500">{{ version.user?.name || 'Unknown' }} · {{ new Date(version.created_at).toLocaleString() }}</p>
                    </div>
                    <p v-if="!versions?.length" class="text-sm text-slate-500">No previous versions yet.</p>
                </div>
            </div>
        </div>
    </AgentLayout>
</template>
