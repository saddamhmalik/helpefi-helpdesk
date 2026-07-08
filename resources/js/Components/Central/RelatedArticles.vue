<script setup>
import { Link } from '@inertiajs/vue3';

defineProps({
    title: { type: String, default: 'Related resources' },
    items: { type: Array, default: () => [] },
    /** 'links' for tag-style links, 'posts' for article list, 'features' for feature pages */
    variant: { type: String, default: 'links' },
    accent: { type: String, default: 'blue' },
});

const borderClass = {
    blue: 'border-blue-200 bg-blue-50 dark:border-blue-900/50 dark:bg-blue-950/30',
    slate: 'border-slate-200 bg-slate-50 dark:border-slate-800 dark:bg-slate-950',
};
</script>

<template>
    <section
        v-if="items.length"
        class="mt-12 rounded-2xl border p-6"
        :class="borderClass[accent] ?? borderClass.slate"
        style="content-visibility:auto"
    >
        <h2 class="text-lg font-semibold text-slate-900 dark:text-slate-100">{{ title }}</h2>

        <!-- tag-style links -->
        <div v-if="variant === 'links'" class="mt-4 flex flex-wrap gap-3">
            <Link
                v-for="(link, index) in items"
                :key="index"
                :href="link.href || link.path"
                class="rounded-full border px-4 py-2 text-sm font-medium transition"
                :class="accent === 'blue'
                    ? 'border-blue-200 bg-white text-blue-800 hover:border-blue-400 dark:border-blue-800 dark:bg-slate-900 dark:text-blue-200'
                    : 'border-slate-200 bg-white text-slate-700 hover:border-blue-300 hover:text-blue-700 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200'"
            >
                {{ link.label || link.nav_label }}
            </Link>
        </div>

        <!-- post list -->
        <ul v-else-if="variant === 'posts'" class="mt-4 space-y-3">
            <li v-for="(post, index) in items" :key="index">
                <Link
                    :href="post.path"
                    class="font-medium text-blue-600 hover:text-blue-700"
                >
                    {{ post.title }}
                </Link>
                <div v-if="post.excerpt" class="mt-1 text-sm text-slate-600 dark:text-slate-300">
                    {{ post.excerpt }}
                </div>
            </li>
        </ul>
    </section>
</template>
