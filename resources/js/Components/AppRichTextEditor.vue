<script setup>
import Placeholder from '@tiptap/extension-placeholder';
import StarterKit from '@tiptap/starter-kit';
import { EditorContent, useEditor } from '@tiptap/vue-3';
import { computed, onBeforeUnmount, ref, watch } from 'vue';

const props = defineProps({
    modelValue: { type: String, default: '' },
    placeholder: { type: String, default: 'Write a reply...' },
    borderless: { type: Boolean, default: false },
    compact: { type: Boolean, default: false },
    inline: { type: Boolean, default: false },
    expanded: { type: Boolean, default: false },
    form: { type: Boolean, default: false },
});

const emit = defineEmits(['update:modelValue']);

const toolbarTick = ref(0);

const editorSurfaceClass = computed(() => {
    if (props.form) {
        return 'min-h-[10rem] max-h-[min(24rem,50vh)] overflow-y-auto px-3.5 py-2.5 text-sm leading-relaxed text-slate-800 focus:outline-none';
    }

    if (props.expanded) {
        return 'min-h-[8rem] max-h-[min(16rem,40vh)] overflow-y-auto px-4 py-3 text-sm leading-relaxed text-slate-800 focus:outline-none';
    }

    if (props.inline) {
        return 'max-h-24 min-h-[2.25rem] overflow-y-auto px-2 py-1.5 text-sm leading-snug text-slate-800 focus:outline-none';
    }

    if (props.compact) {
        return 'min-h-[72px] px-3 py-2 text-sm text-slate-800 focus:outline-none';
    }

    return 'min-h-[120px] px-3 py-2 text-sm text-slate-800 focus:outline-none';
});

const editor = useEditor({
    content: props.modelValue || '',
    extensions: [
        StarterKit.configure({
            heading: false,
            codeBlock: false,
            code: false,
            blockquote: true,
            link: {
                openOnClick: false,
                HTMLAttributes: { rel: 'noopener noreferrer', target: '_blank' },
            },
        }),
        Placeholder.configure({ placeholder: props.placeholder }),
    ],
    editorProps: {
        attributes: {
            class: editorSurfaceClass.value,
        },
    },
    onUpdate: ({ editor: currentEditor }) => {
        emit('update:modelValue', currentEditor.getHTML());
    },
    onTransaction: () => {
        toolbarTick.value += 1;
    },
});

watch(editorSurfaceClass, (value) => {
    editor.value?.setOptions({
        editorProps: {
            attributes: { class: value },
        },
    });
});

watch(() => props.placeholder, (value) => {
    if (!editor.value) {
        return;
    }

    const extension = editor.value.extensionManager.extensions.find((item) => item.name === 'placeholder');

    if (extension) {
        extension.options.placeholder = value;
        editor.value.view.dispatch(editor.value.state.tr);
    }
});

watch(() => props.modelValue, (value) => {
    if (!editor.value) {
        return;
    }

    const current = editor.value.getHTML();

    if (value !== current) {
        editor.value.commands.setContent(value || '', false);
    }
});

onBeforeUnmount(() => {
    editor.value?.destroy();
});

const run = (callback) => {
    if (!editor.value) {
        return;
    }

    callback(editor.value).run();
};

const isActive = (name, attrs = {}) => {
    toolbarTick.value;

    return editor.value?.isActive(name, attrs) ?? false;
};

const toggleLink = () => {
    if (!editor.value) {
        return;
    }

    const previous = editor.value.getAttributes('link').href;
    const url = window.prompt('Link URL', previous || 'https://');

    if (url === null) {
        return;
    }

    if (url === '') {
        run((instance) => instance.chain().focus().extendMarkRange('link').unsetLink());

        return;
    }

    run((instance) => instance.chain().focus().extendMarkRange('link').setLink({ href: url }));
};

const buttonClass = (active, icon = false) => [
    'rounded transition',
    icon ? 'flex h-7 w-7 items-center justify-center text-xs font-semibold' : 'px-2 py-1 text-xs font-semibold',
    active ? 'bg-slate-200 text-slate-900' : 'text-slate-500 hover:bg-slate-100 hover:text-slate-800',
];

