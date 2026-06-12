<script setup>
import { computed } from 'vue';

const props = defineProps({
    body: {
        type: String,
        default: '',
    },
    inverted: {
        type: Boolean,
        default: false,
    },
});

const normalizedBody = computed(() => (props.body ?? '').replace(/\r\n/g, '\n').replace(/\r/g, '\n'));

const isHtml = computed(() => /<[a-z][\s\S]*>/i.test(normalizedBody.value));

const segments = computed(() => {
    const text = normalizedBody.value;

    if (!text || isHtml.value) {
        return [];
    }

    const pattern = /(https?:\/\/[^\s<>"']+)/g;
    const parts = [];
    let lastIndex = 0;
    let match;

    while ((match = pattern.exec(text)) !== null) {
        if (match.index > lastIndex) {
            parts.push({ type: 'text', value: text.slice(lastIndex, match.index) });
        }

        parts.push({ type: 'link', value: match[1] });
        lastIndex = match.index + match[0].length;
    }

    if (lastIndex < text.length) {
        parts.push({ type: 'text', value: text.slice(lastIndex) });
    }

    return parts.length ? parts : [{ type: 'text', value: text }];
});

const htmlClass = computed(() => [
    'ticket-message-html break-words text-sm [&_a]:underline [&_li]:ml-4 [&_ol]:list-decimal [&_ol]:pl-4 [&_p]:my-1 [&_ul]:list-disc [&_ul]:pl-4',
    props.inverted ? 'text-white [&_a]:text-blue-100' : 'text-slate-800 dark:text-slate-200 [&_a]:text-blue-600',
]);
</script>

<template>
    <div v-if="isHtml" :class="htmlClass" v-html="normalizedBody" />
    <p v-else class="whitespace-pre-wrap break-words" :class="inverted ? 'text-white' : 'text-slate-800 dark:text-slate-200'">
        <template v-for="(segment, index) in segments" :key="index">
            <a
                v-if="segment.type === 'link'"
                :href="segment.value"
                target="_blank"
                rel="noopener noreferrer"
                class="underline"
                :class="inverted ? 'text-blue-100 hover:text-white' : 'text-blue-600 hover:text-blue-700 dark:hover:text-blue-300 dark:text-blue-300'"
            >{{ segment.value }}</a>
            <span v-else>{{ segment.value }}</span>
        </template>
    </p>
</template>

<style scoped>
.ticket-message-html :deep(blockquote) {
    border-left: 3px solid rgb(203 213 225);
    margin: 0.5rem 0;
    padding-left: 0.75rem;
}
</style>
