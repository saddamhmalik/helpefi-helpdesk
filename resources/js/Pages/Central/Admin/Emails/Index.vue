<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import AdminLayout from '../../../../Layouts/AdminLayout.vue';
import PageHeader from '../../../../Components/PageHeader.vue';
import { usePlatformAdmin } from '../../../../composables/usePlatformAdmin.js';

defineProps({
    templates: { type: Array, default: () => [] },
    placeholders: { type: Array, default: () => [] },
});

const { can } = usePlatformAdmin();
const canManage = can('emails.manage');

const destroy = (template) => {
    if (template.is_system) {
        return;
    }

    if (confirm(`Delete "${template.name}"?`)) {
        router.delete(`/admin/emails/${template.id}`);
    }
};

const eventLabel = (slug) => {
    if (slug === 'registration_confirmation') {
        return 'Sent on registration';
    }

    if (slug === 'workspace_welcome') {
        return 'Sent when workspace is ready';
    }

    return 'Custom template';
};

const placeholderTag = (key) => `{{${key}}}`;
</script>

<template>
    <Head title="Email templates" />
    <AdminLayout>
        <div class="mx-auto max-w-5xl px-4 py-8 sm:px-6">
            <PageHeader title="Email templates" description="Manage registration and welcome emails sent from the central platform.">
                <template v-if="canManage" #actions>
                    <Link href="/admin/emails/create" class="rounded-xl bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-blue-700">
                        Add template
                    </Link>
                </template>
            </PageHeader>

            <div class="mb-6 rounded-2xl border border-blue-200 bg-blue-50 p-5">
                <p class="text-sm font-semibold text-blue-900">Available placeholders</p>
                <div class="mt-3 flex flex-wrap gap-2">
                    <code v-for="item in placeholders" :key="item.key" class="rounded-lg bg-white px-2.5 py-1 text-xs text-blue-800 ring-1 ring-blue-200">{{ placeholderTag(item.key) }}</code>
                </div>
            </div>

            <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50">
                        <tr>
                            <th class="px-5 py-3.5 text-left font-medium text-slate-600">Template</th>
                            <th class="px-5 py-3.5 text-left font-medium text-slate-600">Subject</th>
                            <th class="px-5 py-3.5 text-left font-medium text-slate-600">Trigger</th>
                            <th class="px-5 py-3.5 text-left font-medium text-slate-600">Status</th>
                            <th class="px-5 py-3.5 text-right font-medium text-slate-600">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr v-for="template in templates" :key="template.id" class="hover:bg-slate-50/80">
                            <td class="px-5 py-4 align-top">
                                <p class="font-medium text-slate-900">{{ template.name }}</p>
                                <p class="mt-0.5 font-mono text-xs text-slate-500">{{ template.slug }}</p>
                                <span v-if="template.is_system" class="mt-2 inline-flex rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-slate-600">System</span>
                            </td>
                            <td class="max-w-xs px-5 py-4 align-top text-slate-600">{{ template.subject }}</td>
                            <td class="px-5 py-4 align-top text-slate-600">{{ eventLabel(template.slug) }}</td>
                            <td class="px-5 py-4 align-top">
                                <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-medium" :class="template.is_active ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-600'">
                                    {{ template.is_active ? 'Active' : 'Disabled' }}
                                </span>
                            </td>
                            <td class="px-5 py-4 align-top text-right">
                                <div class="flex justify-end gap-2">
                                    <Link :href="`/admin/emails/${template.id}/edit`" class="rounded-lg px-3 py-1.5 text-xs font-medium text-blue-600 hover:bg-blue-50">
                                        Edit
                                    </Link>
                                    <button
                                        v-if="canManage && !template.is_system"
                                        type="button"
                                        class="rounded-lg px-3 py-1.5 text-xs font-medium text-red-600 hover:bg-red-50"
                                        @click="destroy(template)"
                                    >
                                        Delete
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </AdminLayout>
</template>
