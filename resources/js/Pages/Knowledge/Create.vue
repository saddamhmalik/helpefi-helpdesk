<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import AgentLayout from '../../Layouts/AgentLayout.vue';
import FormRichTextField from '../../Components/FormRichTextField.vue';
import FormField from '../../Components/FormField.vue';
import FormPage from '../../Components/FormPage.vue';
import FormSection from '../../Components/FormSection.vue';
import { formInputClass, formSelectClass } from '../../composables/useFormControls.js';

defineProps({
    categories: Array,
    collections: Array,
});

const form = useForm({
    title: '',
    excerpt: '',
    body: '',
    knowledge_category_id: '',
    knowledge_collection_id: '',
    is_published: false,
});

const submit = () => form.post('/knowledge');
</script>

<template>
    <Head title="New article" />
    <AgentLayout>
        <FormPage
            description="Write help content for agents and customers. You can publish immediately or save as a draft."
            cancel-href="/knowledge"
            submit-label="Save article"
            :processing="form.processing"
            max-width="lg"
            @submit="submit"
        >
            <FormSection title="Article">
                <FormField label="Title" required :error="form.errors.title">
                    <input v-model="form.title" type="text" :class="formInputClass" placeholder="How to reset your password" required />
                </FormField>
                <FormField label="Excerpt" hint="Short summary shown in search results and article lists." :error="form.errors.excerpt">
                    <input v-model="form.excerpt" type="text" :class="formInputClass" placeholder="One-line summary" />
                </FormField>
                <FormRichTextField
                    v-model="form.body"
                    label="Body"
                    required
                    :error="form.errors.body"
                    placeholder="Write the full article content…"
                />
            </FormSection>

            <FormSection title="Organization">
                <div class="grid gap-5 sm:grid-cols-2">
                    <FormField label="Collection" :error="form.errors.knowledge_collection_id">
                        <select v-model="form.knowledge_collection_id" :class="formSelectClass">
                            <option value="">No collection</option>
                            <option v-for="collection in collections" :key="collection.id" :value="collection.id">{{ collection.name }}</option>
                        </select>
                    </FormField>
                    <FormField label="Category" :error="form.errors.knowledge_category_id">
                        <select v-model="form.knowledge_category_id" :class="formSelectClass">
                            <option value="">Uncategorized</option>
                            <option v-for="category in categories" :key="category.id" :value="category.id">{{ category.name }}</option>
                        </select>
                    </FormField>
                </div>
                <label class="flex cursor-pointer items-center gap-3 rounded-xl border border-slate-200 bg-slate-50/50 px-4 py-3 transition hover:bg-slate-50">
                    <input
                        v-model="form.is_published"
                        type="checkbox"
                        class="h-4 w-4 rounded border-slate-300 text-blue-600 focus:ring-blue-500/20"
                    >
                    <span>
                        <span class="block text-sm font-medium text-slate-900">Publish immediately</span>
                        <span class="block text-xs text-slate-500">Make this article visible in the help center right away.</span>
                    </span>
                </label>
            </FormSection>
        </FormPage>
    </AgentLayout>
</template>