const shellClass = computed(() => {
    if (props.form) {
        return 'overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm transition focus-within:border-blue-500 focus-within:ring-4 focus-within:ring-blue-500/10';
    }

    if (props.borderless) {
        return '';
    }

    return 'overflow-hidden rounded-lg border border-slate-300 bg-white';
});

const toolbarClass = computed(() => {
    if (props.form) {
        return 'flex flex-wrap items-center gap-0.5 border-b border-slate-100 bg-slate-50/80 px-2 py-1.5';
    }

    return ['flex flex-wrap items-center gap-0.5 border-b border-slate-200 bg-slate-50 px-2', props.compact ? 'py-1' : 'py-1.5'];
});
</script>

<template>
    <div
        v-if="inline"
        class="flex min-w-0 flex-1 items-center gap-1 rounded-lg bg-slate-50 ring-1 ring-slate-200 transition focus-within:ring-blue-400"
    >
        <div v-if="editor" class="flex shrink-0 items-center gap-0.5 pl-1">
            <button type="button" :class="buttonClass(isActive('bold'), true)" title="Bold" @mousedown.prevent @click="run((instance) => instance.chain().focus().toggleBold())">B</button>
            <button type="button" :class="buttonClass(isActive('italic'), true)" title="Italic" @mousedown.prevent @click="run((instance) => instance.chain().focus().toggleItalic())"><span class="italic">I</span></button>
            <button type="button" :class="buttonClass(isActive('link'), true)" title="Link" @mousedown.prevent @click="toggleLink">
                <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1" />
                </svg>
            </button>
        </div>
        <EditorContent :editor="editor" class="inline-editor min-w-0 flex-1" />
        <div class="flex shrink-0 items-center pr-1">
            <slot name="toolbar-extra" />
        </div>
    </div>

    <div v-else :class="shellClass">
        <div v-if="editor" :class="toolbarClass">
            <button type="button" :class="buttonClass(isActive('bold'))" title="Bold" @mousedown.prevent @click="run((instance) => instance.chain().focus().toggleBold())">B</button>
            <button type="button" :class="buttonClass(isActive('italic'))" title="Italic" @mousedown.prevent @click="run((instance) => instance.chain().focus().toggleItalic())"><span class="italic">I</span></button>
            <button type="button" :class="buttonClass(isActive('underline'))" title="Underline" @mousedown.prevent @click="run((instance) => instance.chain().focus().toggleUnderline())"><span class="underline">U</span></button>
            <span class="mx-1 h-4 w-px bg-slate-300" />
            <button type="button" :class="buttonClass(isActive('bulletList'))" title="Bullet list" @mousedown.prevent @click="run((instance) => instance.chain().focus().toggleBulletList())">• List</button>
            <button type="button" :class="buttonClass(isActive('orderedList'))" title="Numbered list" @mousedown.prevent @click="run((instance) => instance.chain().focus().toggleOrderedList())">1. List</button>
            <span class="mx-1 h-4 w-px bg-slate-300" />
            <button type="button" :class="buttonClass(isActive('link'))" title="Insert link" @mousedown.prevent @click="toggleLink">Link</button>
            <slot name="toolbar-extra" />
        </div>
        <EditorContent :editor="editor" />
    </div>
</template>

<style>
.ProseMirror {
    outline: none;
}

.inline-editor .ProseMirror p {
    margin: 0;
}

.ProseMirror p.is-editor-empty:first-child::before {
    color: #94a3b8;
    content: attr(data-placeholder);
    float: left;
    height: 0;
    pointer-events: none;
}

.ProseMirror ul {
    list-style: disc;
    margin: 0.5rem 0;
    padding-left: 1.25rem;
}

.ProseMirror ol {
    list-style: decimal;
    margin: 0.5rem 0;
    padding-left: 1.25rem;
}

.ProseMirror p {
    margin: 0.25rem 0;
}

.ProseMirror a {
    color: #2563eb;
    text-decoration: underline;
}

.ProseMirror strong {
    font-weight: 700;
}

.ProseMirror em {
    font-style: italic;
}

.ProseMirror u {
    text-decoration: underline;
}
</style>
