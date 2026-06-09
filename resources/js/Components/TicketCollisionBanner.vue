<script setup>
import { computed } from 'vue';

const props = defineProps({
    viewers: { type: Array, default: () => [] },
});

const viewing = computed(() => props.viewers.filter((viewer) => !viewer.composing));
const replying = computed(() => props.viewers.filter((viewer) => viewer.composing));

const names = (list) => list.map((viewer) => viewer.name).join(', ');
</script>

<template>
    <div v-if="viewers.length" class="border-b border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-900">
        <p v-if="replying.length">
            <span class="font-semibold">{{ names(replying) }}</span>
            {{ replying.length === 1 ? 'is' : 'are' }} replying to this ticket.
        </p>
        <p v-else-if="viewing.length">
            <span class="font-semibold">{{ names(viewing) }}</span>
            {{ viewing.length === 1 ? 'is' : 'are' }} viewing this ticket.
        </p>
    </div>
</template>
