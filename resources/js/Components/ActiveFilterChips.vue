<script setup>
defineProps({
    items: { type: Array, default: () => [] },
});

const emit = defineEmits(['remove', 'clear']);
</script>

<template>
    <div v-if="items.length" class="flex flex-wrap items-center gap-2">
        <span class="text-sm text-slate-500">Active filters:</span>
        <button
            v-for="item in items"
            :key="item.key"
            type="button"
            class="inline-flex items-center gap-1 rounded-full bg-slate-100 px-2.5 py-1 text-xs font-medium text-slate-700 transition hover:bg-slate-200"
            @click="emit('remove', item.key)"
        >
            {{ item.label }}
            <span class="text-slate-400">×</span>
        </button>
        <button type="button" class="text-xs font-medium text-blue-600 hover:text-blue-700" @click="emit('clear')">
            Clear all
        </button>
    </div>
</template>
