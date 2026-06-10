<script setup>
import { Link } from '@inertiajs/vue3';

defineProps({
    links: { type: Array, default: () => [] },
    from: { type: Number, default: null },
    to: { type: Number, default: null },
    total: { type: Number, default: null },
});
</script>

<template>
    <div v-if="links?.length > 3" class="flex flex-wrap items-center justify-between gap-3">
        <p v-if="from != null && to != null && total != null" class="text-sm text-slate-500">
            {{ $t('components.pagination_showing', { from, to, total }) }}
        </p>
        <div class="flex flex-wrap gap-1">
            <Link
                v-for="link in links"
                :key="link.label"
                :href="link.url || '#'"
                class="rounded-lg px-3 py-1.5 text-sm transition"
                :class="[
                    link.active ? 'bg-slate-900 text-white' : 'text-slate-600 hover:bg-slate-100',
                    !link.url ? 'pointer-events-none opacity-40' : '',
                ]"
                v-html="link.label"
            />
        </div>
    </div>
</template>
