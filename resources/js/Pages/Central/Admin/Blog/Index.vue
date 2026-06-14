<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import AdminLayout from '../../../../Layouts/AdminLayout.vue';
import PageHeader from '../../../../Components/PageHeader.vue';
import AppRowActions from '../../../../Components/AppRowActions.vue';
import AppEditAction from '../../../../Components/AppEditAction.vue';
import AppDeleteAction from '../../../../Components/AppDeleteAction.vue';
import { usePlatformAdmin } from '../../../../composables/usePlatformAdmin.js';

defineProps({
    posts: { type: Array, default: () => [] },
});

const { can } = usePlatformAdmin();
const canManage = can('blog.manage');

const destroy = (post) => {
    if (!confirm(`Delete "${post.title}"?`)) {
        return;
    }

    router.delete(`/admin/blog/${post.id}`);
};

const statusClass = (status) => (
    status === 'published'
        ? 'bg-emerald-100 text-emerald-700 dark:text-emerald-300'
        : 'bg-amber-100 text-amber-800 dark:text-amber-200'
);
</script>

<template>
    <Head title="Marketing blog" />
    <AdminLayout>
        <div class="mx-auto max-w-6xl px-4 py-8 sm:px-6">
            <PageHeader title="Marketing blog" description="Publish SEO articles on the public helpefi.com blog.">
                <template v-if="canManage" #actions>
                    <Link href="/admin/blog/create" class="rounded-xl bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-blue-700">
                        New post
                    </Link>
                </template>
            </PageHeader>

            <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <table class="min-w-full divide-y divide-slate-200 text-sm dark:divide-slate-800">
                    <thead class="bg-slate-50 dark:bg-slate-950">
                        <tr>
                            <th class="px-5 py-3.5 text-left font-medium text-slate-600 dark:text-slate-400">Post</th>
                            <th class="px-5 py-3.5 text-left font-medium text-slate-600 dark:text-slate-400">Status</th>
                            <th class="px-5 py-3.5 text-left font-medium text-slate-600 dark:text-slate-400">Published</th>
                            <th class="px-5 py-3.5 text-right font-medium text-slate-600 dark:text-slate-400">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        <tr v-for="post in posts" :key="post.id" class="hover:bg-slate-50 dark:hover:bg-slate-800/80">
                            <td class="px-5 py-4 align-top">
                                <p class="font-medium text-slate-900 dark:text-slate-100">{{ post.title }}</p>
                                <p class="mt-0.5 font-mono text-xs text-slate-500 dark:text-slate-400">/blog/{{ post.slug }}</p>
                                <p class="mt-2 line-clamp-2 text-xs text-slate-500 dark:text-slate-400">{{ post.excerpt }}</p>
                            </td>
                            <td class="px-5 py-4 align-top">
                                <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-medium capitalize" :class="statusClass(post.status)">
                                    {{ post.status }}
                                </span>
                            </td>
                            <td class="px-5 py-4 align-top text-slate-600 dark:text-slate-400">
                                <p>{{ post.published_at || '—' }}</p>
                                <p v-if="post.reading_minutes" class="mt-1 text-xs text-slate-500">{{ post.reading_minutes }} min read</p>
                            </td>
                            <td class="px-5 py-4 align-top text-right">
                                <AppRowActions>
                                    <a
                                        v-if="post.public_path"
                                        :href="post.public_path"
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        class="text-sm font-medium text-blue-600 hover:text-blue-700"
                                    >
                                        View
                                    </a>
                                    <AppEditAction v-if="canManage" label="Edit" :href="`/admin/blog/${post.id}/edit`" />
                                    <AppDeleteAction v-if="canManage" label="Delete" @click="destroy(post)" />
                                </AppRowActions>
                            </td>
                        </tr>
                        <tr v-if="!posts.length">
                            <td colspan="4" class="px-5 py-10 text-center text-sm text-slate-500 dark:text-slate-400">
                                No blog posts yet.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </AdminLayout>
</template>
