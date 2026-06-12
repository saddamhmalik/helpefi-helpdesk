<script setup>
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import AgentLayout from '../../Layouts/AgentLayout.vue';
import AppModal from '../../Components/AppModal.vue';
import FormRichTextField from '../../Components/FormRichTextField.vue';
import { formInputClass, formSelectClass } from '../../composables/useFormControls.js';
import { useI18n } from 'vue-i18n';
import { useDateTime } from '../../composables/useDateTime.js';

const props = defineProps({
    article: Object,
    categories: Array,
    collections: Array,
    versions: Array,
    translations: Array,
    locales: Array,
});

const { formatDateTime } = useDateTime();

const { t } = useI18n();

const form = useForm({
    title: props.article.title,
    excerpt: props.article.excerpt,
    body: props.article.body,
    knowledge_category_id: props.article.knowledge_category_id,
    knowledge_collection_id: props.article.knowledge_collection_id,
    is_published: props.article.is_published,
});

const translationForm = useForm({
    locale: '',
    title: '',
    excerpt: '',
    body: '',
    is_published: false,
});

const showTranslationModal = ref(false);

const availableTranslationLocales = computed(() => {
    const existing = new Set((props.translations ?? []).map((item) => item.locale));

    return (props.locales ?? []).filter((locale) => !existing.has(locale.code));
});

const submit = () => form.put(`/knowledge/${props.article.id}`);

const restoreVersion = (versionId) => {
    router.post(`/knowledge/${props.article.id}/versions/${versionId}/restore`);
};

const openTranslationModal = () => {
    translationForm.reset();
    translationForm.locale = availableTranslationLocales.value[0]?.code ?? '';
    showTranslationModal.value = true;
};

const createTranslation = () => {
    translationForm.post(`/knowledge/${props.article.id}/translations`, {
        onSuccess: () => {
            showTranslationModal.value = false;
        },
    });
};

const portalArticleUrl = computed(() => {
    const helpCenter = usePage().props.helpCenter;
    if (!helpCenter?.brandSlug) {
        return null;
    }

    const locale = props.article.locale || 'en';

    return `/portal/${helpCenter.brandSlug}/articles/${props.article.slug}?lang=${locale}`;
});
</script>

