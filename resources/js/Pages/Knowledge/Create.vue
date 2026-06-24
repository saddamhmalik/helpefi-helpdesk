<script setup>
import { Head, useForm, router } from '@inertiajs/vue3';
import { onMounted } from 'vue';
import AgentLayout from '../../Layouts/AgentLayout.vue';
import FormRichTextField from '../../Components/FormRichTextField.vue';
import FormField from '../../Components/FormField.vue';
import FormPage from '../../Components/FormPage.vue';
import FormSection from '../../Components/FormSection.vue';
import { formInputClass, formSelectClass } from '../../composables/useFormControls.js';
import { useI18n } from 'vue-i18n';

const props = defineProps({
    categories: Array,
    collections: Array,
    locales: Array,
    defaultLocale: String,
});

const { t } = useI18n();

const form = useForm({
    title: '',
    excerpt: '',
    body: '',
    knowledge_category_id: '',
    knowledge_collection_id: '',
    is_published: false,
    locale: props.defaultLocale ?? 'en',
});

onMounted(() => {
    if (!form.locale) {
        form.locale = props.defaultLocale ?? 'en';
    }
});

const submit = () => form.post('/knowledge', {
    onSuccess: () => router.reload({ only: ['helpCenter'] }),
});
</script>

<template>
    <Head :title="$t('knowledge.new_article')" />
    <AgentLayout>
        <FormPage
            :description="$t('knowledge.write_help_content_for_agents_and_customers_you_can_publish_immediatel')"
            cancel-href="/knowledge"
            :submit-label="$t('knowledge.save_article')"
            :processing="form.processing"
            max-width="lg"
            @submit="submit"
        >
            <FormSection :title="$t('knowledge.article')">
                <FormField :label="$t('common.title')" required :error="form.errors.title">
                    <input v-model="form.title" type="text" :class="formInputClass" :placeholder="$t('knowledge.how_to_reset_your_password')" required />
                </FormField>
                <FormField :label="$t('knowledge.excerpt')" hint="Short summary shown in search results and article lists." :error="form.errors.excerpt">
                    <input v-model="form.excerpt" type="text" :class="formInputClass" :placeholder="$t('knowledge.one-line_summary')" />
                </FormField>
                <FormRichTextField
                    v-model="form.body"
                    :label="$t('knowledge.body')"
                    required
                    :error="form.errors.body"
                    :placeholder="$t('knowledge.write_the_full_article_content_ellipsis')"
                />
            </FormSection>

            <FormSection :title="$t('knowledge.organization')">
                <div class="grid gap-5 sm:grid-cols-2">
                    <FormField :label="$t('knowledge.language')" :error="form.errors.locale">
                        <select v-model="form.locale" :class="formSelectClass">
                            <option v-for="locale in locales" :key="locale.code" :value="locale.code">{{ locale.label }}</option>
                        </select>
                    </FormField>
                    <FormField :label="$t('knowledge.collection')" :error="form.errors.knowledge_collection_id">
                        <select v-model="form.knowledge_collection_id" :class="formSelectClass">
                            <option value="">{{ $t('knowledge.no_collection') }}</option>
                            <option v-for="collection in collections" :key="collection.id" :value="collection.id">{{ collection.name }}</option>
                        </select>
                    </FormField>
                    <FormField :label="$t('knowledge.category')" :error="form.errors.knowledge_category_id">
                        <select v-model="form.knowledge_category_id" :class="formSelectClass">
                            <option value="">{{ $t('knowledge.uncategorized') }}</option>
                            <option v-for="category in categories" :key="category.id" :value="category.id">{{ category.name }}</option>
                        </select>
                    </FormField>
                </div>
                <label class="flex cursor-pointer items-center gap-3 rounded-xl border agent-border agent-panel-muted/50 px-4 py-3 transition agent-hover-surface">
                    <input
                        v-model="form.is_published"
                        type="checkbox"
                        class="h-4 w-4 rounded agent-border text-blue-600 focus:ring-blue-500/20"
                    >
                    <span>
                        <span class="block text-sm font-medium agent-text">{{ $t('knowledge.publish_immediately') }}</span>
                        <span class="block text-xs agent-text-subtle">{{ $t('knowledge.make_this_article_visible_in_the_help_center_right_away') }}</span>
                    </span>
                </label>
            </FormSection>
        </FormPage>
    </AgentLayout>
</template>
