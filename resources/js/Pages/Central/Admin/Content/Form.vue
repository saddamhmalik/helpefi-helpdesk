<script setup>
import { computed, ref, watch } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import AdminLayout from '../../../../Layouts/AdminLayout.vue';
import { adminInputClass } from '../../../../composables/usePlatformAdmin.js';

const props = defineProps({
    draft: { type: Object, default: null },
    options: { type: Object, default: () => ({}) },
});

const isEditing = computed(() => props.draft !== null);
const isPublished = computed(() => props.draft?.status === 'published');

const generator = useForm({
    content_type: 'feature',
    title: '',
    brief: '',
    slug: '',
    competitor: '',
    industry: '',
});

const editor = useForm({
    title: props.draft?.title ?? '',
    slug: props.draft?.slug ?? '',
    brief: props.draft?.brief ?? '',
    edited_content: JSON.stringify(props.draft?.effective_content ?? {}, null, 2),
    seo: JSON.stringify(props.draft?.seo ?? {}, null, 2),
    schema_markup: JSON.stringify(props.draft?.schema_markup ?? {}, null, 2),
    internal_links: JSON.stringify(props.draft?.internal_links ?? [], null, 2),
});

watch(() => props.draft, (draft) => {
    if (!draft) return;
    editor.title = draft.title ?? '';
    editor.slug = draft.slug ?? '';
    editor.brief = draft.brief ?? '';
    editor.edited_content = JSON.stringify(draft.effective_content ?? {}, null, 2);
    editor.seo = JSON.stringify(draft.seo ?? {}, null, 2);
    editor.schema_markup = JSON.stringify(draft.schema_markup ?? {}, null, 2);
    editor.internal_links = JSON.stringify(draft.internal_links ?? [], null, 2);
});

const activeTab = ref('content');
const tabs = [
    { key: 'content', label: 'Page content' },
    { key: 'seo', label: 'SEO' },
    { key: 'schema', label: 'Schema' },
    { key: 'links', label: 'Internal links' },
];

const showCompetitor = computed(() => ['comparison'].includes(generator.content_type));
const showIndustry = computed(() => ['vertical'].includes(generator.content_type));
const showSlug = computed(() => generator.content_type !== 'blog_outline');

const generate = () => {
    generator.post('/admin/content');
};

const save = () => {
    editor.put(`/admin/content/${props.draft.id}`);
};

const regenerate = () => {
    if (!confirm('Regenerate content? Unsaved edits to generated content will be lost.')) return;
    router.post(`/admin/content/${props.draft.id}/regenerate`);
};

const publish = () => {
    if (!confirm('Publish this content? It will go live (or create a blog draft).')) return;
    router.post(`/admin/content/${props.draft.id}/publish`);
};

const destroyDraft = () => {
    if (!confirm('Delete this draft permanently?')) return;
    router.delete(`/admin/content/${props.draft.id}`);
};

const editorField = computed(() => {
    if (activeTab.value === 'seo') return 'seo';
    if (activeTab.value === 'schema') return 'schema_markup';
    if (activeTab.value === 'links') return 'internal_links';
    return 'edited_content';
});
</script>

