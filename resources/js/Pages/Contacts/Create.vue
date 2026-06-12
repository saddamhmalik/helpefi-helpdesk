<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import AgentLayout from '../../Layouts/AgentLayout.vue';
import CustomFields from '../../Components/CustomFields.vue';
import FormField from '../../Components/FormField.vue';
import FormPage from '../../Components/FormPage.vue';
import FormSection from '../../Components/FormSection.vue';
import { formInputClass, formSelectClass } from '../../composables/useFormControls.js';
import { useI18n } from 'vue-i18n';

defineProps({
    organizations: Array,
    tags: Array,
    customFieldDefinitions: Array,
});

const { t } = useI18n();

const form = useForm({
    name: '',
    email: '',
    phone: '',
    organization_id: '',
    tag_ids: [],
    custom_fields: {},
});

const toggleTag = (tagId) => {
    const index = form.tag_ids.indexOf(tagId);

    if (index === -1) {
        form.tag_ids.push(tagId);
    } else {
        form.tag_ids.splice(index, 1);
    }
};

const submit = () => form.post('/contacts');
</script>

<template>
    <Head :title="$t('contacts.new_customer')" />
    <AgentLayout>
        <FormPage
            :description="$t('contacts.add_a_customer_profile_portal_access_can_be_granted_separately_after_c')"
            cancel-href="/contacts"
            :submit-label="$t('contacts.create_customer')"
            :processing="form.processing"
            max-width="sm"
            @submit="submit"
        >
            <FormSection :title="$t('contacts.profile')">
                <FormField :label="$t('contacts.name')" required :error="form.errors.name">
                    <input v-model="form.name" type="text" :class="formInputClass" :placeholder="$t('contacts.full_name')" required />
                </FormField>
                <FormField
                    :label="$t('contacts.email')"
                    hint="Auto-links to an organization when the email domain matches."
                    :error="form.errors.email"
                >
                    <input v-model="form.email" type="email" :class="formInputClass" :placeholder="$t('contacts.name_company_com')" />
                </FormField>
                <FormField :label="$t('contacts.phone')" :error="form.errors.phone">
                    <input v-model="form.phone" type="text" :class="formInputClass" placeholder="+1 555 000 0000" />
                </FormField>
            </FormSection>

            <FormSection :title="$t('contacts.organization_tags')">
                <FormField :label="$t('contacts.organization')" :error="form.errors.organization_id">
                    <select v-model="form.organization_id" :class="formSelectClass">
                        <option value="">{{ $t('contacts.no_organization') }}</option>
                        <option v-for="org in organizations" :key="org.id" :value="org.id">{{ org.name }}</option>
                    </select>
                </FormField>
                <FormField :label="$t('contacts.tags')" :error="form.errors.tag_ids">
                    <div class="flex flex-wrap gap-2">
                        <button
                            v-for="tag in tags"
                            :key="tag.id"
                            type="button"
                            class="rounded-full px-3 py-1.5 text-sm font-medium ring-1 ring-inset transition"
                            :class="form.tag_ids.includes(tag.id)
                                ? 'bg-blue-50 dark:bg-blue-950/40 text-blue-700 dark:text-blue-300 ring-blue-200'
                                : 'bg-white dark:bg-slate-900 text-slate-600 dark:text-slate-400 ring-slate-200 dark:ring-slate-700 hover:bg-slate-50 dark:hover:bg-slate-800'"
                            @click="toggleTag(tag.id)"
                        >
                            {{ tag.name }}
                        </button>
                    </div>
                </FormField>
            </FormSection>

            <CustomFields
                v-model="form.custom_fields"
                :definitions="customFieldDefinitions"
                :errors="form.errors"
            />
        </FormPage>
    </AgentLayout>
</template>
