<script setup>
import { Head } from '@inertiajs/vue3';
import SettingsPage from '../../../Components/SettingsPage.vue';
import AppRowActions from '../../../Components/AppRowActions.vue';
import AppEditAction from '../../../Components/AppEditAction.vue';
defineProps({
    templates: { type: Array, default: () => [] },
    placeholders: { type: Array, default: () => [] },
});

const placeholderTag = (key) => `{{${key}}}`;
</script>

<template>
    <Head :title="$t('settings.email_templates')" />
    <SettingsPage :title="$t('settings.email_templates')" description="Customize subject lines and HTML bodies for every automated email your workspace sends.">
        <div class="mb-6 rounded-xl border border-blue-200 bg-blue-50 p-4">
            <p class="text-sm font-medium text-blue-900">Placeholders</p>
            <p class="mt-1 text-xs text-blue-800">Use these tokens in subjects and bodies. Each template also lists the placeholders relevant to that email.</p>
            <div class="mt-3 flex flex-wrap gap-2">
                <code v-for="item in placeholders" :key="item.key" class="rounded-lg bg-white px-2 py-1 text-xs text-blue-800 ring-1 ring-blue-200">{{ placeholderTag(item.key) }}</code>
            </div>
        </div>

        <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
            <table class="min-w-full divide-y divide-slate-100 text-sm">
                <thead class="bg-slate-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Template</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">{{ $t('common.subject') }}</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">When sent</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Status</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-slate-500">{{ $t('common.actions') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <tr v-for="template in templates" :key="template.id" class="hover:bg-slate-50">
                        <td class="px-4 py-3 align-top">
                            <p class="font-medium text-slate-900">{{ template.name }}</p>
                            <p class="mt-0.5 font-mono text-xs text-slate-500">{{ template.slug }}</p>
                        </td>
                        <td class="max-w-xs px-4 py-3 align-top text-slate-600">{{ template.subject }}</td>
                        <td class="px-4 py-3 align-top text-slate-600">{{ template.trigger || '—' }}</td>
                        <td class="px-4 py-3 align-top">
                            <span class="rounded-full px-2 py-0.5 text-xs font-medium" :class="template.is_active ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-600'">
                                {{ template.is_active ? 'Active' : 'Disabled' }}
                            </span>
                        </td>
                        <td class="px-4 py-3 align-top text-right">
                            <AppRowActions>
                                <AppEditAction
                                    :label="$t('common.edit')"
                                    :href="`/settings/email-templates/${template.id}/edit`"
                                />
                            </AppRowActions>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </SettingsPage>
</template>
