<script setup>
import { router, usePage } from '@inertiajs/vue3';
import { computed, onMounted, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { useAccessibleDialog } from '../composables/useModal.js';
import { readSessionJson, removeSessionItem, storageKey, writeSessionItem, writeSessionJson } from '../support/sessionStorage.js';

const DISMISS_NOTICE_PATH = (noticeId) => `/platform-notices/${noticeId}/dismiss`;

const page = usePage();
const { t } = useI18n();

const scope = computed(() => page.props.tenantId ?? '');
const seenStorageKey = computed(() => storageKey('platform-notices-seen-session', scope.value));
const completeStorageKey = computed(() => storageKey('platform-notices-modal-complete', scope.value));

const modalOpen = ref(false);
const queue = ref([]);
const dismissing = ref(false);
const dismissError = ref(false);
const totalInQueue = ref(0);
const dialogRef = ref(null);
const primaryButtonRef = ref(null);

const styles = {
    maintenance: {
        header: 'border-amber-100 bg-amber-50 dark:bg-amber-950/40',
        icon: 'bg-amber-100 text-amber-700 dark:text-amber-300',
        badge: 'bg-amber-200/60 text-amber-900',
        title: 'text-slate-900 dark:text-slate-100',
        body: 'text-amber-900',
        button: 'bg-amber-600 hover:bg-amber-700',
    },
    offer: {
        header: 'border-emerald-100 bg-emerald-50 dark:bg-emerald-950/40',
        icon: 'bg-emerald-100 text-emerald-700 dark:text-emerald-300',
        badge: 'bg-emerald-200/60 text-emerald-900',
        title: 'text-slate-900 dark:text-slate-100',
        body: 'text-emerald-900',
        button: 'bg-emerald-600 hover:bg-emerald-700',
    },
    announcement: {
        header: 'border-blue-100 bg-blue-50 dark:bg-blue-950/40',
        icon: 'bg-blue-100 text-blue-700 dark:text-blue-300',
        badge: 'bg-blue-200/60 text-blue-900',
        title: 'text-slate-900 dark:text-slate-100',
        body: 'text-blue-900',
        button: 'bg-blue-600 hover:bg-blue-700',
    },
    general: {
        header: 'border-slate-100 dark:border-slate-800 bg-slate-50',
        icon: 'bg-slate-100 dark:bg-slate-900 text-slate-600 dark:text-slate-400',
        badge: 'bg-slate-200/60 text-slate-800 dark:text-slate-200',
        title: 'text-slate-900 dark:text-slate-100',
        body: 'text-slate-800 dark:text-slate-200',
        button: 'bg-slate-700 hover:bg-slate-800',
    },
};

const typeLabelKeys = {
    maintenance: 'notice_maintenance',
    offer: 'notice_offer',
    announcement: 'notice_announcement',
    general: 'notice_general',
};

const current = computed(() => queue.value[0] ?? null);

const dialogOpen = computed(() => modalOpen.value && !!current.value);

const currentIndex = computed(() => Math.max(1, totalInQueue.value - queue.value.length + 1));

const styleFor = (notice) => styles[notice?.notice_type] ?? styles.general;

const typeLabel = (noticeType) => t(`components.${typeLabelKeys[noticeType] ?? 'notice_general'}`);

const readSeenIds = () => readSessionJson(seenStorageKey.value, []);

const markSeen = (id) => {
    const seen = new Set(readSeenIds());
    seen.add(id);
    writeSessionJson(seenStorageKey.value, [...seen]);
};

const markQueueComplete = () => {
    writeSessionItem(completeStorageKey.value, '1');
    window.dispatchEvent(new CustomEvent('platform-notices-complete'));
};

const buildQueue = () => {
    const notices = page.props.platformNotices ?? [];
    const seen = new Set(readSeenIds());

    queue.value = notices.filter((notice) => {
        if (notice.dismissible) {
            return true;
        }

        return !seen.has(notice.id);
    });

    totalInQueue.value = queue.value.length;
    dismissError.value = false;

    if (queue.value.length > 0) {
        removeSessionItem(completeStorageKey.value);
        modalOpen.value = true;

        return;
    }

    modalOpen.value = false;
};

const closeModal = () => {
    modalOpen.value = false;

    if (!queue.value.length) {
        markQueueComplete();
    }
};

const advanceQueue = () => {
    queue.value.shift();

    if (queue.value.length) {
        modalOpen.value = true;
        return;
    }

    closeModal();
};

const dismiss = async (notice) => {
    if (dismissing.value) {
        return;
    }

    dismissError.value = false;

    if (notice.dismissible) {
        dismissing.value = true;

        router.post(DISMISS_NOTICE_PATH(notice.id), {}, {
            preserveScroll: true,
            onError: () => {
                dismissError.value = true;
            },
            onFinish: () => {
                dismissing.value = false;
            },
        });

        return;
    }

    markSeen(notice.id);
    advanceQueue();
};

useAccessibleDialog(dialogOpen, dialogRef, {
    initialFocusRef: primaryButtonRef,
    onEscape: () => {
        if (current.value && !dismissing.value) {
            dismiss(current.value);
        }
    },
});

onMounted(buildQueue);

watch(() => page.props.platformNotices, buildQueue, { deep: true });
</script>

<template>
    <Teleport to="body">
        <Transition
            enter-active-class="transition duration-200 ease-out"
            enter-from-class="opacity-0"
            enter-to-class="opacity-100"
            leave-active-class="transition duration-150 ease-in"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div
                v-if="modalOpen && current"
                class="fixed inset-0 z-[195] flex items-center justify-center bg-slate-900/45 p-4 backdrop-blur-[2px]"
                role="dialog"
                aria-modal="true"
                :aria-label="current.title"
            >
                <div ref="dialogRef" class="w-full max-w-lg overflow-hidden rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 shadow-2xl">
                    <div class="border-b px-5 py-4" :class="styleFor(current).header">
                        <div class="flex items-start gap-3">
                            <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl" :class="styleFor(current).icon">
                                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                            </span>
                            <div class="min-w-0 flex-1">
                                <div class="flex flex-wrap items-center gap-2">
                                    <h2 class="text-lg font-semibold" :class="styleFor(current).title">{{ current.title }}</h2>
                                    <span class="rounded px-1.5 py-0.5 text-[10px] font-semibold uppercase tracking-wide" :class="styleFor(current).badge">
                                        {{ typeLabel(current.notice_type) }}
                                    </span>
                                    <span
                                        v-if="current.priority === 'high'"
                                        class="rounded bg-red-200/70 px-1.5 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-red-900"
                                    >
                                        {{ t('components.important') }}
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="max-h-[min(60vh,24rem)] overflow-y-auto px-5 py-4">
                        <img
                            v-if="current.image_url"
                            :src="current.image_url"
                            :alt="current.title"
                            class="mb-4 max-h-48 w-full rounded-xl border border-slate-200 dark:border-slate-800 object-contain"
                        />
                        <div
                            v-if="current.body_html"
                            class="prose prose-sm max-w-none"
                            :class="styleFor(current).body"
                            v-html="current.body_html"
                        />
                        <p v-else class="text-sm text-slate-600 dark:text-slate-400">{{ t('components.platform_notice_fallback') }}</p>
                    </div>

                    <div class="flex items-center justify-between gap-3 border-t border-slate-100 dark:border-slate-800 px-5 py-4">
                        <div class="min-w-0">
                            <p v-if="totalInQueue > 1" class="text-xs text-slate-400 dark:text-slate-500">
                                {{ t('components.notice_queue_progress', { current: currentIndex, total: totalInQueue }) }}
                            </p>
                            <p v-if="dismissError" class="text-xs text-red-600">
                                {{ t('components.deflection_error') }}
                            </p>
                        </div>

                        <button
                            ref="primaryButtonRef"
                            type="button"
                            class="shrink-0 rounded-lg px-4 py-2 text-sm font-semibold text-white transition disabled:opacity-60"
                            :class="styleFor(current).button"
                            :disabled="dismissing"
                            @click="dismiss(current)"
                        >
                            {{ current.dismissible ? t('components.dismiss') : t('components.got_it') }}
                        </button>
                    </div>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>
