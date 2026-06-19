<script setup>
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import AppModal from './AppModal.vue';

const { t } = useI18n();

const props = defineProps({
    attachment: { type: Object, required: true },
    interactive: { type: Boolean, default: true },
});

const lightboxOpen = ref(false);

const isImage = computed(() => (props.attachment.mime_type || '').startsWith('image/'));

const extension = computed(() => {
    const parts = props.attachment.filename?.split('.') ?? [];

    return parts.length > 1 ? parts.pop().toUpperCase() : t('components.file');
});

const formatFileSize = (bytes) => {
    if (!bytes) return '';
    if (bytes < 1024) return `${bytes} B`;
    if (bytes < 1024 * 1024) return `${Math.round(bytes / 1024)} KB`;

    return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
};

const openPreview = () => {
    if (props.interactive && isImage.value) {
        lightboxOpen.value = true;
    }
};
</script>

<template>
    <component
        :is="interactive && !isImage ? 'a' : 'button'"
        :type="interactive && !isImage ? undefined : 'button'"
        :href="interactive && !isImage ? attachment.url : undefined"
        :target="interactive && !isImage ? '_blank' : undefined"
        :rel="interactive && !isImage ? 'noopener' : undefined"
        class="group block overflow-hidden rounded-lg border border-slate-200 dark:border-slate-800/80 bg-white dark:bg-slate-900 text-left transition duration-200"
        :class="[
            isImage ? 'max-w-[220px]' : 'max-w-[260px]',
            interactive ? 'cursor-pointer hover:-translate-y-0.5 hover:border-blue-300 hover:shadow-md active:translate-y-0' : '',
        ]"
        @click="isImage ? openPreview() : undefined"
    >
        <div v-if="isImage" class="relative aspect-[4/3] bg-slate-100 dark:bg-slate-900">
            <img
                :src="attachment.url"
                :alt="attachment.filename"
                class="h-full w-full object-cover transition duration-200 group-hover:scale-[1.02]"
                loading="lazy"
                decoding="async"
            />
            <div class="absolute inset-0 flex items-center justify-center bg-slate-900/0 transition group-hover:bg-slate-900/20">
                <span class="rounded-full bg-white/90 px-2 py-1 text-[10px] font-semibold uppercase tracking-wide text-slate-700 opacity-0 shadow transition group-hover:opacity-100">
                    {{ $t('components.preview') }}
                </span>
            </div>
        </div>
        <div v-else class="flex items-center gap-3 px-3 py-2.5">
            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-md bg-slate-100 dark:bg-slate-900 text-[10px] font-bold uppercase tracking-wide text-slate-600 dark:text-slate-400 transition group-hover:bg-blue-50 dark:bg-blue-950/40 group-hover:text-blue-700 dark:text-blue-300">
                {{ extension }}
            </div>
            <div class="min-w-0">
                <p class="truncate text-sm font-medium text-slate-800 dark:text-slate-200 group-hover:text-blue-700 dark:text-blue-300">{{ attachment.filename }}</p>
                <p class="text-xs text-slate-500 dark:text-slate-400">{{ formatFileSize(attachment.size) }}</p>
            </div>
        </div>
        <div v-if="isImage" class="border-t border-slate-100 dark:border-slate-800 px-2.5 py-1.5">
            <p class="truncate text-xs text-slate-600 dark:text-slate-400">{{ attachment.filename }}</p>
            <p class="text-[11px] text-slate-400 dark:text-slate-500">{{ formatFileSize(attachment.size) }}</p>
        </div>
    </component>

    <AppModal
        v-if="isImage"
        :open="lightboxOpen"
        :title="attachment.filename"
        size="xl"
        @close="lightboxOpen = false"
    >
        <img
            :src="attachment.url"
            :alt="attachment.filename"
            class="mx-auto max-h-[70vh] w-auto max-w-full rounded-lg object-contain"
        />
        <div class="mt-4 flex items-center justify-between gap-3 text-sm text-slate-500 dark:text-slate-400">
            <span>{{ formatFileSize(attachment.size) }}</span>
            <a
                :href="attachment.url"
                target="_blank"
                rel="noopener"
                class="font-medium text-blue-600 hover:text-blue-700 dark:hover:text-blue-300 dark:text-blue-300"
            >
                {{ $t('components.open_original') }}
            </a>
        </div>
    </AppModal>
</template>
