<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import AgentLayout from '../../Layouts/AgentLayout.vue';
import FormField from '../../Components/FormField.vue';
import FormPage from '../../Components/FormPage.vue';
import FormSection from '../../Components/FormSection.vue';
import { formInputClass, formSelectClass, formTextareaClass } from '../../composables/useFormControls.js';

const props = defineProps({
    meta: Object,
    contacts: Array,
    organizations: Array,
    parentOptions: Array,
});

const form = useForm({
    asset_type_id: props.meta.types[0]?.id ?? '',
    parent_id: '',
    name: '',
    serial_number: '',
    status: 'in_stock',
    contact_id: '',
    organization_id: '',
    location: '',
    purchased_at: '',
    warranty_expires_at: '',
    notes: '',
});

const submit = () => form.post('/assets');
</script>

<template>
    <Head title="New asset" />
    <AgentLayout>
        <FormPage
            description="Track hardware or configuration items in your CMDB."
            cancel-href="/assets"
            submit-label="Create asset"
            :processing="form.processing"
            max-width="md"
            @submit="submit"
        >
            <FormSection title="Asset identity">
                <div class="grid gap-5 sm:grid-cols-2">
                    <FormField label="Type" required :error="form.errors.asset_type_id">
                        <select v-model="form.asset_type_id" required :class="formSelectClass">
                            <option v-for="type in meta.types" :key="type.id" :value="type.id">{{ type.name }}</option>
                        </select>
                    </FormField>
                    <FormField label="Status" :error="form.errors.status">
                        <select v-model="form.status" :class="formSelectClass">
                            <option v-for="status in meta.statuses" :key="status.value" :value="status.value">{{ status.label }}</option>
                        </select>
                    </FormField>
                </div>
                <FormField label="Name" required :error="form.errors.name">
                    <input v-model="form.name" type="text" required :class="formInputClass" placeholder="MacBook Pro 14&quot;" />
                </FormField>
                <div class="grid gap-5 sm:grid-cols-2">
                    <FormField label="Serial number" :error="form.errors.serial_number">
                        <input v-model="form.serial_number" type="text" :class="formInputClass" placeholder="SN-000000" />
                    </FormField>
                    <FormField label="Location" :error="form.errors.location">
                        <input v-model="form.location" type="text" :class="formInputClass" placeholder="Office, rack, or desk" />
                    </FormField>
                </div>
            </FormSection>

            <FormSection title="Assignment">
                <div class="grid gap-5 sm:grid-cols-2">
                    <FormField label="Assigned contact" :error="form.errors.contact_id">
                        <select v-model="form.contact_id" :class="formSelectClass">
                            <option value="">Unassigned</option>
                            <option v-for="contact in contacts" :key="contact.id" :value="contact.id">{{ contact.name }}</option>
                        </select>
                    </FormField>
                    <FormField label="Organization" :error="form.errors.organization_id">
                        <select v-model="form.organization_id" :class="formSelectClass">
                            <option value="">No organization</option>
                            <option v-for="org in organizations" :key="org.id" :value="org.id">{{ org.name }}</option>
                        </select>
                    </FormField>
                </div>
                <FormField label="Parent asset" :error="form.errors.parent_id">
                    <select v-model="form.parent_id" :class="formSelectClass">
                        <option value="">No parent</option>
                        <option v-for="parent in parentOptions" :key="parent.id" :value="parent.id">{{ parent.asset_tag }} — {{ parent.name }}</option>
                    </select>
                </FormField>
            </FormSection>

            <FormSection title="Lifecycle">
                <div class="grid gap-5 sm:grid-cols-2">
                    <FormField label="Purchased" :error="form.errors.purchased_at">
                        <input v-model="form.purchased_at" type="date" :class="formInputClass" />
                    </FormField>
                    <FormField label="Warranty expires" :error="form.errors.warranty_expires_at">
                        <input v-model="form.warranty_expires_at" type="date" :class="formInputClass" />
                    </FormField>
                </div>
                <FormField label="Notes" :error="form.errors.notes">
                    <textarea v-model="form.notes" rows="4" :class="formTextareaClass" placeholder="Optional maintenance or configuration notes" />
                </FormField>
            </FormSection>
        </FormPage>
    </AgentLayout>
</template>