<template>
    <Head :title="`${article.title} · ${$t('knowledge.edit')}`" />
    <AgentLayout>
        <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
            <Link href="/knowledge" class="text-sm text-blue-600 hover:text-blue-700 dark:hover:text-blue-300 dark:text-blue-300">{{ $t('knowledge.back_to_knowledge_base') }}</Link>
            <div class="flex flex-wrap items-center gap-3">
                <Link :href="`/knowledge/${article.id}`" class="text-sm font-medium text-slate-700 dark:text-slate-300 hover:text-slate-900 dark:text-slate-100 dark:hover:text-slate-100">{{ $t('knowledge.view') }}</Link>
                <a
                    v-if="article.is_published && portalArticleUrl"
                    :href="portalArticleUrl"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="text-sm text-blue-600 hover:text-blue-700 dark:hover:text-blue-300 dark:text-blue-300"
                >{{ $t('knowledge.view_on_portal') }}</a>
            </div>
        </div>

        <div class="grid gap-6 lg:grid-cols-3">
            <div class="lg:col-span-2">
                <div class="rounded-xl border agent-border agent-panel p-8 shadow-sm">
                    <div class="mb-4 flex items-center gap-2">
                        <span class="rounded-full bg-slate-100 dark:bg-slate-900 px-2.5 py-0.5 text-xs font-semibold uppercase agent-text-muted">{{ article.locale || 'en' }}</span>
                    </div>
                    <form class="space-y-4" @submit.prevent="submit">
                        <div>
                            <label class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-300">{{ $t('common.title') }}</label>
                            <input v-model="form.title" type="text" :class="formInputClass" required />
                        </div>
                        <div>
                            <label class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-300">{{ $t('knowledge.collection') }}</label>
                            <select v-model="form.knowledge_collection_id" :class="formSelectClass">
                                <option value="">{{ $t('knowledge.no_collection') }}</option>
                                <option v-for="collection in collections" :key="collection.id" :value="collection.id">{{ collection.name }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-300">{{ $t('knowledge.category') }}</label>
                            <select v-model="form.knowledge_category_id" :class="formSelectClass">
                                <option value="">{{ $t('knowledge.uncategorized') }}</option>
                                <option v-for="category in categories" :key="category.id" :value="category.id">{{ category.name }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-300">{{ $t('knowledge.excerpt') }}</label>
                            <input v-model="form.excerpt" type="text" :class="formInputClass" />
                        </div>
                        <FormRichTextField
                            v-model="form.body"
                            :label="$t('knowledge.body')"
                            required
                            :error="form.errors.body"
                        />
                        <label class="flex items-center gap-2 text-sm agent-text-muted">
                            <input v-model="form.is_published" type="checkbox" class="rounded agent-border" />
                            Published
                        </label>
                        <button type="submit" class="rounded-lg bg-blue-600 px-4 py-2 font-medium text-white hover:bg-blue-700" :disabled="form.processing">{{ $t('knowledge.update_article') }}</button>
                    </form>
                </div>
            </div>

            <div class="space-y-6">
                <div class="agent-card">
                    <div class="flex items-center justify-between gap-2">
                        <h2 class="text-lg font-semibold agent-text">{{ $t('knowledge.translations') }}</h2>
                        <button
                            v-if="availableTranslationLocales.length"
                            type="button"
                            class="text-sm font-medium text-blue-600 hover:text-blue-700 dark:hover:text-blue-300 dark:text-blue-300"
                            @click="openTranslationModal"
                        >{{ $t('knowledge.add') }}</button>
                    </div>
                    <div class="mt-4 space-y-2">
                        <Link
                            v-for="translation in translations"
                            :key="translation.id"
                            :href="`/knowledge/${translation.id}/edit`"
                            class="flex items-center justify-between rounded-lg border px-3 py-2 text-sm transition"
                            :class="translation.id === article.id ? 'border-blue-200 dark:border-blue-900/60 bg-blue-50 dark:bg-blue-950/40 text-blue-700 dark:text-blue-300' : 'agent-border-subtle agent-hover-surface'"
                        >
                            <span class="font-medium uppercase">{{ translation.locale }}</span>
                            <span class="truncate agent-text-muted">{{ translation.title }}</span>
                        </Link>
                    </div>
                </div>

                <div class="agent-card">
                    <h2 class="text-lg font-semibold agent-text">{{ $t('knowledge.version_history') }}</h2>
                    <div class="mt-4 space-y-3">
                        <div v-for="version in versions" :key="version.id" class="rounded-lg border agent-border-subtle p-3 text-sm">
                            <div class="flex items-center justify-between gap-2">
                                <span class="font-medium agent-text">v{{ version.version_number }}</span>
                                <button type="button" class="text-blue-600 hover:text-blue-700 dark:hover:text-blue-300 dark:text-blue-300" @click="restoreVersion(version.id)">{{ $t('knowledge.restore') }}</button>
                            </div>
                            <p class="mt-1 truncate agent-text-muted">{{ version.title }}</p>
                            <p class="mt-1 text-xs agent-text-subtle">{{ version.user?.name || 'Unknown' }} · {{ formatDateTime(version.created_at) }}</p>
                        </div>
                        <p v-if="!versions?.length" class="text-sm agent-text-subtle">{{ $t('knowledge.no_previous_versions_yet') }}</p>
                    </div>
                </div>
            </div>
        </div>

        <AppModal
            :open="showTranslationModal"
            :title="$t('knowledge.add_translation')"
            :description="$t('knowledge.create_a_localized_version_linked_to_this_article_group')"
            @close="showTranslationModal = false"
        >
            <form id="translation-form" class="space-y-4" @submit.prevent="createTranslation">
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-300">{{ $t('knowledge.language') }}</label>
                    <select v-model="translationForm.locale" :class="formSelectClass" required>
                        <option v-for="locale in availableTranslationLocales" :key="locale.code" :value="locale.code">{{ locale.label }}</option>
                    </select>
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-300">{{ $t('common.title') }}</label>
                    <input v-model="translationForm.title" type="text" :class="formInputClass" required />
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-300">{{ $t('knowledge.excerpt') }}</label>
                    <input v-model="translationForm.excerpt" type="text" :class="formInputClass" />
                </div>
                <FormRichTextField
                    v-model="translationForm.body"
                    :label="$t('knowledge.body')"
                    required
                    :error="translationForm.errors.body"
                />
                <label class="flex items-center gap-2 text-sm agent-text-muted">
                    <input v-model="translationForm.is_published" type="checkbox" class="rounded agent-border" />
                    Published
                </label>
            </form>

            <template #footer>
                <div class="flex justify-end gap-2">
                    <button type="button" class="rounded-lg border agent-border px-4 py-2 text-sm font-medium text-slate-700 dark:text-slate-300" @click="showTranslationModal = false">{{ $t('knowledge.cancel') }}</button>
                    <button type="submit" form="translation-form" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700" :disabled="translationForm.processing">{{ $t('knowledge.create_translation') }}</button>
                </div>
            </template>
        </AppModal>
    </AgentLayout>
</template>
