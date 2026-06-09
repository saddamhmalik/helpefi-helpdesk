<script setup>
import { onBeforeUnmount, ref } from 'vue';
import AppRichTextEditor from './AppRichTextEditor.vue';

const body = defineModel('body', { type: String, default: '' });
const attachments = defineModel('attachments', { type: Array, default: () => [] });

defineProps({
    placeholder: { type: String, default: 'Write a reply...' },
    compact: { type: Boolean, default: false },
    inline: { type: Boolean, default: false },
    expanded: { type: Boolean, default: false },
});

const fileInput = ref(null);
const previewUrls = ref(new Map());
const isDragging = ref(false);
const dragDepth = ref(0);

const formatFileSize = (bytes) => {
    if (!bytes) return '';
    if (bytes < 1024) return `${bytes} B`;
    if (bytes < 1024 * 1024) return `${Math.round(bytes / 1024)} KB`;

    return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
};

const rememberPreview = (file) => {
    if (!file.type?.startsWith('image/')) {
        return null;
    }

    if (!previewUrls.value.has(file)) {
        previewUrls.value.set(file, URL.createObjectURL(file));
    }

    return previewUrls.value.get(file);
};

const revokePreview = (file) => {
    const url = previewUrls.value.get(file);

    if (url) {
        URL.revokeObjectURL(url);
        previewUrls.value.delete(file);
    }
};

const addFiles = (files) => {
    const selected = Array.from(files ?? []);
    const remaining = 5 - attachments.value.length;

    if (remaining <= 0 || selected.length === 0) {
        return;
    }

    attachments.value = [...attachments.value, ...selected.slice(0, remaining)];
};

const onFilesSelected = (event) => {
    addFiles(event.target.files);
    event.target.value = '';
};

const removeAttachment = (index) => {
    const removed = attachments.value[index];

    attachments.value = attachments.value.filter((_, fileIndex) => fileIndex !== index);

    if (removed) {
        revokePreview(removed);
    }
};

const extension = (filename) => {
    const parts = filename.split('.');

    return parts.length > 1 ? parts.pop().toUpperCase() : 'FILE';
};

const onDragEnter = (event) => {
    event.preventDefault();
    dragDepth.value += 1;
    isDragging.value = true;
};

const onDragLeave = (event) => {
    event.preventDefault();
    dragDepth.value -= 1;

    if (dragDepth.value <= 0) {
        dragDepth.value = 0;
        isDragging.value = false;
    }
};

const onDragOver = (event) => {
    event.preventDefault();
};

const onDrop = (event) => {
    event.preventDefault();
    dragDepth.value = 0;
    isDragging.value = false;
    addFiles(event.dataTransfer?.files);
};

onBeforeUnmount(() => {
    previewUrls.value.forEach((url) => URL.revokeObjectURL(url));
    previewUrls.value.clear();
});
</script>

