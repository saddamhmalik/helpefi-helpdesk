<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import AgentLayout from '../../Layouts/AgentLayout.vue';
import FormPage from '../../Components/FormPage.vue';
import FormSection from '../../Components/FormSection.vue';
import { formSelectClass } from '../../composables/useFormControls.js';

const props = defineProps({
    kb_locales: Array,
    kb_default_locale: String,
    locale_choices: Array,
});

const { t } = useI18n();

const form = useForm({
    kb_locales: [...(props.kb_locales ?? ['en'])],
    kb_default_locale: props.kb_default_locale ?? 'en',
});

const toggleLocale = (code) => {
    if (form.kb_locales.includes(code)) {
        form.kb_locales = form.kb_locales.filter((locale) => locale !== code);

        if (form.kb_default_locale === code) {
            form.kb_default_locale = form.kb_locales[0] ?? 'en';
        }

        return;
    }

    form.kb_locales = [...form.kb_locales, code];
};

const submit = () => form.put('/knowledge/settings');
</script>

<template>
    <Head :title="$t('settings_knowledge.knowledge_base_settings')" />
    <AgentLayout>
        <FormPage
            :title="$t('settings_knowledge.knowledge_base_locales')"
            :description="$t('settings_knowledge.choose_which_languages_appear_in_the_help_center_and_agent_knowledge_b')"
            cancel-href="/knowledge"
            :submit-label="$t('settings_knowledge.save_locales')"
            :processing="form.processing"
            max-width="lg"
            @submit="submit"
        >
            <FormSection :title="$t('settings_knowledge.enabled_languages')">
                <div class="grid gap-2 sm:grid-cols-2">
                    <label
                        v-for="choice in locale_choices"
                        :key="choice.code"
                        class="flex cursor-pointer items-center gap-3 rounded-xl border agent-border px-4 py-3 transition agent-hover-surface"
                    >
                        <input
                            type="checkbox"
                            class="rounded agent-border text-blue-600"
                            :checked="form.kb_locales.includes(choice.code)"
                            @change="toggleLocale(choice.code)"
                        >
                        <span class="text-sm font-medium agent-text">{{ choice.label }}</span>
                        <span class="ml-auto text-xs uppercase text-slate-400 dark:text-slate-500">{{ choice.code }}</span>
                    </label>
                </div>
            </FormSection>

            <FormSection :title="$t('settings_knowledge.default_language')">
                <select v-model="form.kb_default_locale" :class="formSelectClass">
                    <option v-for="locale in form.kb_locales" :key="locale" :value="locale">
                        {{ locale_choices.find((choice) => choice.code === locale)?.label || locale }}
                    </option>
                </select>
            </FormSection>
        </FormPage>
    </AgentLayout>
</template>
