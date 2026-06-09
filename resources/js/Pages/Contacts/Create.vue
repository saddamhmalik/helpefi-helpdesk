<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import AgentLayout from '../../Layouts/AgentLayout.vue';
import CustomFields from '../../Components/CustomFields.vue';
import FormField from '../../Components/FormField.vue';
import FormPage from '../../Components/FormPage.vue';
import FormSection from '../../Components/FormSection.vue';
import { formInputClass, formSelectClass } from '../../composables/useFormControls.js';

defineProps({
    organizations: Array,
    tags: Array,
    customFieldDefinitions: Array,
});

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
    <Head title="New customer" />
    <AgentLayout>
        <FormPage
            description="Add a customer profile. Portal access can be granted separately after creation."
            cancel-href="/contacts"
            submit-label="Create customer"
            :processing="form.processing"
            max-width="sm"
            @submit="submit"
        >
            <FormSection title="Profile">
                <FormField label="Name" required :error="form.errors.name">
                    <input v-model="form.name" type="text" :class="formInputClass" placeholder="Full name" required />
                </FormField>
                <FormField
                    label="Email"
                    hint="Auto-links to an organization when the email domain matches."
                    :error="form.errors.email"
                >
                    <input v-model="form.email" type="email" :class="formInputClass" placeholder="name@company.com" />
                </FormField>
                <FormField label="Phone" :error="form.errors.phone">
                    <input v-model="form.phone" type="text" :class="formInputClass" placeholder="+1 555 000 0000" />
                </FormField>
            </FormSection>

            <FormSection title="Organization & tags">
                <FormField label="Organization" :error="form.errors.organization_id">
                    <select v-model="form.organization_id" :class="formSelectClass">
                        <option value="">No organization</option>
                        <option v-for="org in organizations" :key="org.id" :value="org.id">{{ org.name }}</option>
                    </select>
                </FormField>
                <FormField label="Tags" :error="form.errors.tag_ids">
                    <div class="flex flex-wrap gap-2">
                        <button
                            v-for="tag in tags"
                            :key="tag.id"
                            type="button"
                            class="rounded-full px-3 py-1.5 text-sm font-medium ring-1 ring-inset transition"
                            :class="form.tag_ids.includes(tag.id)
                                ? 'bg-blue-50 text-blue-700 ring-blue-200'
                                : 'bg-white text-slate-600 ring-slate-200 hover:bg-slate-50'"
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
