<script setup>
import { Link } from '@inertiajs/vue3';
import { settingsNavHref } from '../composables/useSettingsSection.js';

defineProps({
    path: { type: String, required: true },
    defaultSection: { type: String, required: true },
    sections: { type: Array, required: true },
    activeSection: { type: String, required: true },
});
</script>

<template>
    <nav class="mb-6 flex flex-wrap gap-1 border-b border-slate-200 pb-px" aria-label="Section navigation">
        <Link
            v-for="section in sections"
            :key="section.id"
            :href="settingsNavHref(path, section.id, defaultSection)"
            class="-mb-px rounded-t-lg border-b-2 px-3 py-2 text-sm font-medium transition"
            :class="activeSection === section.id
                ? 'border-blue-600 text-blue-700'
                : 'border-transparent text-slate-500 hover:border-slate-300 hover:text-slate-800'"
        >
            {{ section.label }}
        </Link>
    </nav>
</template>
