<script setup>
import { computed, onBeforeUnmount, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import AppRichTextEditor from './AppRichTextEditor.vue';

const { t } = useI18n();

const body = defineModel('body', { type: String, default: '' });
const attachments = defineModel('attachments', { type: Array, default: () => [] });

const props = defineProps({
    placeholder: { type: String, default: undefined },
    compact: { type: Boolean, default: false },
    expanded: { type: Boolean, default: false },
});

const emit = defineEmits(['user-input']);

const resolvedPlaceholder = computed(() => props.placeholder ?? t('components.write_a_reply'));

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

    return parts.length > 1 ? parts.pop().toUpperCase() : t('components.file');
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

const onUserInput = () => {
    emit('user-input');
};

onBeforeUnmount(() => {
    previewUrls.value.forEach((url) => URL.revokeObjectURL(url));
    previewUrls.value.clear();
});
</script>

<template>
    <div
        class="relative overflow-hidden bg-white dark:bg-slate-900 transition duration-200"
        :class="[
            compact && !expanded ? 'rounded-lg ring-1 ring-slate-200 dark:ring-slate-700' : '',
            isDragging ? 'border-blue-400 ring-2 ring-blue-200' : '',
        ]"
        @dragenter="onDragEnter"
        @dragleave="onDragLeave"
        @dragover="onDragOver"
        @drop="onDrop"
    >
        <div
            v-if="isDragging"
            class="pointer-events-none absolute inset-0 z-10 flex items-center justify-center bg-blue-50 dark:bg-blue-950/40/90 backdrop-blur-[1px]"
        >
            <div class="rounded-xl border border-dashed border-blue-300 bg-white dark:bg-slate-900 px-4 py-3 text-center shadow-sm">
                <p class="text-sm font-semibold text-blue-700 dark:text-blue-300">{{ $t('components.drop_files_to_attach') }}</p>
                <p class="mt-1 text-xs text-blue-600/80">{{ $t('components.up_to_5_files_10mb_each') }}</p>
            </div>
        </div>

        <AppRichTextEditor
            v-model="body"
            :placeholder="resolvedPlaceholder"
            :compact="compact && !expanded"
            :expanded="expanded"
            borderless
            @user-input="onUserInput"
        >
            <template #toolbar-extra>
                <span class="mx-1 h-4 w-px bg-slate-300" />
                <input
                    ref="fileInput"
                    type="file"
                    multiple
                    class="hidden"
                    @change="onFilesSelected"
                />
                <button
                    type="button"
                    :title="$t('components.attach_files')"
                    class="inline-flex items-center justify-center rounded transition hover:bg-slate-100 dark:bg-slate-900 dark:hover:bg-slate-800 hover:text-slate-900 dark:hover:text-slate-100 dark:text-slate-100"
                    :class="expanded ? 'gap-1.5 px-2.5 py-1 text-xs font-semibold text-slate-600 dark:text-slate-400' : 'gap-1 px-2 py-1 text-xs font-semibold text-slate-600 dark:text-slate-400'"
                    @mousedown.prevent
                    @click="fileInput?.click()"
                >
                    <svg class="h-3.5 w-3.5" :class="expanded ? 'h-4 w-4' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13" />
                    </svg>
                    <span>{{ $t('components.attach') }}</span>
                </button>
            </template>
        </AppRichTextEditor>

        <div v-if="attachments.length" class="flex flex-wrap gap-1.5 border-t border-slate-100 dark:border-slate-800 bg-slate-50 dark:bg-slate-950/60 p-2">
            <div
                v-for="(file, index) in attachments"
                :key="`${file.name}-${file.size}-${index}`"
                class="group relative overflow-hidden rounded-md border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 transition hover:border-slate-300 dark:hover:border-slate-600 dark:border-slate-700"
            >
                <button
                    type="button"
                    class="absolute right-0.5 top-0.5 z-10 flex h-4 w-4 items-center justify-center rounded-full bg-slate-900/75 text-white opacity-0 transition group-hover:opacity-100"
                    :title="$t('components.remove_attachment')"
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
                    <div class="flex h-7 w-7 shrink-0 items-center justify-center rounded bg-slate-100 dark:bg-slate-900 text-[9px] font-bold uppercase text-slate-600 dark:text-slate-400">
                        {{ extension(file.name) }}
                    </div>
                    <div class="min-w-0">
                        <p class="truncate text-[11px] font-medium text-slate-800 dark:text-slate-200">{{ file.name }}</p>
                        <p class="text-[10px] text-slate-500 dark:text-slate-400">{{ formatFileSize(file.size) }}</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
