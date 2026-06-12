<script setup>
import { computed } from 'vue';
import { marked } from 'marked';

marked.setOptions({
    breaks: true,
    gfm: true,
});

const props = defineProps({
    content: { type: String, default: '' },
});

const isHtml = computed(() => /<[a-z][\s\S]*>/i.test(props.content ?? ''));

function formatContent(text) {
    if (!text) {
        return '';
    }

    if (/^#{1,6}\s/m.test(text)) {
        return text;
    }

    const lines = text.split('\n');
    const output = [];

    for (const line of lines) {
        const trimmed = line.trim();

        if (!trimmed) {
            output.push('');
            continue;
        }

        if (trimmed.endsWith(':') && !trimmed.startsWith('-') && !/^\d+\.\s/.test(trimmed)) {
            output.push(`## ${trimmed.slice(0, -1)}`);
            continue;
        }

        if (trimmed.startsWith('- ')) {
            output.push(formatInline(trimmed));
            continue;
        }

        if (/^\d+\.\s/.test(trimmed)) {
            output.push(formatInline(trimmed));
            continue;
        }

        output.push(formatInline(trimmed));
    }

    return output.join('\n');
}

function formatInline(line) {
    return line
        .replace(/(Settings → [A-Za-z &]+)/g, '**$1**')
        .replace(/(\/[a-z0-9\-_/{}]+)/gi, '`$1`')
        .replace(/\b(admin|agent|customer)\b —/g, '**$1** —');
}

const html = computed(() => {
    if (!props.content) {
        return '';
    }

    if (isHtml.value) {
        return props.content;
    }

    return marked.parse(formatContent(props.content));
});

const proseClass = 'kb-prose prose prose-slate dark:prose-invert max-w-none prose-headings:font-semibold prose-headings:tracking-tight prose-h2:mt-8 prose-h2:mb-4 prose-h2:border-b prose-h2:border-slate-100 prose-h2:pb-2 prose-h2:text-xl dark:prose-h2:border-slate-800 prose-p:leading-7 prose-li:my-1 prose-ul:my-4 prose-ol:my-4 prose-a:text-blue-600 dark:prose-a:text-blue-400 prose-a:no-underline hover:prose-a:underline prose-code:rounded-md prose-code:bg-slate-100 prose-code:px-1.5 prose-code:py-0.5 prose-code:text-sm prose-code:font-medium prose-code:text-slate-800 prose-code:before:content-none prose-code:after:content-none dark:prose-code:bg-slate-800 dark:prose-code:text-slate-200 prose-strong:text-slate-900 dark:prose-strong:text-slate-100 prose-table:my-6 prose-th:bg-slate-50 prose-th:px-4 prose-th:py-2 prose-td:px-4 prose-td:py-2 dark:prose-th:bg-slate-800';

const richClass = 'ticket-message-html break-words text-sm leading-relaxed text-slate-800 dark:text-slate-200 [&_a]:text-blue-600 dark:[&_a]:text-blue-400 [&_a]:underline [&_li]:ml-4 [&_ol]:list-decimal [&_ol]:pl-4 [&_p]:my-2 [&_ul]:list-disc [&_ul]:pl-4';
</script>

<template>
    <div
        v-if="isHtml"
        :class="richClass"
        v-html="html"
    />
    <div
        v-else
        :class="proseClass"
        v-html="html"
    />
</template>
