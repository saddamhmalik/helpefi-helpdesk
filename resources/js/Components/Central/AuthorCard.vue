<script setup>
defineProps({
    updatedAt: { type: String, default: '' },
    author: { type: Object, default: () => ({}) },
    reviewer: { type: Object, default: () => ({}) },
    updatedLabel: { type: String, default: 'Last updated on' },
    writtenLabel: { type: String, default: 'Written by' },
    reviewedLabel: { type: String, default: 'Reviewed by' },
});
</script>

<template>
    <section
        v-if="updatedAt || author?.name || reviewer?.name"
        class="border-b border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-950"
    >
        <div class="mx-auto max-w-3xl px-4 py-4 sm:px-6 lg:px-8">
            <div class="flex flex-wrap items-center gap-x-5 gap-y-1.5 text-xs text-slate-500 dark:text-slate-400">
                <span v-if="updatedAt" class="inline-flex items-center gap-1.5">
                    <svg class="h-3.5 w-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    {{ updatedLabel }} {{ updatedAt }}
                </span>
                <span v-if="author?.name" class="inline-flex items-center gap-1.5">
                    <svg class="h-3.5 w-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                    </svg>
                    {{ writtenLabel }} {{ author.name }}
                    <span v-if="author.role" class="hidden sm:inline text-slate-400">· {{ author.role }}</span>
                </span>
                <span v-if="reviewer?.name" class="inline-flex items-center gap-1.5">
                    <svg class="h-3.5 w-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                    </svg>
                    {{ reviewedLabel }} {{ reviewer.name }}
                    <span v-if="reviewer.role" class="hidden sm:inline text-slate-400">· {{ reviewer.role }}</span>
                </span>
            </div>
        </div>
    </section>
</template>
