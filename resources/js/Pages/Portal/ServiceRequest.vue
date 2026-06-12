<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import { onMounted } from 'vue';
import PortalLayout from '../../Layouts/PortalLayout.vue';
import { usePortalRoutes } from '../../composables/usePortalRoutes.js';
import { useI18n } from 'vue-i18n';

const props = defineProps({
    service: Object,
    customer: Object,
});

const { t } = useI18n();
const { portalPath } = usePortalRoutes();

const form = useForm({
    name: '',
    email: '',
    details: '',
    fields: {},
});

onMounted(() => {
    if (props.customer) {
        form.name = props.customer.name;
        form.email = props.customer.email;
    }

    (props.service.fields ?? []).forEach((field) => {
        form.fields[field.name] = '';
    });
});
</script>

<template>
    <Head :title="service.name" />
    <PortalLayout>
        <div class="mx-auto max-w-xl">
            <Link :href="portalPath('/services')" class="text-sm text-blue-600 hover:text-blue-700 dark:hover:text-blue-300 dark:text-blue-300">← Service catalog</Link>
            <h1 class="mt-2 text-2xl font-bold text-slate-900 dark:text-slate-100">{{ service.name }}</h1>
            <p v-if="service.description" class="mt-1 text-sm text-slate-600 dark:text-slate-400">{{ service.description }}</p>

            <form class="mt-6 space-y-4 rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 p-6 shadow-sm" @submit.prevent="form.post(portalPath(`/services/${service.slug}`))">
                <template v-if="!customer">
                    <div>
                        <label class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-300">{{ $t('portal.name') }}</label>
                        <input v-model="form.name" type="text" required class="w-full rounded-lg border border-slate-300 dark:border-slate-700 px-3 py-2" />
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-300">{{ $t('portal.email') }}</label>
                        <input v-model="form.email" type="email" required class="w-full rounded-lg border border-slate-300 dark:border-slate-700 px-3 py-2" />
                    </div>
                </template>

                <div v-for="field in service.fields" :key="field.name">
                    <label class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-300">
                        {{ field.label }}
                        <span v-if="field.required" class="text-red-600">*</span>
                    </label>
                    <select
                        v-if="field.type === 'select'"
                        v-model="form.fields[field.name]"
                        :required="field.required"
                        class="w-full rounded-lg border border-slate-300 dark:border-slate-700 px-3 py-2"
                    >
                        <option value="">{{ $t('portal.select') }}</option>
                        <option v-for="option in field.options" :key="option" :value="option">{{ option }}</option>
                    </select>
                    <textarea
                        v-else-if="field.type === 'textarea'"
                        v-model="form.fields[field.name]"
                        rows="3"
                        :required="field.required"
                        class="w-full rounded-lg border border-slate-300 dark:border-slate-700 px-3 py-2"
                    />
                    <input
                        v-else
                        v-model="form.fields[field.name]"
                        type="text"
                        :required="field.required"
                        class="w-full rounded-lg border border-slate-300 dark:border-slate-700 px-3 py-2"
                    />
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-300">{{ $t('portal.additional_details') }}</label>
                    <textarea v-model="form.details" rows="4" class="w-full rounded-lg border border-slate-300 dark:border-slate-700 px-3 py-2" />
                </div>

                <button type="submit" class="w-full rounded-lg bg-blue-600 px-4 py-2 font-medium text-white hover:bg-blue-700" :disabled="form.processing">{{ $t('portal.submit_request') }}</button>
            </form>
        </div>
    </PortalLayout>
</template>
