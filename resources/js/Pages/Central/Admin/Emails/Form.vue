<script setup>
import { computed } from 'vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import AdminLayout from '../../../../Layouts/AdminLayout.vue';

const props = defineProps({
    template: { type: Object, default: null },
    placeholders: { type: Array, default: () => [] },
});

const page = usePage();
const isEditing = computed(() => props.template !== null);

const form = useForm({
    name: props.template?.name ?? '',
    slug: props.template?.slug ?? '',
    subject: props.template?.subject ?? '',
    body_html: props.template?.body_html ?? '',
    is_active: props.template?.is_active ?? true,
});

const submit = () => {
    if (isEditing.value) {
        form.put(`/admin/emails/${props.template.id}`);
        return;
    }

    form.post('/admin/emails');
};

const placeholderTag = (key) => `{{${key}}}`;
</script>

<template>
    <Head :title="isEditing ? 'Edit email template' : 'New email template'" />
    <AdminLayout>
        <div class="mx-auto max-w-4xl px-4 py-8 sm:px-6">
            <div class="mb-8">
                <Link href="/admin/emails" class="text-sm text-slate-500 hover:text-slate-700">← Back to email templates</Link>
                <h1 class="mt-2 text-2xl font-semibold text-slate-900">{{ isEditing ? 'Edit template' : 'New email template' }}</h1>
                <p class="mt-1 text-sm text-slate-600">Use placeholders like <code class="rounded bg-slate-100 px-1">{{ placeholderTag('admin_name') }}</code> in the subject and body.</p>
            </div>

            <div class="mb-6 rounded-xl border border-blue-200 bg-blue-50 p-4">
                <p class="text-sm font-medium text-blue-900">Placeholders</p>
                <ul class="mt-2 grid gap-1 sm:grid-cols-2">
                    <li v-for="item in placeholders" :key="item.key" class="text-xs text-blue-800">
                        <code class="rounded bg-white px-1.5 py-0.5">{{ placeholderTag(item.key) }}</code>
                        — {{ item.label }}
                    </li>
                </ul>
            </div>

            <form class="space-y-6 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm" @submit.prevent="submit">
                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-slate-700">Name</label>
                        <input v-model="form.name" type="text" required class="w-full rounded-xl border border-slate-200 px-3.5 py-2.5 text-sm" />
                        <p v-if="form.errors.name" class="mt-1 text-xs text-red-600">{{ form.errors.name }}</p>
                    </div>
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-slate-700">Slug</label>
                        <input
                            v-model="form.slug"
                            type="text"
                            :required="!template?.is_system"
                            :disabled="template?.is_system"
                            class="w-full rounded-xl border border-slate-200 px-3.5 py-2.5 text-sm disabled:bg-slate-50"
                        />
                        <p v-if="form.errors.slug" class="mt-1 text-xs text-red-600">{{ form.errors.slug }}</p>
                    </div>
                </div>

                <div>
                    <label class="mb-1.5 block text-sm font-medium text-slate-700">Subject</label>
                    <input v-model="form.subject" type="text" required class="w-full rounded-xl border border-slate-200 px-3.5 py-2.5 text-sm" />
                    <p v-if="form.errors.subject" class="mt-1 text-xs text-red-600">{{ form.errors.subject }}</p>
                </div>

                <div>
                    <label class="mb-1.5 block text-sm font-medium text-slate-700">Body (HTML)</label>
                    <textarea v-model="form.body_html" rows="14" required class="w-full rounded-xl border border-slate-200 px-3.5 py-2.5 font-mono text-sm" />
                    <p v-if="form.errors.body_html" class="mt-1 text-xs text-red-600">{{ form.errors.body_html }}</p>
                </div>

                <label class="flex items-center gap-2 text-sm text-slate-700">
                    <input v-model="form.is_active" type="checkbox" class="rounded border-slate-300" />
                    Active (emails will be sent when this template is used)
                </label>

                <div class="flex items-center gap-3">
                    <button type="submit" class="rounded-xl bg-blue-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-blue-700" :disabled="form.processing">
                        {{ isEditing ? 'Save changes' : 'Create template' }}
                    </button>
                    <Link href="/admin/emails" class="text-sm text-slate-500 hover:text-slate-700">Cancel</Link>
                </div>
            </form>
        </div>
    </AdminLayout>
</template>
