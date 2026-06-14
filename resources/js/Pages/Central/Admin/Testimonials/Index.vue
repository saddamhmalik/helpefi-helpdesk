<script setup>
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import AdminLayout from '../../../../Layouts/AdminLayout.vue';
import PageHeader from '../../../../Components/PageHeader.vue';
import AppRowActions from '../../../../Components/AppRowActions.vue';
import AppEditAction from '../../../../Components/AppEditAction.vue';
import AppDeleteAction from '../../../../Components/AppDeleteAction.vue';
import { usePlatformAdmin } from '../../../../composables/usePlatformAdmin.js';

const props = defineProps({
    testimonials: { type: Array, default: () => [] },
    testimonialsEnabled: { type: Boolean, default: true },
});

const { can } = usePlatformAdmin();
const canManage = can('testimonials.manage');

const settingsForm = useForm({
    testimonials_enabled: props.testimonialsEnabled,
});

const saveSettings = () => {
    settingsForm.put('/admin/testimonials/settings', {
        preserveScroll: true,
    });
};

const destroy = (testimonial) => {
    if (!confirm(`Delete testimonial from ${testimonial.name}?`)) {
        return;
    }

    router.delete(`/admin/testimonials/${testimonial.id}`);
};
</script>

<template>
    <Head title="Testimonials" />
    <AdminLayout>
        <div class="mx-auto max-w-6xl px-4 py-8 sm:px-6">
            <PageHeader title="Testimonials" description="Manage customer quotes shown on the marketing homepage.">
                <template v-if="canManage" #actions>
                    <Link href="/admin/testimonials/create" class="rounded-xl bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-blue-700">
                        New testimonial
                    </Link>
                </template>
            </PageHeader>

            <div v-if="canManage" class="mb-6 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <div class="flex flex-wrap items-center justify-between gap-4">
                    <div>
                        <p class="text-sm font-medium text-slate-900 dark:text-slate-100">Show testimonials on homepage</p>
                        <p class="mt-1 text-sm text-slate-600 dark:text-slate-400">When disabled, the entire social proof section is hidden from the marketing site.</p>
                    </div>
                    <label class="inline-flex cursor-pointer items-center gap-3">
                        <span class="text-sm font-medium text-slate-700 dark:text-slate-300">{{ settingsForm.testimonials_enabled ? 'Enabled' : 'Disabled' }}</span>
                        <input
                            v-model="settingsForm.testimonials_enabled"
                            type="checkbox"
                            class="h-5 w-5 rounded border-slate-300 text-blue-600 focus:ring-blue-500"
                            @change="saveSettings"
                        />
                    </label>
                </div>
            </div>

            <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <table class="min-w-full divide-y divide-slate-200 text-sm dark:divide-slate-800">
                    <thead class="bg-slate-50 dark:bg-slate-950">
                        <tr>
                            <th class="px-5 py-3.5 text-left font-medium text-slate-600 dark:text-slate-400">Quote</th>
                            <th class="px-5 py-3.5 text-left font-medium text-slate-600 dark:text-slate-400">Author</th>
                            <th class="px-5 py-3.5 text-left font-medium text-slate-600 dark:text-slate-400">Order</th>
                            <th class="px-5 py-3.5 text-left font-medium text-slate-600 dark:text-slate-400">Status</th>
                            <th class="px-5 py-3.5 text-right font-medium text-slate-600 dark:text-slate-400">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        <tr v-for="testimonial in testimonials" :key="testimonial.id" class="hover:bg-slate-50 dark:hover:bg-slate-800/80">
                            <td class="px-5 py-4 align-top">
                                <p class="line-clamp-3 text-slate-700 dark:text-slate-300">“{{ testimonial.quote }}”</p>
                            </td>
                            <td class="px-5 py-4 align-top">
                                <p class="font-medium text-slate-900 dark:text-slate-100">{{ testimonial.name }}</p>
                                <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">{{ testimonial.role }} · {{ testimonial.company_type }}</p>
                            </td>
                            <td class="px-5 py-4 align-top text-slate-600 dark:text-slate-400">{{ testimonial.sort_order }}</td>
                            <td class="px-5 py-4 align-top">
                                <span
                                    class="inline-flex rounded-full px-2.5 py-1 text-xs font-medium"
                                    :class="testimonial.is_enabled ? 'bg-emerald-100 text-emerald-700 dark:text-emerald-300' : 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-400'"
                                >
                                    {{ testimonial.is_enabled ? 'Visible' : 'Hidden' }}
                                </span>
                            </td>
                            <td class="px-5 py-4 align-top text-right">
                                <AppRowActions>
                                    <AppEditAction v-if="canManage" label="Edit" :href="`/admin/testimonials/${testimonial.id}/edit`" />
                                    <AppDeleteAction v-if="canManage" label="Delete" @click="destroy(testimonial)" />
                                </AppRowActions>
                            </td>
                        </tr>
                        <tr v-if="!testimonials.length">
                            <td colspan="5" class="px-5 py-10 text-center text-sm text-slate-500 dark:text-slate-400">
                                No testimonials yet.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </AdminLayout>
</template>
