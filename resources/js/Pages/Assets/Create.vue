<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import AgentLayout from '../../Layouts/AgentLayout.vue';
import AssetsNav from '../../Components/AssetsNav.vue';
import FormField from '../../Components/FormField.vue';
import FormPage from '../../Components/FormPage.vue';
import FormSection from '../../Components/FormSection.vue';
import { formInputClass, formSelectClass, formTextareaClass } from '../../composables/useFormControls.js';
import { useI18n } from 'vue-i18n';

const props = defineProps({
    meta: Object,
    contacts: Array,
    organizations: Array,
    parentOptions: Array,
});

const { t } = useI18n();

const form = useForm({
    asset_type_id: props.meta.types[0]?.id ?? '',
    parent_id: '',
    name: '',
    serial_number: '',
    status: 'in_stock',
    contact_id: '',
    organization_id: '',
    location: '',
    ip_address: '',
    mac_address: '',
    hostname: '',
    manufacturer: '',
    model: '',
    vendor: '',
    purchase_cost: '',
    purchased_at: '',
    warranty_expires_at: '',
    notes: '',
});

const submit = () => form.post('/assets');
</script>

<template>
    <Head :title="$t('assets.new_asset')" />
    <AgentLayout>
        <AssetsNav />
        <FormPage
            :description="$t('assets.track_hardware_or_configuration_items_in_your_cmdb')"
            cancel-href="/assets"
            :submit-label="$t('assets.create_asset')"
            :processing="form.processing"
            max-width="md"
            @submit="submit"
        >
            <FormSection :title="$t('assets.asset_identity')">
                <div class="grid gap-5 sm:grid-cols-2">
                    <FormField :label="$t('assets.type')" required :error="form.errors.asset_type_id">
                        <select v-model="form.asset_type_id" required :class="formSelectClass">
                            <option v-for="type in meta.types" :key="type.id" :value="type.id">{{ type.name }}</option>
                        </select>
                        <p class="mt-1.5 text-xs text-slate-500 dark:text-slate-400">
                            <a href="/assets/types" class="text-blue-600 hover:text-blue-700 dark:hover:text-blue-300 dark:text-blue-300">{{ $t('assets.manage_asset_types') }}</a>
                            to add Printer, Router, and other categories.
                        </p>
                    </FormField>
                    <FormField :label="$t('assets.status')" :error="form.errors.status">
                        <select v-model="form.status" :class="formSelectClass">
                            <option v-for="status in meta.statuses" :key="status.value" :value="status.value">{{ status.label }}</option>
                        </select>
                    </FormField>
                </div>
                <FormField :label="$t('assets.name')" required :error="form.errors.name">
                    <input v-model="form.name" type="text" required :class="formInputClass" placeholder="MacBook Pro 14&quot;" />
                </FormField>
                <div class="grid gap-5 sm:grid-cols-2">
                    <FormField :label="$t('assets.serial_number')" :error="form.errors.serial_number">
                        <input v-model="form.serial_number" type="text" :class="formInputClass" :placeholder="$t('assets.sn-000000')" />
                    </FormField>
                    <FormField :label="$t('assets.location')" :error="form.errors.location">
                        <input v-model="form.location" type="text" :class="formInputClass" :placeholder="$t('assets.office_rack_or_desk')" />
                    </FormField>
                </div>
            </FormSection>

            <FormSection :title="$t('assets.network')">
                <div class="grid gap-5 sm:grid-cols-3">
                    <FormField :label="$t('assets.ip_address')" :error="form.errors.ip_address">
                        <input v-model="form.ip_address" type="text" :class="formInputClass" placeholder="10.0.0.12" />
                    </FormField>
                    <FormField :label="$t('assets.mac_address')" :error="form.errors.mac_address">
                        <input v-model="form.mac_address" type="text" :class="formInputClass" :placeholder="$t('assets.aa_bb_cc_dd_ee_ff')" />
                    </FormField>
                    <FormField :label="$t('assets.hostname')" :error="form.errors.hostname">
                        <input v-model="form.hostname" type="text" :class="formInputClass" :placeholder="$t('assets.laptop-01')" />
                    </FormField>
                </div>
            </FormSection>

            <FormSection :title="$t('assets.assignment')">
                <div class="grid gap-5 sm:grid-cols-2">
                    <FormField :label="$t('assets.assigned_contact')" :error="form.errors.contact_id">
                        <select v-model="form.contact_id" :class="formSelectClass">
                            <option value="">{{ $t('assets.unassigned') }}</option>
                            <option v-for="contact in contacts" :key="contact.id" :value="contact.id">{{ contact.name }}</option>
                        </select>
                    </FormField>
                    <FormField :label="$t('assets.organization')" :error="form.errors.organization_id">
                        <select v-model="form.organization_id" :class="formSelectClass">
                            <option value="">{{ $t('assets.no_organization') }}</option>
                            <option v-for="org in organizations" :key="org.id" :value="org.id">{{ org.name }}</option>
                        </select>
                    </FormField>
                </div>
                <FormField :label="$t('assets.parent_asset')" :error="form.errors.parent_id">
                    <select v-model="form.parent_id" :class="formSelectClass">
                        <option value="">{{ $t('assets.no_parent') }}</option>
                        <option v-for="parent in parentOptions" :key="parent.id" :value="parent.id">{{ parent.asset_tag }} — {{ parent.name }}</option>
                    </select>
                </FormField>
            </FormSection>

            <FormSection :title="$t('assets.procurement')">
                <div class="grid gap-5 sm:grid-cols-3">
                    <FormField :label="$t('assets.manufacturer')" :error="form.errors.manufacturer">
                        <input v-model="form.manufacturer" type="text" :class="formInputClass" :placeholder="$t('assets.dell')" />
                    </FormField>
                    <FormField :label="$t('assets.model')" :error="form.errors.model">
                        <input v-model="form.model" type="text" :class="formInputClass" :placeholder="$t('assets.latitude_7440')" />
                    </FormField>
                    <FormField :label="$t('assets.vendor')" :error="form.errors.vendor">
                        <input v-model="form.vendor" type="text" :class="formInputClass" :placeholder="$t('assets.cdw')" />
                    </FormField>
                </div>
                <div class="grid gap-5 sm:grid-cols-3">
                    <FormField :label="$t('assets.purchase_cost')" :error="form.errors.purchase_cost">
                        <input v-model="form.purchase_cost" type="number" min="0" step="0.01" :class="formInputClass" placeholder="1299.00" />
                    </FormField>
                    <FormField :label="$t('assets.purchased')" :error="form.errors.purchased_at">
                        <input v-model="form.purchased_at" type="date" :class="formInputClass" />
                    </FormField>
                    <FormField :label="$t('assets.warranty_expires')" :error="form.errors.warranty_expires_at">
                        <input v-model="form.warranty_expires_at" type="date" :class="formInputClass" />
                    </FormField>
                </div>
                <FormField :label="$t('assets.notes')" :error="form.errors.notes">
                    <textarea v-model="form.notes" rows="4" :class="formTextareaClass" :placeholder="$t('assets.optional_maintenance_or_configuration_notes')" />
                </FormField>
            </FormSection>
        </FormPage>
    </AgentLayout>
</template>
