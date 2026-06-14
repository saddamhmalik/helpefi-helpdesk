<script setup>
import { computed } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import AdminLayout from '../../../../Layouts/AdminLayout.vue';
import { adminInputClass } from '../../../../composables/usePlatformAdmin.js';

const props = defineProps({
    testimonial: { type: Object, default: null },
});

const isEditing = computed(() => props.testimonial !== null);

const form = useForm({
    quote: props.testimonial?.quote ?? '',
    name: props.testimonial?.name ?? '',
    role: props.testimonial?.role ?? '',
    company_type: props.testimonial?.company_type ?? '',
    sort_order: props.testimonial?.sort_order ?? 0,
    is_enabled: props.testimonial?.is_enabled ?? true,
});

const submit = () => {
    if (isEditing.value) {
        form.put(`/admin/testimonials/${props.testimonial.id}`);
        return;
    }

    form.post('/admin/testimonials');
};
</script>

<template>
    <Head :title="isEditing ? 'Edit testimonial' : 'New testimonial'" />
    <AdminLayout>
        <div class="mx-auto max-w-3xl px-4 py-8 sm:px-6">
            <div class="mb-8">
                <Link href="/admin/testimonials" class="text-sm text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-300">← Back to testimonials</Link>
                <h1 class="mt-2 text-2xl font-semibold text-slate-900 dark:text-slate-100">{{ isEditing ? 'Edit testimonial' : 'New testimonial' }}</h1>
                <p class="mt-1 text-sm text-slate-600 dark:text-slate-400">Quotes appear in the homepage social proof section when the section is enabled.</p>
            </div>

            <form class="space-y-6 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900" @submit.prevent="submit">
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-slate-700 dark:text-slate-300">Quote</label>
                    <textarea v-model="form.quote" rows="5" required :class="adminInputClass" />
                    <p v-if="form.errors.quote" class="mt-1 text-xs text-red-600">{{ form.errors.quote }}</p>
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-slate-700 dark:text-slate-300">Name</label>
                        <input v-model="form.name" type="text" required :class="adminInputClass" />
                        <p v-if="form.errors.name" class="mt-1 text-xs text-red-600">{{ form.errors.name }}</p>
                    </div>
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-slate-700 dark:text-slate-300">Role</label>
                        <input v-model="form.role" type="text" required :class="adminInputClass" />
                        <p v-if="form.errors.role" class="mt-1 text-xs text-red-600">{{ form.errors.role }}</p>
                    </div>
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-slate-700 dark:text-slate-300">Company type</label>
                        <input v-model="form.company_type" type="text" required :class="adminInputClass" />
                        <p v-if="form.errors.company_type" class="mt-1 text-xs text-red-600">{{ form.errors.company_type }}</p>
                    </div>
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-slate-700 dark:text-slate-300">Sort order</label>
                        <input v-model.number="form.sort_order" type="number" min="0" max="9999" :class="adminInputClass" />
                        <p v-if="form.errors.sort_order" class="mt-1 text-xs text-red-600">{{ form.errors.sort_order }}</p>
                    </div>
                </div>

                <label class="inline-flex items-center gap-3">
                    <input v-model="form.is_enabled" type="checkbox" class="h-4 w-4 rounded border-slate-300 text-blue-600 focus:ring-blue-500" />
                    <span class="text-sm font-medium text-slate-700 dark:text-slate-300">Visible on homepage</span>
                </label>

                <div class="flex items-center justify-end gap-3 border-t border-slate-200 pt-6 dark:border-slate-800">
                    <Link href="/admin/testimonials" class="rounded-xl px-4 py-2.5 text-sm font-medium text-slate-600 hover:text-slate-900 dark:text-slate-400 dark:hover:text-slate-200">
                        Cancel
                    </Link>
                    <button type="submit" class="rounded-xl bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-blue-700" :disabled="form.processing">
                        {{ isEditing ? 'Save changes' : 'Create testimonial' }}
                    </button>
                </div>
            </form>
        </div>
    </AdminLayout>
</template>
