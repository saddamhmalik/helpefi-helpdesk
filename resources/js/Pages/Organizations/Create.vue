<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import AgentLayout from '../../Layouts/AgentLayout.vue';
import FormField from '../../Components/FormField.vue';
import FormPage from '../../Components/FormPage.vue';
import FormSection from '../../Components/FormSection.vue';
import { formInputClass, formTextareaClass } from '../../composables/useFormControls.js';

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
    <Head title="New organization" />
    <AgentLayout>
        <FormPage
            description="Set up a company account. Email domains help auto-match incoming customers."
            cancel-href="/organizations"
            submit-label="Create organization"
            :processing="form.processing"
            max-width="sm"
            @submit="submit"
        >
            <FormSection title="Company details">
                <FormField label="Name" required :error="form.errors.name">
                    <input v-model="form.name" type="text" :class="formInputClass" placeholder="Acme Inc." required />
                </FormField>
                <FormField label="Website" :error="form.errors.website">
                    <input v-model="form.website" type="url" :class="formInputClass" placeholder="https://example.com" />
                </FormField>
                <FormField label="Phone" :error="form.errors.phone">
                    <input v-model="form.phone" type="text" :class="formInputClass" placeholder="+1 555 000 0000" />
                </FormField>
                <FormField label="Customer tier" :error="form.errors.customer_tier">
                    <select v-model="form.customer_tier" :class="formInputClass">
                        <option value="">Standard (default SLA)</option>
                        <option value="standard">Standard</option>
                        <option value="premium">Premium</option>
                        <option value="enterprise">Enterprise</option>
                    </select>
                </FormField>
                <FormField label="Description" :error="form.errors.description">
                    <textarea v-model="form.description" rows="4" :class="formTextareaClass" placeholder="Optional notes about this organization" />
                </FormField>
            </FormSection>

            <FormSection title="Email domains" description="Customers with matching email domains are linked automatically.">
                <div class="space-y-3">
                    <div v-for="(_, index) in form.domains" :key="index" class="flex items-end gap-2">
                        <FormField class="min-w-0 flex-1" :error="form.errors[`domains.${index}`]">
                            <input v-model="form.domains[index]" type="text" :class="formInputClass" placeholder="example.com" />
                        </FormField>
                        <button
                            v-if="form.domains.length > 1"
                            type="button"
                            class="mb-0.5 shrink-0 rounded-lg px-3 py-2.5 text-sm font-medium text-red-600 transition hover:bg-red-50"
                            @click="removeDomain(index)"
                        >
                            Remove
                        </button>
                    </div>
                    <button
                        type="button"
                        class="text-sm font-medium text-blue-600 transition hover:text-blue-700"
                        @click="addDomain"
                    >
                        + Add domain
                    </button>
                </div>
            </FormSection>
        </FormPage>
    </AgentLayout>
</template>