<template>
    <Head :title="isEditing ? 'Edit content draft' : 'Generate content'" />
    <AdminLayout>
        <div class="mx-auto max-w-5xl px-4 py-8 sm:px-6">
            <div class="mb-8">
                <Link href="/admin/content" class="text-sm text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-300">← Back to AI content</Link>
                <h1 class="mt-2 text-2xl font-semibold text-slate-900 dark:text-slate-100">
                    {{ isEditing ? 'Edit draft' : 'Generate content' }}
                </h1>
                <p v-if="isEditing" class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                    {{ draft.content_type_label }} · {{ draft.status }}
                    <span v-if="draft.ai_source" class="ml-2 font-mono text-xs">via {{ draft.ai_source }}</span>
                </p>
            </div>

            <form v-if="!isEditing" class="space-y-6 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900" @submit.prevent="generate">
                <h2 class="text-sm font-semibold text-slate-900 dark:text-slate-100">Generation brief</h2>
                <p class="text-sm text-slate-600 dark:text-slate-400">
                    Content follows EEAT principles and is checked against your existing site corpus to avoid duplicates.
                </p>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-slate-700 dark:text-slate-300">Content type</label>
                        <select v-model="generator.content_type" :class="adminInputClass">
                            <option v-for="type in options.content_types ?? []" :key="type.value" :value="type.value">{{ type.label }}</option>
                        </select>
                    </div>
                    <div v-if="showSlug">
                        <label class="mb-1.5 block text-sm font-medium text-slate-700 dark:text-slate-300">Slug (optional)</label>
                        <input v-model="generator.slug" type="text" :class="adminInputClass" placeholder="auto-generated from title" />
                    </div>
                </div>

                <div>
                    <label class="mb-1.5 block text-sm font-medium text-slate-700 dark:text-slate-300">Title</label>
                    <input v-model="generator.title" type="text" :class="adminInputClass" />
                    <p v-if="generator.errors.title" class="mt-1 text-xs text-red-600">{{ generator.errors.title }}</p>
                </div>

                <div v-if="showCompetitor">
                    <label class="mb-1.5 block text-sm font-medium text-slate-700 dark:text-slate-300">Competitor name</label>
                    <input v-model="generator.competitor" type="text" :class="adminInputClass" />
                </div>

                <div v-if="showIndustry">
                    <label class="mb-1.5 block text-sm font-medium text-slate-700 dark:text-slate-300">Industry</label>
                    <input v-model="generator.industry" type="text" :class="adminInputClass" />
                </div>

                <div>
                    <label class="mb-1.5 block text-sm font-medium text-slate-700 dark:text-slate-300">Brief</label>
                    <textarea v-model="generator.brief" rows="6" :class="adminInputClass" placeholder="Audience, angle, key differentiators, and what to avoid duplicating..." />
                    <p v-if="generator.errors.brief" class="mt-1 text-xs text-red-600">{{ generator.errors.brief }}</p>
                </div>

                <button type="submit" class="rounded-xl bg-slate-900 px-5 py-2.5 text-sm font-semibold text-white hover:bg-slate-800 dark:bg-slate-700" :disabled="generator.processing">
                    Generate draft
                </button>
            </form>

            <div v-else class="space-y-6">
                <div v-if="draft.duplicate_warnings?.length" class="rounded-2xl border border-amber-200 bg-amber-50 p-4 dark:border-amber-900/50 dark:bg-amber-950/30">
                    <p class="text-sm font-semibold text-amber-900 dark:text-amber-200">Duplicate content check</p>
                    <ul class="mt-2 space-y-1 text-sm text-amber-800 dark:text-amber-300">
                        <li v-for="(warning, index) in draft.duplicate_warnings" :key="index">
                            {{ warning.message }} ({{ Math.round((warning.similarity ?? 0) * 100) }}% — {{ warning.title }})
                        </li>
                    </ul>
                </div>

                <div v-if="draft.published_reference" class="rounded-2xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-900 dark:border-emerald-900/50 dark:bg-emerald-950/30 dark:text-emerald-200">
                    Published as {{ draft.published_reference.type }}
                    <span v-if="draft.published_reference.path"> · {{ draft.published_reference.path }}</span>
                </div>

                <form class="rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900" @submit.prevent="save">
                    <div class="border-b border-slate-200 px-6 pt-6 dark:border-slate-800">
                        <div class="grid gap-4 sm:grid-cols-2">
                            <div>
                                <label class="mb-1.5 block text-sm font-medium text-slate-700 dark:text-slate-300">Title</label>
                                <input v-model="editor.title" type="text" :class="adminInputClass" :disabled="isPublished" />
                            </div>
                            <div>
                                <label class="mb-1.5 block text-sm font-medium text-slate-700 dark:text-slate-300">Slug</label>
                                <input v-model="editor.slug" type="text" :class="adminInputClass" :disabled="isPublished" />
                            </div>
                        </div>
                        <div class="mt-4">
                            <label class="mb-1.5 block text-sm font-medium text-slate-700 dark:text-slate-300">Brief</label>
                            <textarea v-model="editor.brief" rows="3" :class="adminInputClass" :disabled="isPublished" />
                        </div>
                    </div>

                    <div class="flex gap-1 border-b border-slate-200 px-6 dark:border-slate-800">
                        <button
                            v-for="tab in tabs"
                            :key="tab.key"
                            type="button"
                            class="px-4 py-3 text-sm font-medium"
                            :class="activeTab === tab.key ? 'border-b-2 border-blue-600 text-blue-600' : 'text-slate-500 hover:text-slate-700 dark:text-slate-400'"
                            @click="activeTab = tab.key"
                        >
                            {{ tab.label }}
                        </button>
                    </div>

                    <div class="p-6">
                        <textarea
                            v-model="editor[editorField]"
                            rows="18"
                            :class="adminInputClass + ' font-mono text-xs'"
                            :disabled="isPublished"
                        />
                        <p v-if="editor.errors.edited_content" class="mt-1 text-xs text-red-600">{{ editor.errors.edited_content }}</p>
                    </div>

                    <div v-if="!isPublished" class="flex flex-wrap items-center gap-3 border-t border-slate-200 px-6 py-4 dark:border-slate-800">
                        <button type="submit" class="rounded-xl bg-blue-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-blue-700" :disabled="editor.processing">
                            Save edits
                        </button>
                        <button type="button" class="rounded-xl border border-slate-200 px-5 py-2.5 text-sm font-medium text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-200" @click="regenerate">
                            Regenerate
                        </button>
                        <button type="button" class="rounded-xl bg-emerald-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-emerald-700" @click="publish">
                            Publish
                        </button>
                        <button type="button" class="ml-auto text-sm text-red-600 hover:text-red-700" @click="destroyDraft">
                            Delete
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </AdminLayout>
</template>
