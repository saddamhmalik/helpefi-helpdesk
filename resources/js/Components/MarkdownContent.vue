<script setup>
import { computed, watch } from 'vue';
import DOMPurify from 'dompurify';
import { marked } from 'marked';
import Prism from 'prismjs';
import 'prismjs/components/prism-clike';
import 'prismjs/components/prism-javascript';
import 'prismjs/components/prism-typescript';
import 'prismjs/components/prism-php';
import 'prismjs/components/prism-bash';
import 'prismjs/components/prism-json';
import 'prismjs/components/prism-yaml';
import 'prismjs/components/prism-python';
import 'prismjs/components/prism-sql';
import 'prismjs/components/prism-docker';
import 'prismjs/themes/prism-tomorrow.css';

marked.setOptions({
    gfm: true,
    breaks: true,
    mangle: false,
});

const props = defineProps({
    content: { type: String, default: '' },
    tocMinLevel: { type: Number, default: 2 },
    tocMaxLevel: { type: Number, default: 3 },
});

const emit = defineEmits(['toc']);

const escapeHtml = (value) => (
    String(value ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;')
);

const slugify = (value) => {
    const raw = String(value ?? '')
        .toLowerCase()
        .trim()
        .replace(/[\s]+/g, '-')
        .replace(/[^a-z0-9\-]/g, '');

    return raw === '' ? 'section' : raw;
};

const buildSlugger = () => {
    const counts = new Map();

    return {
        slug: (value) => {
            const base = slugify(value);
            const count = counts.get(base) ?? 0;
            counts.set(base, count + 1);
            return count === 0 ? base : `${base}-${count}`;
        },
    };
};

const parsed = computed(() => {
    const toc = [];
    const slugger = buildSlugger();

    const renderer = new marked.Renderer();

    renderer.heading = (text, level) => {
        const depth = Number(level) || 2;
        const id = slugger.slug(text);
        const safeText = text ?? '';

        if (depth >= props.tocMinLevel && depth <= props.tocMaxLevel) {
            toc.push({ id, text: safeText, level: depth });
        }

        return `<h${depth} id="${id}">${safeText}</h${depth}>`;
    };

    renderer.code = (code, infostring, escaped) => {
        const langRaw = String(infostring ?? '').trim();
        const lang = langRaw === '' ? '' : langRaw.split(/\s+/)[0].toLowerCase();

        const langMap = {
            js: 'javascript',
            ts: 'typescript',
            sh: 'bash',
            shell: 'bash',
            yml: 'yaml',
        };

        const prismLang = langMap[lang] ?? lang;
        const grammar = prismLang && Prism.languages[prismLang] ? Prism.languages[prismLang] : null;
        const safeCode = escaped ? code : escapeHtml(code);

        if (!grammar) {
            return `<pre><code>${safeCode}</code></pre>`;
        }

        try {
            const highlighted = Prism.highlight(code, grammar, prismLang);
            return `<pre><code class="language-${prismLang}">${highlighted}</code></pre>`;
        } catch {
            return `<pre><code>${safeCode}</code></pre>`;
        }
    };

    const rawHtml = marked.parse(props.content ?? '', { renderer });

    const safeHtml = DOMPurify.sanitize(rawHtml, {
        ALLOWED_TAGS: [
            'p',
            'br',
            'strong',
            'em',
            'b',
            'i',
            'u',
            'a',
            'ul',
            'ol',
            'li',
            'img',
            'blockquote',
            'span',
            'div',
            'h1',
            'h2',
            'h3',
            'h4',
            'h5',
            'h6',
            'table',
            'thead',
            'tbody',
            'tr',
            'th',
            'td',
            'code',
            'pre',
        ],
        ALLOWED_ATTR: ['href', 'src', 'alt', 'class', 'target', 'rel', 'colspan', 'rowspan', 'id'],
    });

    return { html: safeHtml, toc };
});

watch(() => parsed.value.toc, (value) => emit('toc', value), { immediate: true });
</script>

<template>
    <div class="prose prose-slate max-w-none dark:prose-invert prose-pre:my-6">
        <div v-html="parsed.html" />
    </div>
</template>

