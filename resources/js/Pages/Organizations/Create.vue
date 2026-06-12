<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import AgentLayout from '../../Layouts/AgentLayout.vue';
import FormField from '../../Components/FormField.vue';
import FormPage from '../../Components/FormPage.vue';
import FormSection from '../../Components/FormSection.vue';
import { formInputClass, formTextareaClass } from '../../composables/useFormControls.js';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();

const form = useForm({
    name: '',
    website: '',
    phone: '',
    description: '',
    customer_tier: '',
    domains: [''],
});

const addDomain = () => form.domains.push('');
const removeDomain = (index) => form.domains.splice(index, 1);

const submit = () => form.post('/organizations');
</script>

<template>
    <Head :title="$t('organizations.new_organization')" />
    <AgentLayout>
        <FormPage
            :description="$t('organizations.set_up_a_company_account_email_domains_help_auto-match_incoming_custom')"
            cancel-href="/organizations"
            :submit-label="$t('organizations.create_organization')"
            :processing="form.processing"
            max-width="sm"
            @submit="submit"
        >
            <FormSection :title="$t('organizations.company_details')">
                <FormField :label="$t('organizations.name')" required :error="form.errors.name">
                    <input v-model="form.name" type="text" :class="formInputClass" :placeholder="$t('organizations.acme_inc')" required />
                </FormField>
                <FormField :label="$t('organizations.website')" :error="form.errors.website">
                    <input v-model="form.website" type="url" :class="formInputClass" placeholder="https://example.com" />
                </FormField>
                <FormField :label="$t('organizations.phone')" :error="form.errors.phone">
                    <input v-model="form.phone" type="text" :class="formInputClass" placeholder="+1 555 000 0000" />
                </FormField>
                <FormField :label="$t('organizations.customer_tier')" :error="form.errors.customer_tier">
                    <select v-model="form.customer_tier" :class="formInputClass">
                        <option value="">{{ $t('organizations.standard_default_sla') }}</option>
                        <option value="standard">{{ $t('organizations.standard') }}</option>
                        <option value="premium">{{ $t('organizations.premium') }}</option>
                        <option value="enterprise">{{ $t('organizations.enterprise') }}</option>
                    </select>
                </FormField>
                <FormField :label="$t('organizations.description')" :error="form.errors.description">
                    <textarea v-model="form.description" rows="4" :class="formTextareaClass" :placeholder="$t('organizations.optional_notes_about_this_organization')" />
                </FormField>
            </FormSection>

            <FormSection :title="$t('organizations.email_domains')" :description="$t('organizations.customers_with_matching_email_domains_are_linked_automatically')">
                <div class="space-y-3">
                    <div v-for="(_, index) in form.domains" :key="index" class="flex items-end gap-2">
                        <FormField class="min-w-0 flex-1" :error="form.errors[`domains.${index}`]">
                            <input v-model="form.domains[index]" type="text" :class="formInputClass" placeholder="example.com" />
                        </FormField>
                        <button
                            v-if="form.domains.length > 1"
                            type="button"
                            class="mb-0.5 shrink-0 rounded-lg px-3 py-2.5 text-sm font-medium text-red-600 transition hover:bg-red-50 dark:bg-red-950/40"
                            @click="removeDomain(index)"
                        >{{ $t('common.remove') }}</button>
                    </div>
                    <button
                        type="button"
                        class="text-sm font-medium text-blue-600 transition hover:text-blue-700 dark:hover:text-blue-300 dark:text-blue-300"
                        @click="addDomain"
                    >{{ $t('organizations.add_domain') }}</button>
                </div>
            </FormSection>
        </FormPage>
    </AgentLayout>
</template>
