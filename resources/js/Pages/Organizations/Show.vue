<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import AgentLayout from '../../Layouts/AgentLayout.vue';
import { useI18n } from 'vue-i18n';

const props = defineProps({
    organization: Object,
});

const { t } = useI18n();

const form = useForm({
    name: props.organization.name,
    website: props.organization.website || '',
    phone: props.organization.phone || '',
    description: props.organization.description || '',
    customer_tier: props.organization.customer_tier || '',
    domains: props.organization.domains?.length ? props.organization.domains.map((d) => d.domain) : [''],
});

const addDomain = () => form.domains.push('');
const removeDomain = (index) => form.domains.splice(index, 1);

const submit = () => form.put(`/organizations/${props.organization.id}`);
</script>

<template>
    <Head :title="organization.name" />
    <AgentLayout>
        <div class="mb-4">
            <Link href="/organizations" class="text-sm text-blue-600 hover:text-blue-700">← Back to organizations</Link>
        </div>

        <div class="grid gap-6 lg:grid-cols-2">
            <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
                <h1 class="text-2xl font-semibold text-slate-900">{{ organization.name }}</h1>

                <form class="mt-6 space-y-4" @submit.prevent="submit">
                    <div>
                        <label class="mb-1 block text-sm font-medium text-slate-700">{{ $t('organizations.name') }}</label>
                        <input v-model="form.name" type="text" class="w-full rounded-lg border border-slate-300 px-3 py-2" required />
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-slate-700">{{ $t('organizations.website') }}</label>
                        <input v-model="form.website" type="url" class="w-full rounded-lg border border-slate-300 px-3 py-2" />
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-slate-700">{{ $t('organizations.phone') }}</label>
                        <input v-model="form.phone" type="text" class="w-full rounded-lg border border-slate-300 px-3 py-2" />
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-slate-700">{{ $t('organizations.customer_tier') }}</label>
                        <select v-model="form.customer_tier" class="w-full rounded-lg border border-slate-300 px-3 py-2">
                            <option value="">{{ $t('organizations.standard_default_sla') }}</option>
                            <option value="standard">{{ $t('organizations.standard') }}</option>
                            <option value="premium">{{ $t('organizations.premium') }}</option>
                            <option value="enterprise">{{ $t('organizations.enterprise') }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-slate-700">{{ $t('organizations.description') }}</label>
                        <textarea v-model="form.description" rows="3" class="w-full rounded-lg border border-slate-300 px-3 py-2" />
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-slate-700">{{ $t('organizations.email_domains') }}</label>
                        <div v-for="(_, index) in form.domains" :key="index" class="mb-2 flex gap-2">
                            <input v-model="form.domains[index]" type="text" class="flex-1 rounded-lg border border-slate-300 px-3 py-2" />
                            <button v-if="form.domains.length > 1" type="button" class="text-sm text-red-600" @click="removeDomain(index)">{{ $t('common.remove') }}</button>
                        </div>
                        <button type="button" class="text-sm text-blue-600" @click="addDomain">{{ $t('organizations.add_domain') }}</button>
                    </div>
                    <button type="submit" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700" :disabled="form.processing">{{ $t('organizations.update') }}</button>
                </form>
            </div>

            <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
                <h2 class="text-lg font-semibold text-slate-900">{{ $t('organizations.contacts') }}</h2>
                <ul class="mt-4 space-y-2">
                    <li v-for="contact in organization.contacts" :key="contact.id">
                        <Link :href="`/contacts/${contact.id}`" class="text-sm text-blue-600 hover:text-blue-700">{{ contact.name }}</Link>
                        <span class="ml-2 text-xs text-slate-500">{{ contact.tickets_count }} tickets</span>
                    </li>
                    <li v-if="!organization.contacts?.length" class="text-sm text-slate-500">{{ $t('organizations.no_contacts_linked') }}</li>
                </ul>
            </div>
        </div>
    </AgentLayout>
</template>