<template>
    <div
        v-if="!inline && !expanded"
        class="relative overflow-hidden bg-white transition duration-200"
        :class="[
            compact ? 'rounded-lg ring-1 ring-slate-200' : 'rounded-lg border',
            isDragging ? 'border-blue-400 ring-2 ring-blue-200' : compact ? '' : 'border-slate-300 focus-within:border-blue-400 focus-within:ring-2 focus-within:ring-blue-100',
        ]"
        @dragenter="onDragEnter"
        @dragleave="onDragLeave"
        @dragover="onDragOver"
        @drop="onDrop"
    >
        <div
            v-if="isDragging"
            class="pointer-events-none absolute inset-0 z-10 flex items-center justify-center bg-blue-50/90 backdrop-blur-[1px]"
        >
            <div class="rounded-xl border border-dashed border-blue-300 bg-white px-4 py-3 text-center shadow-sm">
                <p class="text-sm font-semibold text-blue-700">Drop files to attach</p>
                <p class="mt-1 text-xs text-blue-600/80">Up to 5 files, 10MB each</p>
            </div>
        </div>

        <AppRichTextEditor v-model="body" :placeholder="placeholder" :compact="compact" :inline="inline" borderless>
            <template #toolbar-extra>
                <span v-if="!inline" class="mx-1 h-4 w-px bg-slate-300" />
                <input
                    ref="fileInput"
                    type="file"
                    multiple
                    class="hidden"
                    @change="onFilesSelected"
                />
                <button
                    type="button"
                    title="Attach files"
                    class="inline-flex items-center justify-center rounded transition hover:bg-slate-100 hover:text-slate-900"
                    :class="inline ? 'h-7 w-7 text-slate-500' : 'gap-1 px-2 py-1 text-xs font-semibold text-slate-600'"
                    @mousedown.prevent
                    @click="fileInput?.click()"
                >
                    <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13" />
                    </svg>
                    <span v-if="!inline">Attach</span>
                </button>
            </template>
        </AppRichTextEditor>

        <div v-if="attachments.length" class="flex flex-wrap gap-1.5 border-t border-slate-100 bg-slate-50/60 p-2">
            <div
                v-for="(file, index) in attachments"
                :key="`${file.name}-${file.size}-${index}`"
                class="group relative overflow-hidden rounded-md border border-slate-200 bg-white transition hover:border-slate-300"
            >
                <button
                    type="button"
                    class="absolute right-0.5 top-0.5 z-10 flex h-4 w-4 items-center justify-center rounded-full bg-slate-900/75 text-white opacity-0 transition group-hover:opacity-100"
                    title="Remove attachment"
                    @click="removeAttachment(index)"
                >
                    <svg class="h-2.5 w-2.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>

                <div v-if="rememberPreview(file)" class="h-14 w-20">
                    <img :src="rememberPreview(file)" :alt="file.name" class="h-full w-full object-cover" />
                </div>
                <div v-else class="flex max-w-[9rem] items-center gap-1.5 px-2 py-1.5">
                    <div class="flex h-7 w-7 shrink-0 items-center justify-center rounded bg-slate-100 text-[9px] font-bold uppercase text-slate-600">
                        {{ extension(file.name) }}
                    </div>
                    <div class="min-w-0">
                        <p class="truncate text-[11px] font-medium text-slate-800">{{ file.name }}</p>
                        <p class="text-[10px] text-slate-500">{{ formatFileSize(file.size) }}</p>
                    </div>
                </div>
            </div>
        </div>

        <div v-if="!compact" class="border-t border-slate-100 px-3 py-1.5 text-[11px] text-slate-400">
            Drag files here or use Attach · {{ attachments.length }}/5 files
        </div>
    </div>

    <div
        v-else-if="expanded"
        class="relative"
        @dragenter="onDragEnter"
        @dragleave="onDragLeave"
        @dragover="onDragOver"
        @drop="onDrop"
    >
        <div v-if="isDragging" class="pointer-events-none absolute inset-0 z-10 flex items-center justify-center rounded-xl bg-blue-50/90">
            <p class="text-sm font-semibold text-blue-700">Drop files to attach</p>
        </div>
        <AppRichTextEditor v-model="body" :placeholder="placeholder" expanded borderless>
            <template #toolbar-extra>
                <span class="mx-1 h-4 w-px bg-slate-300" />
                <input ref="fileInput" type="file" multiple class="hidden" @change="onFilesSelected" />
                <button
                    type="button"
                    title="Attach files"
                    class="inline-flex items-center gap-1.5 rounded-md px-2.5 py-1 text-xs font-semibold text-slate-600 transition hover:bg-slate-100 hover:text-slate-900"
                    @mousedown.prevent
                    @click="fileInput?.click()"
                >
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13" />
                    </svg>
                    Attach
                </button>
            </template>
        </AppRichTextEditor>
        <div v-if="attachments.length" class="flex flex-wrap gap-2 border-t border-slate-100 bg-slate-50/50 p-3">
            <div
                v-for="(file, index) in attachments"
                :key="`${file.name}-${file.size}-${index}`"
                class="group relative overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm"
            >
                <button
                    type="button"
                    class="absolute right-1 top-1 z-10 flex h-5 w-5 items-center justify-center rounded-full bg-slate-900/75 text-white opacity-0 transition group-hover:opacity-100"
                    @click="removeAttachment(index)"
                >
                    <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
                <div v-if="rememberPreview(file)" class="h-20 w-28">
                    <img :src="rememberPreview(file)" :alt="file.name" class="h-full w-full object-cover" />
                </div>
                <div v-else class="flex w-44 items-center gap-2 px-3 py-2">
                    <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-md bg-slate-100 text-[10px] font-bold uppercase text-slate-600">
                        {{ extension(file.name) }}
                    </div>
                    <div class="min-w-0">
                        <p class="truncate text-xs font-medium text-slate-800">{{ file.name }}</p>
                        <p class="text-[11px] text-slate-500">{{ formatFileSize(file.size) }}</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div
        v-else
        class="relative min-w-0 flex-1"
        @dragenter="onDragEnter"
        @dragleave="onDragLeave"
        @dragover="onDragOver"
        @drop="onDrop"
    >
        <div
            v-if="isDragging"
            class="pointer-events-none absolute inset-0 z-10 flex items-center justify-center rounded-lg bg-blue-50/90"
        >
            <p class="text-xs font-semibold text-blue-700">Drop to attach</p>
        </div>
        <AppRichTextEditor v-model="body" :placeholder="placeholder" inline borderless>
            <template #toolbar-extra>
                <input ref="fileInput" type="file" multiple class="hidden" @change="onFilesSelected" />
                <button
                    type="button"
                    title="Attach files"
                    class="inline-flex h-7 w-7 items-center justify-center rounded text-slate-500 transition hover:bg-slate-100 hover:text-slate-900"
                    @mousedown.prevent
                    @click="fileInput?.click()"
                >
                    <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13" />
                    </svg>
                </button>
            </template>
        </AppRichTextEditor>
        <div v-if="attachments.length" class="mt-1 flex flex-wrap gap-1">
            <span
                v-for="(file, index) in attachments"
                :key="`${file.name}-${index}`"
                class="inline-flex items-center gap-1 rounded-full bg-slate-100 px-2 py-0.5 text-[10px] text-slate-600"
            >
                {{ file.name }}
                <button type="button" class="text-slate-400 hover:text-slate-700" @click="removeAttachment(index)">×</button>
            </span>
        </div>
    </div>
</template>
